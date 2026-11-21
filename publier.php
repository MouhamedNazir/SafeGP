<?php
require_once 'init.php';

exiger_connexion();

$user_id = (int)$_SESSION['user_id'];
$success = isset($_GET['publie']);
$erreur = "";
$t = [];
$contacts_valeurs = [];

// Limite de publications selon la formule (Gratuit 4 / Standard 8 / Illimité)
$quota = quota_publication($conn, $user_id);
$limite_atteinte = !$quota['illimite'] && $quota['restantes'] <= 0;

// E-mail à confirmer avant de publier (quand l'envoi d'e-mails est actif)
$email_a_confirmer = $nav_email_a_confirmer;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $t = trajet_depuis_post();
    $contacts_valeurs = contacts_depuis_post($erreur);

    if ($email_a_confirmer) {
        $erreur = "Confirme d'abord ton adresse e-mail pour publier un trajet.";
    } elseif ($limite_atteinte) {
        $erreur = "Tu as atteint la limite de " . $quota['limite'] . " publications ce mois-ci avec la formule " . $quota['nom'] . ".";
    } elseif (!$erreur) {
        $erreur = trajet_valider($t, $contacts_valeurs);
    }

    // Tout est bon → on insère
    if (!$erreur) {
        $stmt = $conn->prepare("INSERT INTO trajets
            (depart, pays_depart, arrivee, pays_arrivee, date_depart, heure_depart, date_arrivee, heure_arrivee, date_limite_depot,
             prix_kilo, devise, kilos_total, kilos_restants, telephone, commentaire,
             point_depot, horaires_depot, point_reception, horaires_reception,
             pour_autrui, voyageur_nom, user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssssdsddssssssisi",
            $t['depart'], $t['pays_depart'], $t['arrivee'], $t['pays_arrivee'], $t['date_depart'], $t['heure_depart'],
            $t['date_arrivee'], $t['heure_arrivee'], $t['date_limite_depot'],
            $t['prix_kilo'], $t['devise'], $t['kilos_total'], $t['kilos_restants'], $t['telephone'], $t['commentaire'],
            $t['point_depot'], $t['horaires_depot'], $t['point_reception'], $t['horaires_reception'],
            $t['pour_autrui'], $t['voyageur_nom'], $user_id);

        if ($stmt->execute()) {
            $trajet_id = $stmt->insert_id;
            // Compté dans la limite du mois, même si le trajet est supprimé ensuite
            $conn->query("INSERT INTO publications (user_id, trajet_id) VALUES (" . (int)$user_id . ", " . (int)$trajet_id . ")");
            if ($t['pour_autrui']) {
                contacts_enregistrer($conn, $user_id, $trajet_id, $contacts_valeurs);
            }
            // Prévenir les membres qui ont une alerte sur ce trajet
            alertes_notifier($conn, $trajet_id);
            // Redirection vers un formulaire vide : un rafraîchissement
            // de la page ne republie pas le trajet une deuxième fois
            $stmt->close();
            $conn->close();
            header("Location: publier.php?publie=" . (int)$trajet_id);
            exit();
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
            <p class="success"><i class="fa-solid fa-circle-check" style="color:var(--vert);"></i> Trajet publié avec succès !
                <?php if ((int)$_GET['publie'] > 0): ?><a href="trajet.php?id=<?= (int)$_GET['publie'] ?>">Voir et partager l'annonce</a> ·<?php endif; ?>
                <a href="dashboard.php">Mes trajets</a></p>
        <?php endif; ?>

        <?php if ($erreur): ?>
            <p class="erreur"><i class="fa-solid fa-circle-xmark" style="color:white;"></i> <?= e($erreur) ?></p>
        <?php endif; ?>

        <!-- Formule et publications du mois -->
        <?php include 'quota_bandeau.php'; ?>

        <?php if ($email_a_confirmer): ?>
            <p class="quota quota-atteint"><i class="fa-solid fa-envelope"></i> Confirme ton adresse e-mail (lien reçu à l'inscription) pour pouvoir publier. Tu peux renvoyer l'e-mail depuis le bandeau en haut de la page.</p>
        <?php elseif (!$limite_atteinte): ?>
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