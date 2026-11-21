<?php
require_once 'init.php';

// Vérification — seuls les admins peuvent accéder
// Si pas connecté ou pas admin → redirection
exiger_connexion();

// On vérifie le rôle dans la DB — pas seulement en session
// car quelqu'un pourrait modifier sa session manuellement
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($admin['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// ============================================================
// ACTIONS ADMIN
// ============================================================

// Supprimer un trajet abusif
// Les suppressions passent par POST (+ jeton CSRF vérifié dans navbar.php)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['supprimer_trajet'])) {
    $id = (int)$_POST['supprimer_trajet'];
    $stmt = $conn->prepare("DELETE FROM trajets WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin.php?success=trajet_supprime");
    exit();
}

// Supprimer un utilisateur
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['supprimer_user'])) {
    $id = (int)$_POST['supprimer_user'];

    // On vérifie que ce n'est pas un admin
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $cible = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($cible && $cible['role'] !== 'admin' && !supprimer_utilisateur($conn, $id)) {
        header("Location: admin.php?erreur=suppression");
        exit();
    }
    header("Location: admin.php?success=user_supprime");
    exit();
}

// Changer le rôle d'un utilisateur
if (isset($_POST['changer_role'])) {
    $id = (int)$_POST['user_id'];
    $role = $_POST['role'];
    $roles_autorises = ['user', 'admin'];
    if (in_array($role, $roles_autorises)) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $role, $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: admin.php?success=role_modifie");
    exit();
}

