<?php
require_once 'init.php';

$depart = trim($_GET['depart'] ?? '');
$arrivee = trim($_GET['arrivee'] ?? '');

$stmt = $conn->prepare("
    SELECT trajets.*, users.nom, users.photo, users.username, users.disponible
    FROM trajets
    LEFT JOIN users ON trajets.user_id = users.id
    WHERE trajets.depart LIKE ? AND trajets.arrivee LIKE ?
");

$d = "%$depart%";
$a = "%$arrivee%";
$stmt->bind_param("ss", $d, $a);
$stmt->execute();
$trajets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Numéros WhatsApp à afficher pour chaque trajet
$contacts = contacts_pour_trajets($conn, $trajets);
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Résultats - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

    <section class="resultats">
        <h2>Trajets : <?= e($depart) ?> → <?= e($arrivee) ?></h2>

        <?php if ($trajets): ?>

            <?php foreach ($trajets as $row): ?>
                <div class="annonce">
                    <?php $avec_gp = true; include 'trajet_infos.php'; ?>

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
            <div class="aucun-resultat">
                <p><i class="fa-solid fa-face-frown"></i> Aucun trajet trouvé pour cette destination.</p>
                <a href="index.php">← Retour à l'accueil</a>
            </div>
        <?php endif; ?>

    </section>

    <?php include 'footer.php'; ?>

</body>
</html>
