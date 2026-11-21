<?php
// ============================================================
// CHOISIR UN NOUVEAU MOT DE PASSE (lien reçu par e-mail)
// ============================================================
require_once 'init.php';

$jeton = $_GET['jeton'] ?? '';
$user_id = jeton_verifier($conn, $jeton, 'reinitialisation');
$erreur = "";

if ($user_id && $_SERVER["REQUEST_METHOD"] == "POST") {
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    if (strlen($mot_de_passe) < 8) {
        $erreur = "Le mot de passe doit faire au moins 8 caractères.";
    } elseif ($mot_de_passe !== $confirmation) {
        $erreur = "Les mots de passe ne correspondent pas.";
    } else {
        $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
        // Le lien a été reçu sur cette adresse : elle est donc confirmée
        $stmt = $conn->prepare("UPDATE users SET mot_de_passe = ?, email_verifie = 1 WHERE id = ?");
        $stmt->bind_param("si", $hash, $user_id);
        $stmt->execute();
        $stmt->close();
        jeton_supprimer($conn, $user_id, 'reinitialisation');
        $conn->close();
        header("Location: connexion.php?mdp=change");
        exit();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Nouveau mot de passe - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>
    <section class="publier">
        <h2>Nouveau mot de passe</h2>

        <?php if (!$user_id): ?>
            <p class="erreur"><i class="fa-solid fa-circle-xmark" style="color:white;"></i> Ce lien n'est plus valide (déjà utilisé ou expiré).</p>
            <p style="text-align:center;"><a href="mot_de_passe_oublie.php" style="color:#f97316;">Demander un nouveau lien</a></p>
        <?php else: ?>
            <?php if ($erreur): ?>
                <p class="erreur"><i class="fa-solid fa-circle-xmark" style="color:white;"></i> <?= e($erreur) ?></p>
            <?php endif; ?>
            <form action="reinitialiser.php?jeton=<?= e($jeton) ?>" method="POST">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label>Nouveau mot de passe</label>
                    <input type="password" name="mot_de_passe" minlength="8" placeholder="Au moins 8 caractères" required>
                </div>
                <div class="form-group">
                    <label>Confirmer le mot de passe</label>
                    <input type="password" name="confirmation" minlength="8" required>
                </div>
                <button type="submit" class="btn-publier">Enregistrer</button>
            </form>
        <?php endif; ?>
    </section>
<?php include 'footer.php'; ?>
</body>
</html>
