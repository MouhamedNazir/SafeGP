<?php
require_once 'init.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$erreur = "";

// Récupérer le trajet — vérifier qu'il appartient bien à cet utilisateur
$id_trajet = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM trajets WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id_trajet, $user_id);
$stmt->execute();
$trajet = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Si le trajet n'existe pas ou n'appartient pas à cet user → redirection
if (!$trajet) {
    header("Location: dashboard.php");
    exit();
}

// Valeurs affichées : celles du trajet, ou celles envoyées si erreur
$t = $trajet;
$contacts_valeurs = $trajet['pour_autrui'] ? contacts_trajet($conn, $id_trajet) : [];
$avec_kilos_restants = true;

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $t = trajet_depuis_post();
    $contacts_valeurs = contacts_depuis_post($erreur);
    if (!$erreur) {
        // Un trajet déjà commencé peut garder sa date de départ passée
        if ($t['date_depart'] < date('Y-m-d') && $t['date_depart'] === $trajet['date_depart']) {
            $date_reelle = $t['date_depart'];
            $t['date_depart'] = date('Y-m-d');
            $erreur = trajet_valider($t, $contacts_valeurs);
            $t['date_depart'] = $date_reelle;
        } else {
            $erreur = trajet_valider($t, $contacts_valeurs);
        }
    }

    if (!$erreur) {
        $stmt = $conn->prepare("UPDATE trajets SET
            depart = ?, pays_depart = ?, arrivee = ?, pays_arrivee = ?, date_depart = ?, heure_depart = ?,
            date_arrivee = ?, heure_arrivee = ?, date_limite_depot = ?,
            prix_kilo = ?, devise = ?, kilos_total = ?, kilos_restants = ?, telephone = ?, commentaire = ?,
            point_depot = ?, horaires_depot = ?, point_reception = ?, horaires_reception = ?,
            pour_autrui = ?, voyageur_nom = ?
            WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sssssssssdsddssssssisii",
            $t['depart'], $t['pays_depart'], $t['arrivee'], $t['pays_arrivee'], $t['date_depart'], $t['heure_depart'],
            $t['date_arrivee'], $t['heure_arrivee'], $t['date_limite_depot'],
            $t['prix_kilo'], $t['devise'], $t['kilos_total'], $t['kilos_restants'], $t['telephone'], $t['commentaire'],
            $t['point_depot'], $t['horaires_depot'], $t['point_reception'], $t['horaires_reception'],
            $t['pour_autrui'], $t['voyageur_nom'],
            $id_trajet, $user_id);

        if ($stmt->execute()) {
            // Numéros propres au trajet : gardés seulement s'il est publié pour autrui
            contacts_enregistrer($conn, $user_id, $id_trajet, $t['pour_autrui'] ? $contacts_valeurs : []);
            header("Location: dashboard.php?success=modifie");
            exit();
        } else {
            $erreur = "Erreur lors de la modification.";
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
    <title>Modifier le trajet - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

    <section class="publier">
        <h2>Modifier le trajet</h2>

        <?php if ($erreur): ?>
            <p class="erreur"><i class="fa-solid fa-circle-xmark" style="color:white;"></i> <?= e($erreur) ?></p>
        <?php endif; ?>

        <form action="modifier_trajet.php?id=<?= $id_trajet ?>" method="POST">
            <?= csrf_field() ?>
            <?php include 'trajet_champs.php'; ?>

            <button type="submit" class="btn-publier"><i class="fa-solid fa-floppy-disk"></i> Sauvegarder les modifications</button>

            <a href="dashboard.php" 
               style="display:block; text-align:center; margin-top:15px; color:var(--texte-doux);">
                ← Annuler
            </a>

        </form>
    </section>

    <?php include 'footer.php'; ?>

</body>
</html>