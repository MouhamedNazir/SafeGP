<?php
require_once 'init.php';

// Vérification — seuls les admins peuvent accéder
// Si pas connecté ou pas admin → redirection
if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

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

// Passer un utilisateur en premium (publications illimitées) ou en gratuit
if (isset($_POST['changer_premium'])) {
    $id = (int)$_POST['user_id'];
    $premium = !empty($_POST['premium']) ? 1 : 0;
    $stmt = $conn->prepare("UPDATE users SET premium = ? WHERE id = ?");
    $stmt->bind_param("ii", $premium, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin.php?success=premium_modifie");
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
                if ($_GET['success'] == 'premium_modifie') echo "Abonnement modifié !";
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
                    <!-- Gratuit / premium -->
                    <form action="admin.php" method="POST" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="changer_premium" value="1">
                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                        <select name="premium" onchange="this.form.submit()"
                                class="select-petit">
                            <option value="0" <?= !$user['premium'] ? 'selected' : '' ?>>gratuit</option>
                            <option value="1" <?= $user['premium'] ? 'selected' : '' ?>>premium</option>
                        </select>
                    </form>
                    <?php if (!$user['premium'] && $user['role'] != 'admin'): ?>
                        <small style="color:var(--texte-doux); display:block;"><?= (int)$user['publications_mois'] ?>/<?= FREEMIUM_LIMITE_MOIS ?> ce mois</small>
                    <?php endif; ?>
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