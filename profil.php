<?php
require_once 'init.php';

// On récupère l'id du GP dans l'URL
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header("Location: index.php");
    exit();
}

// Infos du GP
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Si le GP n'existe pas → redirection
if (!$user) {
    header("Location: index.php");
    exit();
}

// Trajets du GP
$stmt2 = $conn->prepare("SELECT * FROM trajets WHERE user_id = ? ORDER BY date_depart ASC");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$trajets = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

// Numéros WhatsApp : ceux du profil + ceux propres aux trajets publiés pour autrui
$contacts_gp = contacts_profil($conn, $id);
$contacts = contacts_pour_trajets($conn, $trajets);
$conn->close();

// Aperçu du lien quand on partage ce profil (WhatsApp, Facebook...)
$og_title = $user['nom'] . " — GP sur SafeGP";
$og_description = "Voir les trajets de " . $user['nom'] . " et lui confier vos colis entre le Maroc et le Sénégal.";
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
                <?php if (!empty($user['photo'])): ?>
                    <img src="uploads/<?= e($user['photo']) ?>"
                         alt="photo"
                         style="width:70px; height:70px; border-radius:50%; object-fit:cover;">
                <?php else: ?>
                    <?= e(strtoupper(mb_substr($user['nom'], 0, 1))) ?>
                <?php endif; ?>
            </div>
            <div>
                <h2><?= e($user['nom']) ?></h2>
                <?php if (!empty($user['username'])): ?>
                    <p style="color:#f97316;">@<?= e($user['username']) ?></p>
                <?php endif; ?>
                <p><?= badge_dispo_gp($user['disponible']) ?></p>
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

        <h3>Trajets</h3>

        <?php if ($trajets): ?>
            <?php foreach ($trajets as $row): ?>
                <div class="annonce">
                    <?php $avec_gp = false; include 'trajet_infos.php'; ?>

                    <div class="annonce-footer" style="display:flex; gap:10px; flex-wrap:wrap;">
                        <?php if (!empty($row['telephone'])): ?>
                            <a href="tel:<?= e($row['telephone']) ?>" class="btn-contact">
                                <i class="fa-solid fa-phone"></i> <?= e($row['telephone']) ?>
                            </a>
                        <?php endif; ?>

                        <?php afficher_boutons_whatsapp($contacts[$row['id']] ?? []); ?>

                        <?php if (empty($row['telephone']) && empty($contacts[$row['id']])): ?>
                            <span class="no-contact"><i class="fa-solid fa-phone"></i> Contact non renseigné</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php else: ?>
            <p>Ce GP n'a pas encore publié de trajet.</p>
        <?php endif; ?>

    </section>

    <?php include 'footer.php'; ?>

</body>
</html>