// Changer la formule d'un membre à la main (abonnement offert, correction...)
// « fin » vide = sans date de fin
if (isset($_POST['changer_formule'])) {
    $id = (int)$_POST['user_id'];
    $formule = $_POST['formule'] ?? 'gratuit';
    $fin = vide_en_null($_POST['fin'] ?? '');
    if (isset(FORMULES[$formule]) && ($fin === null || strtotime($fin))) {
        if ($formule === 'gratuit') $fin = null;
        $stmt = $conn->prepare("UPDATE users SET formule = ?, abonnement_fin = ?, rappel_abonnement = NULL WHERE id = ?");
        $stmt->bind_param("ssi", $formule, $fin, $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: admin.php?success=formule_modifiee");
    exit();
}

// Demandes d'abonnement : valider (après vérification du paiement) ou refuser
if (isset($_POST['valider_abonnement'])) {
    $ok = abonnement_valider($conn, (int)$_POST['valider_abonnement']);
    header("Location: admin.php?success=" . ($ok ? 'abonnement_valide' : 'abonnement_deja_traite') . "#abonnements");
    exit();
}
if (isset($_POST['refuser_abonnement'])) {
    abonnement_refuser($conn, (int)$_POST['refuser_abonnement'], (string)($_POST['motif'] ?? ''));
    header("Location: admin.php?success=abonnement_refuse#abonnements");
    exit();
}

// Retours clients : marquer lu / non lu, ou supprimer
if (isset($_POST['retour_lu'])) {
    $id = (int)$_POST['retour_id'];
    $lu = $_POST['retour_lu'] === '1' ? 1 : 0;
    $stmt = $conn->prepare("UPDATE retours SET lu = ? WHERE id = ?");
    $stmt->bind_param("ii", $lu, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin.php#retours");
    exit();
}
if (isset($_POST['supprimer_retour'])) {
    $id = (int)$_POST['supprimer_retour'];
    $stmt = $conn->prepare("DELETE FROM retours WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin.php?success=retour_supprime#retours");
    exit();
}

// ============================================================
// STATISTIQUES JE LES QFFICHES IICI
// ============================================================

// Nombre total d'utilisateurs
$total_users = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];

// Nombre total de trajets
$total_trajets = $conn->query("SELECT COUNT(*) as total FROM trajets")->fetch_assoc()['total'];

// Nombre de trajets disponibles
$trajets_dispo = $conn->query("SELECT COUNT(*) as total FROM trajets WHERE statut = 'disponible'")->fetch_assoc()['total'];

// ============================================================
// DONNÉES
// ============================================================

// Tous les utilisateurs, avec leurs publications du mois (limite freemium)
$debut_mois = $conn->real_escape_string(date('Y-m-01 00:00:00'));
$users = $conn->query("
    SELECT users.*,
           (SELECT COUNT(*) FROM trajets WHERE trajets.user_id = users.id AND trajets.created_at >= '$debut_mois') AS publications_mois
    FROM users ORDER BY created_at DESC");

// Demandes d'abonnement : en attente, puis les 15 dernières traitées
$demandes = $conn->query("
    SELECT a.*, u.nom, u.username, u.email
    FROM abonnements a JOIN users u ON u.id = a.user_id
    ORDER BY (a.statut = 'en_attente') DESC, a.id DESC
    LIMIT 40")->fetch_all(MYSQLI_ASSOC);
$demandes_en_attente = count(array_filter($demandes, fn($d) => $d['statut'] === 'en_attente'));
$revenus_mois = (int)$conn->query("SELECT COALESCE(SUM(montant), 0) FROM abonnements WHERE statut = 'valide' AND traite_le >= '" . date('Y-m-01') . "'")->fetch_row()[0];
$abonnes_actifs = (int)$conn->query("SELECT COUNT(*) FROM users WHERE formule <> 'gratuit' AND (abonnement_fin IS NULL OR abonnement_fin >= CURDATE())")->fetch_row()[0];

// Retours clients, non lus d'abord
$retours = $conn->query("
    SELECT retours.*, users.username
    FROM retours LEFT JOIN users ON retours.user_id = users.id
    ORDER BY retours.lu ASC, retours.created_at DESC");
$retours_non_lus = (int)$conn->query("SELECT COUNT(*) FROM retours WHERE lu = 0")->fetch_row()[0];

// Tous les trajets
$trajets = $conn->query("
    SELECT trajets.*, users.nom 
    FROM trajets 
    LEFT JOIN users ON trajets.user_id = users.id 
    ORDER BY trajets.id DESC
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Admin - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

<section class="profil">
    <h2><i class="fa-solid fa-shield"></i> Panel Admin</h2>

    <?php if (isset($_GET['success'])): ?>
        <p class="success">
            <i class="fa-solid fa-circle-check"></i>
            <?php
                if ($_GET['success'] == 'trajet_supprime') echo "Trajet supprimé !";
                if ($_GET['success'] == 'user_supprime') echo "Utilisateur supprimé !";
                if ($_GET['success'] == 'role_modifie') echo "Rôle modifié !";
                if ($_GET['success'] == 'formule_modifiee') echo "Formule modifiée !";
                if ($_GET['success'] == 'abonnement_valide') echo "Abonnement validé et activé, le membre a été prévenu par e-mail.";
                if ($_GET['success'] == 'abonnement_refuse') echo "Demande refusée, le membre a été prévenu par e-mail.";
                if ($_GET['success'] == 'abonnement_deja_traite') echo "Cette demande avait déjà été traitée.";
                if ($_GET['success'] == 'retour_supprime') echo "Retour supprimé !";
            ?>
        </p>
    <?php endif; ?>
    <?php if (isset($_GET['erreur']) && $_GET['erreur'] == 'suppression'): ?>
        <p class="erreur"><i class="fa-solid fa-circle-xmark"></i> Impossible de supprimer cet utilisateur.</p>
    <?php endif; ?>

    <!-- STATISTIQUES -->
    <div style="display:flex; gap:20px; margin-bottom:40px; flex-wrap:wrap;">
        <div class="stat-card">
            <i class="fa-solid fa-users" style="font-size:30px; color:var(--orange);"></i>
            <h3><?= $total_users ?></h3>
            <p>Utilisateurs</p>
        </div>
        <div class="stat-card">
            <i class="fa-solid fa-plane" style="font-size:30px; color:var(--orange);"></i>
            <h3><?= $total_trajets ?></h3>
            <p>Trajets total</p>
        </div>
        <div class="stat-card">
            <i class="fa-solid fa-circle-check" style="font-size:30px; color:var(--vert);"></i>
            <h3><?= $trajets_dispo ?></h3>
            <p>Trajets disponibles</p>
        </div>
        <a href="#abonnements" class="stat-card" style="text-decoration:none; color:inherit;">
            <i class="fa-solid fa-crown" style="font-size:30px; color:var(--jaune-texte);"></i>
            <h3><?= $abonnes_actifs ?></h3>
            <p>Abonnés actifs · <?= e(prix_fcfa($revenus_mois)) ?> ce mois</p>
        </a>
        <a href="#abonnements" class="stat-card" style="text-decoration:none; color:inherit;">
            <i class="fa-solid fa-hourglass-half" style="font-size:30px; color:var(--orange);"></i>
            <h3><?= $demandes_en_attente ?></h3>
            <p>Paiements à vérifier</p>
        </a>
        <a href="#retours" class="stat-card" style="text-decoration:none; color:inherit;">
            <i class="fa-solid fa-lightbulb" style="font-size:30px; color:#facc15;"></i>
            <h3><?= $retours_non_lus ?></h3>
            <p>Retours non lus</p>
        </a>
    </div>

    <!-- UTILISATEURS -->
    <h3 style="color:var(--orange); margin-bottom:20px;">
        <i class="fa-solid fa-users"></i> Utilisateurs
    </h3>

    <div class="table-defilante">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nom</th>
                <th>Username</th>
                <th>Email</th>
                <th>Rôle</th>
                <th>Abonnement</th>
                <th>Inscrit le</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while($user = $users->fetch_assoc()): ?>
            <tr>
                <td><?= $user['id'] ?></td>
                <td><?= htmlspecialchars($user['nom']) ?></td>
                <td>@<?= htmlspecialchars($user['username'] ?? '') ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td>
                    <!-- Changer le rôle -->
                    <form action="admin.php" method="POST" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="changer_role" value="1">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <select name="role" onchange="this.form.submit()"
                                class="select-petit">
                            <option value="user" <?= $user['role'] == 'user' ? 'selected' : '' ?>>user</option>
                            <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>admin</option>
                        </select>
                    </form>
                </td>
                <td>
                    <!-- Formule (modifiable à la main) -->
                    <form action="admin.php" method="POST" class="form-formule">
                        <?= csrf_field() ?>
                        <input type="hidden" name="changer_formule" value="1">
                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                        <select name="formule" class="select-petit" aria-label="Formule">
                            <?php foreach (FORMULES as $code => $f): ?>
                                <option value="<?= $code ?>" <?= $user['formule'] === $code ? 'selected' : '' ?>><?= e($f['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="fin" class="select-petit" value="<?= e($user['abonnement_fin'] ?? '') ?>" title="Fin (vide = sans fin)" aria-label="Date de fin">
                        <button type="submit" class="btn-lien" title="Enregistrer"><i class="fa-solid fa-floppy-disk"></i></button>
                    </form>
                    <?php $fa = formule_active($user); $lim = FORMULES[$fa]['limite']; ?>
                    <small class="texte-doux-bloc">
                        <?php if ($user['formule'] !== 'gratuit' && $fa === 'gratuit'): ?>expiré · <?php endif; ?>
                        <?= (int)$user['publications_mois'] ?>/<?= $lim === null ? '∞' : $lim ?> ce mois
                    </small>
                </td>
                <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                <td>
                    <?php if ($user['role'] != 'admin'): ?>
                        <form action="admin.php" method="POST" style="display:inline; margin:0;"
                              onsubmit="return confirm('Supprimer cet utilisateur ?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="supprimer_user" value="<?= (int)$user['id'] ?>">
                            <button type="submit" title="Supprimer" style="background:none; border:none; padding:0; cursor:pointer; color:var(--orange);">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    <?php else: ?>
                        <span style="color:var(--texte-doux);">protégé</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    </div>

    <!-- TRAJETS -->
    <h3 style="color:var(--orange); margin:40px 0 20px;">
        <i class="fa-solid fa-plane"></i> Trajets
    </h3>

    <div class="table-defilante">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>GP</th>
                <th>Trajet</th>
                <th>Dates</th>
                <th>Prix</th>
                <th>Statut</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $trajets->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['nom'] ?? 'Inconnu') ?></td>
                <td><?= htmlspecialchars($row['depart']) ?> → <?= htmlspecialchars($row['arrivee']) ?></td>
                <td><?= date('d/m/Y', strtotime($row['date_depart'])) ?></td>
                <td><?= e(prix_kilo_fr($row['prix_kilo'], $row['devise'])) ?></td>
                <td>
                    <?php
                        $couleurs = [
                            'disponible' => '#16a34a',
                            'en_route'   => '#f97316',
                            'arrivé'     => '#94a3b8'
                        ];
                        $couleur = $couleurs[$row['statut']] ?? '#94a3b8';
                    ?>
                    <span style="background-color:<?= $couleur ?>; padding:3px 10px; border-radius:20px; font-size:12px;">
                        <?= htmlspecialchars($row['statut']) ?>
                    </span>
                </td>
                <td>
                    <form action="admin.php" method="POST" style="display:inline; margin:0;"
                          onsubmit="return confirm('Supprimer ce trajet ?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="supprimer_trajet" value="<?= (int)$row['id'] ?>">
                        <button type="submit" title="Supprimer" style="background:none; border:none; padding:0; cursor:pointer; color:var(--orange);">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    </div>

    <!-- ABONNEMENTS -->
    <h3 id="abonnements" class="titre-admin">
        <i class="fa-solid fa-crown"></i> Abonnements
        <?php if ($demandes_en_attente): ?><span class="badge-nonlu"><?= $demandes_en_attente ?></span><?php endif; ?>
    </h3>
    <p class="aide-contact">Vérifie chaque paiement dans ton application Wave ou Orange Money (montant et référence) avant de valider.</p>

    <?php if (!$demandes): ?>
        <p class="texte-doux-bloc">Aucune demande d'abonnement pour l'instant.</p>
    <?php else: ?>
    <div class="table-defilante">
    <table class="admin-table">
        <thead>
            <tr><th>Date</th><th>Membre</th><th>Formule</th><th>Montant</th><th>Paiement</th><th>Statut</th></tr>
        </thead>
        <tbody>
        <?php foreach ($demandes as $d): ?>
            <tr class="<?= $d['statut'] === 'en_attente' ? 'ligne-attente' : '' ?>">
                <td><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
                <td><a href="profil.php?id=<?= (int)$d['user_id'] ?>">@<?= e($d['username'] ?? $d['nom']) ?></a><br><small class="texte-doux-bloc"><?= e($d['email']) ?></small></td>
                <td><?= e(FORMULES[$d['formule']]['nom']) ?> · <?= $d['duree'] === 'an' ? '1 an' : '1 mois' ?></td>
                <td><strong><?= e(prix_fcfa((int)$d['montant'])) ?></strong></td>
                <td><?= e(MOYENS_PAIEMENT[$d['moyen']]) ?><br><code><?= e($d['reference']) ?></code><?php if ($d['telephone']): ?><br><small><?= e($d['telephone']) ?></small><?php endif; ?></td>
                <td>
                    <?php if ($d['statut'] === 'en_attente'): ?>
                        <form action="admin.php" method="POST" class="form-inline" onsubmit="return confirm('Paiement vérifié ? L\'abonnement sera activé.')">
                            <?= csrf_field() ?>
                            <button type="submit" name="valider_abonnement" value="<?= (int)$d['id'] ?>" class="btn-contact btn-petit"><i class="fa-solid fa-check"></i> Valider</button>
                        </form>
                        <details class="refus">
                            <summary>Refuser</summary>
                            <form action="admin.php" method="POST">
                                <?= csrf_field() ?>
                                <input type="text" name="motif" class="select-petit" placeholder="Motif (ex : paiement introuvable)" maxlength="255">
                                <button type="submit" name="refuser_abonnement" value="<?= (int)$d['id'] ?>" class="btn-contact btn-petit btn-supprimer">Refuser</button>
                            </form>
                        </details>
                    <?php elseif ($d['statut'] === 'valide'): ?>
                        <span class="statut-ok">Validé</span><br><small class="texte-doux-bloc">du <?= date('d/m/Y', strtotime($d['date_debut'])) ?> au <?= date('d/m/Y', strtotime($d['date_fin'])) ?></small>
                    <?php else: ?>
                        <span class="statut-refus">Refusé</span><br><small class="texte-doux-bloc"><?= e($d['motif_refus']) ?></small>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <!-- RETOURS CLIENTS -->
    <h3 id="retours" style="color:var(--orange); margin:40px 0 20px;">
        <i class="fa-solid fa-lightbulb"></i> Retours clients
        <?php if ($retours_non_lus): ?><span class="badge-nonlu"><?= $retours_non_lus ?></span><?php endif; ?>
    </h3>

    <?php if ($retours->num_rows === 0): ?>
        <p style="color:var(--texte-doux);">Aucun retour pour l'instant. Les visiteurs peuvent t'en envoyer depuis la page <a href="retours.php">Donner mon avis</a>.</p>
    <?php endif; ?>

    <?php $types_retour = ['suggestion' => 'Suggestion', 'probleme' => 'Problème', 'autre' => 'Autre']; ?>
    <?php while ($r = $retours->fetch_assoc()): ?>
        <div class="retour-carte <?= $r['lu'] ? 'retour-lu' : '' ?>">
            <div class="retour-entete">
                <span class="retour-type type-<?= e($r['type']) ?>"><?= e($types_retour[$r['type']] ?? $r['type']) ?></span>
                <span>
                    <?php if ($r['user_id']): ?>
                        <a href="profil.php?id=<?= (int)$r['user_id'] ?>">@<?= e($r['username'] ?? $r['nom']) ?></a>
                    <?php else: ?>
                        <?= e($r['nom'] ?: 'Anonyme') ?>
                    <?php endif; ?>
                    <?php if (!empty($r['email'])): ?>
                        · <a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a>
                    <?php endif; ?>
                </span>
                <span class="msg-date"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></span>
            </div>
            <p class="retour-message"><?= nl2br(e($r['message'])) ?></p>
            <div class="retour-actions">
                <form action="admin.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="retour_id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="retour_lu" value="<?= $r['lu'] ? '0' : '1' ?>">
                    <button type="submit" class="btn-lien">
                        <i class="fa-solid <?= $r['lu'] ? 'fa-envelope' : 'fa-envelope-open' ?>"></i>
                        <?= $r['lu'] ? 'Marquer non lu' : 'Marquer comme lu' ?>
                    </button>
                </form>
                <form action="admin.php" method="POST" onsubmit="return confirm('Supprimer ce retour ?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="supprimer_retour" value="<?= (int)$r['id'] ?>">
                    <button type="submit" class="btn-lien" style="color:var(--orange);"><i class="fa-solid fa-trash"></i> Supprimer</button>
                </form>
            </div>
        </div>
    <?php endwhile; ?>

</section>

<?php include 'footer.php'; ?>

</body>
</html>