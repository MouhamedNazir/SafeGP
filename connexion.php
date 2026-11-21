<?php
require_once 'init.php';

$erreur = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']); /* j ai ajouté trim pour eviter les erreurs d espace */
    $mot_de_passe = $_POST['mot_de_passe'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($mot_de_passe, $user['mot_de_passe'])) {
        // Nouvel identifiant de session à la connexion (évite le vol de session)
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nom'] = $user['nom'];
        $_SESSION['user_photo'] = $user['photo'];
        header("Location: index.php");
        exit();
    } else {
        $erreur = "Email ou mot de passe incorrect.";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Connexion - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

    <section class="publier">
        <h2>Se connecter</h2>

        <?php if (isset($_GET['mdp']) && $_GET['mdp'] === 'change'): ?>
            <p class="success"><i class="fa-solid fa-circle-check" style="color:var(--vert);"></i> Mot de passe modifié, tu peux te connecter.</p>
        <?php endif; ?>

        <?php if ($erreur): ?>
            <p class="erreur"><i class="fa-solid fa-circle-xmark" style="color:white;"></i> <?= e($erreur) ?></p>
        <?php endif; ?>

        <form action="connexion.php" method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="mot_de_passe" required>
            </div>

            <p style="text-align:right; margin:-10px 0 20px;">
                <a href="mot_de_passe_oublie.php" style="color:var(--texte-doux); font-size:14px;">Mot de passe oublié ?</a>
            </p>

            <button type="submit" class="btn-publier">Se connecter</button>

            <p style="text-align:center; margin-top:20px;">
                Pas de compte ? <a href="inscription.php" style="color:var(--orange);">S'inscrire</a>
            </p>

        </form>
    </section>
<?php include 'footer.php'; ?>
</body>
</html>