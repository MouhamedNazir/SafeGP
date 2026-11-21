<?php
include 'config.php';
include 'init.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$success = false;
$erreur = "";
$t = [];
$contacts_valeurs = [];

// Limite freemium : 5 publications par mois pour un compte gratuit
$quota = quota_publication($conn, $user_id);
$limite_atteinte = !$quota['illimite'] && $quota['restantes'] <= 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $t = trajet_depuis_post();
    $contacts_valeurs = contacts_depuis_post($erreur);

    if ($limite_atteinte) {
        $erreur = "Tu as atteint la limite de " . FREEMIUM_LIMITE_MOIS . " publications ce mois-ci avec le compte gratuit.";
    } elseif (!$erreur) {
        $erreur = trajet_valider($t, $contacts_valeurs);
    }

    // Tout est bon → on insère
    if (!$erreur) {
        $stmt = $conn->prepare("INSERT INTO trajets
            (depart, arrivee, date_depart, heure_depart, date_arrivee, heure_arrivee, date_limite_depot,
             prix_kilo, kilos_total, kilos_restants, telephone, commentaire,
             point_depot, horaires_depot, point_reception, horaires_reception,
             pour_autrui, voyageur_nom, user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssdddssssssisi",
            $t['depart'], $t['arrivee'], $t['date_depart'], $t['heure_depart'],
            $t['date_arrivee'], $t['heure_arrivee'], $t['date_limite_depot'],
            $t['prix_kilo'], $t['kilos_total'], $t['kilos_restants'], $t['telephone'], $t['commentaire'],
            $t['point_depot'], $t['horaires_depot'], $t['point_reception'], $t['horaires_reception'],
            $t['pour_autrui'], $t['voyageur_nom'], $user_id);

        if ($stmt->execute()) {
            $trajet_id = $stmt->insert_id;
            if ($t['pour_autrui']) {
                contacts_enregistrer($conn, $user_id, $trajet_id, $contacts_valeurs);
            }
            $success = true;
            $t = [];
            $contacts_valeurs = [];
            $quota = quota_publication($conn, $user_id);
            $limite_atteinte = !$quota['illimite'] && $quota['restantes'] <= 0;
        } else {
            $erreur = "Erreur lors de la publication.";
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Publier un trajet - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

    <section class="publier">
        <h2>Publier un trajet</h2>

        <?php if ($success): ?>
            <p class="success"><i class="fa-solid fa-circle-check" style="color:#16a34a;"></i> Trajet publié avec succès ! <a href="dashboard.php">Voir mes trajets</a></p>
        <?php endif; ?>

        <?php if ($erreur): ?>
            <p class="erreur"><i class="fa-solid fa-circle-xmark" style="color:white;"></i> <?= e($erreur) ?></p>
        <?php endif; ?>

        <!-- Compteur freemium -->
        <?php if ($quota['illimite']): ?>
            <p class="quota quota-premium"><i class="fa-solid fa-crown"></i> Compte premium : publications illimitées</p>
        <?php else: ?>
            <p class="quota <?= $limite_atteinte ? 'quota-atteint' : '' ?>">
                <i class="fa-solid fa-gauge"></i>
                Compte gratuit : <?= $quota['utilisees'] ?> / <?= FREEMIUM_LIMITE_MOIS ?> publications ce mois-ci
                <?php if ($limite_atteinte): ?>
                    <br>Tu pourras publier à nouveau le <?= date('d/m/Y', strtotime('first day of next month')) ?>.
                    Pour publier sans limite, passe au premium : <a href="retours.php?type=autre">contacte-nous</a>.
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if (!$limite_atteinte): ?>
        <form action="publier.php" method="POST">
            <?= csrf_field() ?>
            <?php include 'trajet_champs.php'; ?>
            <button type="submit" class="btn-publier">Publier le trajet</button>
        </form>
        <?php endif; ?>
    </section>
<?php include 'footer.php'; ?>
</body>
</html>