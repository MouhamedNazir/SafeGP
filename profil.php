<?php
include 'config.php';
include 'navbar.php';

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
$trajets = $stmt2->get_result();
$stmt2->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Profil - <?= htmlspecialchars($user['nom']) ?></title>
</head>
<body>

    <section class="profil">

        <div class="profil-header">

            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $user['id']): ?>
                <a href="messagerie.php?with=<?= $user['id'] ?>" class="btn-contact">
                    <i class="fa-solid fa-message"></i> Contacter
                </a>
            <?php endif; ?>

            <div class="profil-avatar">
                <?php if (!empty($user['photo'])): ?>
                    <img src="uploads/<?= htmlspecialchars($user['photo']) ?>" 
                         alt="photo" 
                         style="width:70px; height:70px; border-radius:50%; object-fit:cover;">
                <?php else: ?>
                    <?= strtoupper(substr($user['nom'], 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div>
                <h2><?= htmlspecialchars($user['nom']) ?></h2>
                <?php if (!empty($user['username'])): ?>
                    <p style="color:#f97316;">@<?= htmlspecialchars($user['username']) ?></p>
                <?php endif; ?>
                <p class="profil-date">Membre depuis <?= date('d/m/Y', strtotime($user['created_at'])) ?></p>
            </div>
        </div>

        <h3>Trajets</h3>

        <?php if ($trajets->num_rows > 0): ?>
            <?php while($row = $trajets->fetch_assoc()): ?>
                <div class="annonce">

                    <div class="annonce-header">
                        <span class="trajet-titre">
                            <i class="fa-solid fa-plane-departure"></i> <?= htmlspecialchars($row['depart']) ?>
                            →
                            <i class="fa-solid fa-plane-arrival"></i> <?= htmlspecialchars($row['arrivee']) ?>
                        </span>
                        <div style="display:flex; gap:10px; align-items:center;">
                            <?php
                                // Couleur du badge selon le statut
                                $couleurs = [
                                    'disponible' => '#16a34a',
                                    'en_route'   => '#f97316',
                                    'arrivé'     => '#94a3b8'
                                ];
                                $couleur = $couleurs[$row['statut']] ?? '#94a3b8';
                            ?>
                            <span style="background-color:<?= $couleur ?>; padding:4px 12px; border-radius:20px; font-size:13px;">
                                <?= htmlspecialchars($row['statut']) ?>
                            </span>
                            <span class="prix"><?= htmlspecialchars($row['prix_kilo']) ?> MAD/kg</span>
                        </div>
                    </div>

                    <div class="annonce-body">

                        <div class="info-ligne">
                            <span> <i class="fa-solid fa-calendar"></i> Départ :</span>
                            <span> <?= date('d/m/Y', strtotime($row['date_depart'])) ?></span>
                        </div>

                        <div class="info-ligne">
                            <span> <i class="fa-solid fa-calendar"></i> Arrivée :</span>
                            <span><?= date('d/m/Y', strtotime($row['date_arrivee'])) ?></span>
                        </div>

                        <?php if (!empty($row['point_depot'])): ?>
                        <div class="info-ligne">
                            <span> <i class="fa-solid fa-box"></i> Dépôt :</span>
                            <span><?= htmlspecialchars($row['point_depot']) ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($row['point_reception'])): ?>
                        <div class="info-ligne">
                            <span><i class="fa-solid fa-location-dot"></i> Réception :</span>
                            <span><?= htmlspecialchars($row['point_reception']) ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($row['commentaire'])): ?>
                        <div class="info-ligne">
                            <span> <i class="fa-solid fa-comment"></i> Commentaire :</span>
                            <span><?= htmlspecialchars($row['commentaire']) ?></span>
                        </div>
                        <?php endif; ?>

                    </div>

                    <div class="annonce-footer" style="display:flex; gap:10px; flex-wrap:wrap;">
                        <?php if (!empty($row['telephone'])): ?>
                            <a href="tel:<?= htmlspecialchars($row['telephone']) ?>" class="btn-contact">
                                <i class="fa-solid fa-phone"></i>
                                <?= htmlspecialchars($row['telephone']) ?>
                            </a>
                        <?php else: ?>
                            <span class="no-contact"><i class="fa-solid fa-phone"></i> Contact non renseigné</span>
                        <?php endif; ?>

                        <?php if (!empty($user['whatsapp'])): ?>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $user['whatsapp']) ?>" 
                               target="_blank" class="btn-contact" style="background-color:#25d366;">
                                <i class="fa-solid fa-comment"></i> WhatsApp
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endwhile; ?>

        <?php else: ?>
            <p>Ce GP n'a pas encore publié de trajet.</p>
        <?php endif; ?>

    </section>

    <?php include 'footer.php'; ?>

</body>
</html>