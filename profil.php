<?php
require_once 'init.php';

// On récupère l'id du GP dans l'URL
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    include '404.php';
}

// Infos du GP
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Si le GP n'existe pas → page « introuvable »
if (!$user) {
    $titre_404 = 'Profil introuvable';
    $texte_404 = "Ce membre n'existe pas ou a supprimé son compte.";
    include '404.php';
}

// Trajets du GP
$stmt2 = $conn->prepare("SELECT * FROM trajets WHERE user_id = ? ORDER BY date_depart ASC");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$trajets = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

// Numéros WhatsApp : ceux du profil + ceux propres aux trajets publiés pour autrui
$contacts_gp = contacts_profil($conn, $id);

// ============================================================
// AVIS : publier / modifier / supprimer le sien (ou n'importe lequel pour l'admin)
// ============================================================
$moi = (int)($_SESSION['user_id'] ?? 0);
$erreur_avis = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && $moi) {
    if (isset($_POST['supprimer_avis'])) {
        $avis_id = (int)$_POST['supprimer_avis'];
        if ($nav_is_admin) {
            $stmt = $conn->prepare("DELETE FROM avis WHERE id = ? AND gp_id = ?");
            $stmt->bind_param("ii", $avis_id, $id);
        } else {
            $stmt = $conn->prepare("DELETE FROM avis WHERE id = ? AND auteur_id = ?");
            $stmt->bind_param("ii", $avis_id, $moi);
        }
        $stmt->execute();
        $stmt->close();
        header("Location: profil.php?id=" . $id . "#avis");
        exit();
    }
    if (isset($_POST['note'])) {
        $note = (int)$_POST['note'];
        $commentaire = mb_substr(trim($_POST['commentaire'] ?? ''), 0, 1000);
        if (!peut_noter($conn, $moi, $id)) {
            $erreur_avis = "Tu peux laisser un avis seulement après avoir échangé des messages avec ce GP.";
        } elseif ($note < 1 || $note > 5) {
            $erreur_avis = "Choisis une note de 1 à 5 étoiles.";
        } else {
            $commentaire = $commentaire === '' ? null : $commentaire;
            $stmt = $conn->prepare("INSERT INTO avis (gp_id, auteur_id, note, commentaire) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE note = VALUES(note), commentaire = VALUES(commentaire), updated_at = NOW()");
            $stmt->bind_param("iiis", $id, $moi, $note, $commentaire);
            $stmt->execute();
            $nouveau = $stmt->affected_rows === 1; // 1 = ajout, 2 = modification
            $stmt->close();

            if ($nouveau && $user['email_verifie'] && $user['notif_email']) {
                email_envoyer($user['email'], 'Nouvel avis sur ton profil - SafeGP', email_gabarit(
                    'Tu as reçu un avis ' . str_repeat('★', $note) . str_repeat('☆', 5 - $note),
                    '<p>' . htmlspecialchars($_SESSION['user_nom'] ?? 'Un membre') . ' a laissé un avis sur ton profil'
                    . ($commentaire ? ' :</p><blockquote style="border-left:3px solid #f97316;margin:0;padding:5px 15px;">' . nl2br(htmlspecialchars($commentaire)) . '</blockquote>' : '.</p>'),
                    'Voir mon profil',
                    url_site() . '/profil.php?id=' . $id . '#avis'
                ));
            }
            header("Location: profil.php?id=" . $id . "#avis");
            exit();
        }
    }
}

$stmt = $conn->prepare("SELECT avis.*, users.nom, users.username, users.photo FROM avis JOIN users ON users.id = avis.auteur_id WHERE gp_id = ? ORDER BY avis.id DESC");
$stmt->bind_param("i", $id);
$stmt->execute();
$liste_avis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$stats_avis = avis_stats($conn, [$id])[$id] ?? null;
$mon_avis = null;
foreach ($liste_avis as $a) if ((int)$a['auteur_id'] === $moi) $mon_avis = $a;
$peut_noter = $moi && peut_noter($conn, $moi, $id);
$contacts = contacts_pour_trajets($conn, $trajets);
$conn->close();

