<?php
include 'config.php';
include 'navbar.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$success = false;
$erreur = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $depart = trim($_POST['depart']);
    $arrivee = trim($_POST['arrivee']);
    $date_depart = $_POST['date_depart'];
    $date_arrivee = $_POST['date_arrivee'];
    $prix_kilo = $_POST['prix_kilo'];
    $telephone = trim($_POST['telephone']);
    $commentaire = trim($_POST['commentaire']);
    $point_depot = trim($_POST['point_depot']);
    $point_reception = trim($_POST['point_reception']);

    // Validation téléphone
    if (!empty($telephone) && !preg_match('/^[\+0-9\s]{8,15}$/', $telephone)) {
        $erreur = "Numéro de téléphone invalide.";

    // Validation date passée
    } elseif ($date_depart < date('Y-m-d')) {
        $erreur = "La date de départ ne peut pas être dans le passé.";

    // Validation dates cohérentes
    } elseif ($date_arrivee < $date_depart) {
        $erreur = "La date d'arrivée doit être après la date de départ.";

    // Tout est bon → on insère
    } else {
        $stmt = $conn->prepare("INSERT INTO trajets 
            (depart, arrivee, date_depart, date_arrivee, prix_kilo, telephone, commentaire, point_depot, point_reception, user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdssssi", $depart, $arrivee, $date_depart, $date_arrivee, $prix_kilo, $telephone, $commentaire, $point_depot, $point_reception, $user_id);

        if ($stmt->execute()) {
            $success = true;
        } else {
            $erreur = "Erreur : " . $stmt->error;
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Publier un trajet - SafeGP</title>
</head>
<body>

    <section class="publier">
        <h2>Publier un trajet</h2>

        <?php if ($success): ?>
            <p class="success"><i class="fa-solid fa-circle-check" style="color:#16a34a;"></i> Trajet publié avec succès !</p>
        <?php endif; ?>

        <?php if ($erreur): ?>
            <p class="erreur"><i class="fa-solid fa-circle-xmark" style="color:white;"></i> <?= $erreur ?></p>
        <?php endif; ?>

        <form action="publier.php" method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label>Ville de départ</label>
                <input type="text" name="depart" 
                    value="<?= htmlspecialchars($_POST['depart'] ?? '') ?>" 
                    placeholder="Ex: Casablanca" required>
            </div>

            <div class="form-group">
                <label>Ville d'arrivée</label>
                <input type="text" name="arrivee" 
                    value="<?= htmlspecialchars($_POST['arrivee'] ?? '') ?>" 
                    placeholder="Ex: Dakar" required>
            </div>

            <div class="form-group">
                <label>Date de départ</label>
                <input type="date" name="date_depart" 
                    min="<?= date('Y-m-d') ?>"
                    value="<?= htmlspecialchars($_POST['date_depart'] ?? '') ?>" 
                    required>
            </div>

            <div class="form-group">
                <label>Date d'arrivée</label>
                <input type="date" name="date_arrivee" 
                    value="<?= htmlspecialchars($_POST['date_arrivee'] ?? '') ?>" 
                    required>
            </div>

            <div class="form-group">
                <label>Prix par kilo (€ ou MAD)</label>
                <input type="number" name="prix_kilo" 
                    step="0.01" min="0" 
                    value="<?= htmlspecialchars($_POST['prix_kilo'] ?? '') ?>" 
                    placeholder="Ex: 15" required>
            </div>

            <div class="form-group">
                <label>Numéro de téléphone</label>
                <input type="tel" name="telephone" 
                    value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>" 
                    placeholder="Ex: +212 6 12 34 56 78" 
                    pattern="[\+0-9\s]{8,15}">
            </div>

            <div class="form-group">
                <label>Point de dépôt</label>
                <input type="text" name="point_depot" 
                    value="<?= htmlspecialchars($_POST['point_depot'] ?? '') ?>" 
                    placeholder="Ex: Gare de Casa-Voyageurs">
            </div>

            <div class="form-group">
                <label>Point de réception</label>
                <input type="text" name="point_reception" 
                    value="<?= htmlspecialchars($_POST['point_reception'] ?? '') ?>" 
                    placeholder="Ex: Marché Sandaga, Dakar">
            </div>

            <div class="form-group">
                <label>Commentaire</label>
                <textarea name="commentaire" 
                    placeholder="Ex: Poids max 20kg, objets fragiles acceptés..."><?= htmlspecialchars($_POST['commentaire'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn-publier">Publier le trajet</button>

        </form>
    </section>
<?php include 'footer.php'; ?>
</body>
</html>