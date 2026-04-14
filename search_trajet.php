<?php
include 'config.php';
include 'navbar.php';

$depart = trim($_GET['depart'] ?? '');
$arrivee = trim($_GET['arrivee'] ?? '');

$stmt = $conn->prepare("
    SELECT trajets.*, users.nom, users.photo, users.username, users.whatsapp
    FROM trajets 
    LEFT JOIN users ON trajets.user_id = users.id
    WHERE trajets.depart LIKE ? AND trajets.arrivee LIKE ?
");

$d = "%$depart%";
$a = "%$arrivee%";
$stmt->bind_param("ss", $d, $a);
$stmt->execute();
$result = $stmt->get_result();

/* pour debug
echo "<pre>";
echo "Depart reçu : " . $depart . "<br>";
echo "Arrivee reçue : " . $arrivee . "<br>";
echo "Nombre de résultats : " . $result->num_rows . "<br>";
echo "</pre>";
*/
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Résultats - SafeGP</title>
</head>
<body>

    <section class="resultats">
        <h2>Trajets : <?= htmlspecialchars($depart) ?> → <?= htmlspecialchars($arrivee) ?></h2>

        <?php if ($result->num_rows > 0): ?>

            <?php while($row = $result->fetch_assoc()): ?>
                <div class="annonce">

                    <div class="annonce-header">
                        <span class="trajet-titre">
                            <i class="fa-solid fa-plane-departure"></i> <?= htmlspecialchars($row['depart']) ?>
                            →
                            <i class="fa-solid fa-plane-arrival"></i> <?= htmlspecialchars($row['arrivee']) ?>
                        </span>

                        <div style="display:flex; gap:10px; align-items:center;">
                            <?php
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

                    <!-- Nom du GP — affiché seulement si le trajet est lié à un compte -->
                    <?php if (!empty($row['nom'])): ?>
                    <div class="gp-nom">
                        <?php if (!empty($row['photo'])): ?>
                            <img src="uploads/<?= htmlspecialchars($row['photo']) ?>" 
                                 style="width:40px; height:40px; border-radius:50%; object-fit:cover; vertical-align:middle;">
                        <?php else: ?>
                            👤
                        <?php endif; ?>
                        <a href="profil.php?id=<?= $row['user_id'] ?>">
                            @<?= htmlspecialchars($row['username'] ?? $row['nom']) ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <div class="annonce-body">

                        <div class="info-ligne">
                            <span><i class="fa-solid fa-calendar"></i> Départ :</span>
                            <span><?= date('d/m/Y', strtotime($row['date_depart'])) ?></span>
                        </div>

                        <div class="info-ligne">
                            <span><i class="fa-solid fa-calendar"></i> Arrivée :</span>
                            <span><?= date('d/m/Y', strtotime($row['date_arrivee'])) ?></span>
                        </div>

                        <?php if (!empty($row['point_depot'])): ?>
                        <div class="info-ligne">
                            <span><i class="fa-solid fa-box"></i> Dépôt :</span>
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
                            <span><i class="fa-solid fa-comment"></i> Commentaire :</span>
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

                        <?php if (!empty($row['whatsapp'])): ?>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $row['whatsapp']) ?>" 
                               target="_blank" class="btn-contact" style="background-color:#25d366;">
                               <i class="fa-solid fa-comment"></i>
                                WhatsApp
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endwhile; ?>

        <?php else: ?>
            <div class="aucun-resultat">
                <p><i class="fa-solid fa-face-frown"></i> Aucun trajet trouvé pour cette destination.</p>
                <a href="index.php">← Retour à l'accueil</a>
            </div>
        <?php endif; ?>

    </section>

    <?php include 'footer.php'; ?>

</body>
</html>

<!--
fetch_assoc() → transforme chaque ligne de la DB en tableau PHP
htmlspecialchars() → protège contre les failles XSS
!empty() → affiche le commentaire seulement s'il existe
href="tel:" → sur mobile, ça ouvre directement le téléphone !
-->