// Aperçu du lien quand on partage ce profil (WhatsApp, Facebook...)
$og_title = $user['nom'] . " — GP sur SafeGP";
$og_description = "Voir les trajets de " . $user['nom'] . " et lui confier vos colis, partout dans le monde.";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Profil - <?= e($user['nom']) ?></title>
</head>
<body>
<?php include 'navbar.php'; ?>

    <section class="profil">

        <div class="profil-header">

            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $user['id']): ?>
                <a href="messagerie.php?with=<?= (int)$user['id'] ?>" class="btn-contact">
                    <i class="fa-solid fa-message"></i> Contacter
                </a>
            <?php endif; ?>

            <div class="profil-avatar">
                <?= avatar($user['nom'], $user['photo']) ?>
            </div>
            <div>
                <h2><?= e($user['nom']) ?> <?= badge_verifie($user['verifie']) ?></h2>
                <?php if (!empty($user['username'])): ?>
                    <p style="color:var(--orange);">@<?= e($user['username']) ?></p>
                <?php endif; ?>
                <p><?= badge_dispo_gp($user['disponible']) ?> <?php if ($stats_avis): ?><a href="#avis" class="lien-avis"><?= resume_avis($stats_avis) ?></a><?php endif; ?></p>
                <?php if (!empty($user['joignable_debut']) && !empty($user['joignable_fin'])): ?>
                    <p class="horaires"><i class="fa-regular fa-clock"></i>
                        Joignable de <?= e(heure_fr($user['joignable_debut'])) ?> à <?= e(heure_fr($user['joignable_fin'])) ?>
                    </p>
                <?php endif; ?>
                <p class="profil-date">Membre depuis <?= date('d/m/Y', strtotime($user['created_at'])) ?></p>
            </div>
        </div>

        <?php if ($contacts_gp): ?>
            <div class="contacts-profil">
                <?php afficher_boutons_whatsapp($contacts_gp); ?>
            </div>
        <?php endif; ?>

        <?php
            // Trajets à venir d'abord, puis les trajets passés (repliés)
            $a_venir = array_filter($trajets, fn($t) => $t['date_arrivee'] >= date('Y-m-d'));
            $passes = array_filter($trajets, fn($t) => $t['date_arrivee'] < date('Y-m-d'));
        ?>
        <h3>Trajets à venir (<?= count($a_venir) ?>)</h3>

        <?php if ($trajets): ?>
            <?php if (!$a_venir): ?><p class="texte-doux-bloc">Aucun trajet à venir pour l'instant.</p><?php endif; ?>
            <?php foreach (array_merge($a_venir, $passes) as $i => $row): ?>
                <?php if ($passes && $i === count($a_venir)): ?>
                    <details class="trajets-passes" <?= isset($_GET['passes']) ? 'open' : '' ?>>
                    <summary>Trajets passés (<?= count($passes) ?>)</summary>
                <?php endif; ?>
                <div class="annonce" id="trajet-<?= (int)$row['id'] ?>">
                    <?php $avec_gp = false; include 'trajet_infos.php'; ?>

                    <div class="annonce-footer">
                        <a href="trajet.php?id=<?= (int)$row['id'] ?>" class="btn-contact btn-secondaire"><i class="fa-solid fa-share-nodes"></i> Voir / partager</a>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $user['id']): ?>
                            <a href="messagerie.php?with=<?= (int)$user['id'] ?>&amp;trajet=<?= (int)$row['id'] ?>" class="btn-contact">
                                <i class="fa-solid fa-message"></i> Contacter pour ce trajet
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($row['telephone'])): ?>
                            <a href="tel:<?= e($row['telephone']) ?>" class="btn-contact btn-secondaire">
                                <i class="fa-solid fa-phone"></i> <?= e($row['telephone']) ?>
                            </a>
                        <?php endif; ?>

                        <?php afficher_boutons_whatsapp($contacts[$row['id']] ?? []); ?>

                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($passes): ?></details><?php endif; ?>

        <?php else: ?>
            <p>Ce GP n'a pas encore publié de trajet.</p>
        <?php endif; ?>

        <!-- AVIS -->
        <h3 id="avis">Avis <?= $stats_avis ? '(' . $stats_avis['nombre'] . ')' : '' ?></h3>
        <?php if ($stats_avis): ?>
            <p class="avis-moyenne"><?= resume_avis($stats_avis) ?></p>
        <?php endif; ?>

        <?php if ($erreur_avis): ?>
            <p class="erreur"><i class="fa-solid fa-circle-xmark"></i> <?= e($erreur_avis) ?></p>
        <?php endif; ?>

        <?php if ($peut_noter): ?>
            <form action="profil.php?id=<?= (int)$id ?>#avis" method="POST" class="form-avis">
                <?= csrf_field() ?>
                <p><strong><?= $mon_avis ? 'Modifier ton avis' : 'Laisser un avis sur ' . e($user['nom']) ?></strong></p>
                <div class="choix-etoiles" role="radiogroup" aria-label="Note">
                    <?php for ($n = 5; $n >= 1; $n--): ?>
                        <input type="radio" name="note" value="<?= $n ?>" id="note-<?= $n ?>" <?= ($mon_avis['note'] ?? 0) == $n ? 'checked' : '' ?> required>
                        <label for="note-<?= $n ?>" title="<?= $n ?> étoile<?= $n > 1 ? 's' : '' ?>"><i class="fa-solid fa-star"></i></label>
                    <?php endfor; ?>
                </div>
                <div class="form-group">
                    <textarea name="commentaire" maxlength="1000" placeholder="Comment s'est passé l'envoi ? (ponctualité, soin du colis, communication...)"><?= e($mon_avis['commentaire'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn-contact"><i class="fa-solid fa-paper-plane"></i> <?= $mon_avis ? 'Mettre à jour' : 'Publier mon avis' ?></button>
            </form>
        <?php elseif ($moi && $moi !== (int)$id): ?>
            <p class="aide-contact"><i class="fa-solid fa-circle-info"></i> Tu pourras laisser un avis après avoir échangé avec ce GP dans la messagerie.</p>
        <?php endif; ?>

        <?php if ($liste_avis): ?>
            <div class="liste-avis">
                <?php foreach ($liste_avis as $a): ?>
                    <div class="carte-avis">
                        <div class="carte-avis-entete">
                            <span class="avatar-mini"><?= avatar($a['nom'], $a['photo']) ?></span>
                            <a href="profil.php?id=<?= (int)$a['auteur_id'] ?>">@<?= e($a['username'] ?? $a['nom']) ?></a>
                            <?= etoiles((float)$a['note']) ?>
                            <span class="msg-date"><?= date('d/m/Y', strtotime($a['updated_at'] ?? $a['created_at'])) ?></span>
                            <?php if ((int)$a['auteur_id'] === $moi || $nav_is_admin): ?>
                                <form action="profil.php?id=<?= (int)$id ?>" method="POST" class="form-inline" onsubmit="return confirm('Supprimer cet avis ?')">
                                    <?= csrf_field() ?>
                                    <button type="submit" name="supprimer_avis" value="<?= (int)$a['id'] ?>" class="btn-lien" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php if ($a['commentaire']): ?><p><?= nl2br(e($a['commentaire'])) ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif (!$peut_noter): ?>
            <p class="texte-doux-bloc">Pas encore d'avis.</p>
        <?php endif; ?>

    </section>

    <?php include 'footer.php'; ?>

</body>
</html>
