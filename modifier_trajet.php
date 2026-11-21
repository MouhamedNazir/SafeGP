<?php
include 'config.php';
include 'init.php';

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

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $depart = trim($_POST['depart']);
    $arrivee = trim($_POST['arrivee']);
    $date_depart = $_POST['date_depart'];
    $date_arrivee = $_POST['date_arrivee'];
    $prix_kilo = $_POST['prix_kilo'];
    $telephone = trim($_POST['telephone']);
    $commentaire = trim($_POST['commentaire']);
    $point_depot = trim($_POST['point_depot']);
    $point_reception = trim($_POST['point_reception']);

    // Validations
    if (!empty($telephone) && !preg_match('/^[\+0-9\s]{8,15}$/', $telephone)) {
        $erreur = "Numéro de téléphone invalide.";
    } elseif ($date_depart < date('Y-m-d')) {
        $erreur = "La date de départ ne peut pas être dans le passé.";
    } elseif ($date_arrivee < $date_depart) {
        $erreur = "La date d'arrivée doit être après la date de départ.";
    } else {
        $stmt = $conn->prepare("UPDATE trajets SET 
            depart = ?, arrivee = ?, date_depart = ?, date_arrivee = ?, 
            prix_kilo = ?, telephone = ?, commentaire = ?, 
            point_depot = ?, point_reception = ?
            WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ssssdssssii", 
            $depart, $arrivee, $date_depart, $date_arrivee,
            $prix_kilo, $telephone, $commentaire,
            $point_depot, $point_reception,
            $id_trajet, $user_id);

        if ($stmt->execute()) {
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
            <p class="erreur"><i class="fa-solid fa-circle-xmark" style="color:white;"></i> <?= $erreur ?></p>
        <?php endif; ?>

        <form action="modifier_trajet.php?id=<?= $id_trajet ?>" method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label>Ville de départ</label>
                <input type="text" name="depart" 
                       value="<?= htmlspecialchars($trajet['depart']) ?>" required>
            </div>

            <div class="form-group">
                <label>Ville d'arrivée</label>
                <input type="text" name="arrivee" 
                       value="<?= htmlspecialchars($trajet['arrivee']) ?>" required>
            </div>

            <div class="form-group">
                <label>Date de départ</label>
                <input type="date" name="date_depart" 
                       min="<?= date('Y-m-d') ?>"
                       value="<?= htmlspecialchars($trajet['date_depart']) ?>" required>
            </div>

            <div class="form-group">
                <label>Date d'arrivée</label>
                <input type="date" name="date_arrivee" 
                       value="<?= htmlspecialchars($trajet['date_arrivee']) ?>" required>
            </div>

            <div class="form-group">
                <label>Prix par kilo (€ ou MAD)</label>
                <input type="number" name="prix_kilo" 
                       step="0.01" min="0"
                       value="<?= htmlspecialchars($trajet['prix_kilo']) ?>" required>
            </div>

            <div class="form-group">
                <label>Numéro de téléphone</label>
                <input type="tel" name="telephone" 
                       value="<?= htmlspecialchars($trajet['telephone'] ?? '') ?>"
                       placeholder="Ex: +212 6 12 34 56 78"
                       pattern="[\+0-9\s]{8,15}">
            </div>

            <div class="form-group">
                <label>Point de dépôt</label>
                <input type="text" name="point_depot" 
                       value="<?= htmlspecialchars($trajet['point_depot'] ?? '') ?>"
                       placeholder="Ex: Gare de Casa-Voyageurs">
            </div>

            <div class="form-group">
                <label>Point de réception</label>
                <input type="text" name="point_reception" 
                       value="<?= htmlspecialchars($trajet['point_reception'] ?? '') ?>"
                       placeholder="Ex: Marché Sandaga, Dakar">
            </div>

            <div class="form-group">
                <label>Commentaire</label>
                <textarea name="commentaire" 
                          placeholder="Ex: Poids max 20kg..."><?= htmlspecialchars($trajet['commentaire'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn-publier"><i class="fa-solid fa-floppy-disk"></i> Sauvegarder les modifications</button>

            <a href="dashboard.php" 
               style="display:block; text-align:center; margin-top:15px; color:#94a3b8;">
                ← Annuler
            </a>

        </form>
    </section>

    <?php include 'footer.php'; ?>

</body>
</html>