<?php
include 'config.php';
include 'init.php';

$success = false;
$erreur = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom = trim($_POST['nom']);
    $username = strtolower(trim($_POST['username'])); // on met en minuscule
    $email = trim($_POST['email']);
    $mot_de_passe = $_POST['mot_de_passe'];
    $confirmation = $_POST['confirmation'];

    if ($mot_de_passe !== $confirmation) {
        $erreur = "Les mots de passe ne correspondent pas.";

    } elseif (strlen($username) < 3) {
        $erreur = "Le nom d'utilisateur doit faire au moins 3 caractères.";

    } elseif (!preg_match('/^[a-z0-9_]+$/', $username)) {
        $erreur = "Le nom d'utilisateur ne peut contenir que des lettres, chiffres et _.";

    } else {
        // Vérifier si username déjà pris
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $erreur = "Ce nom d'utilisateur est déjà pris.";
        } else {
            $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (nom, username, email, mot_de_passe) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nom, $username, $email, $hash);

            if ($stmt->execute()) {
                $success = true;
            } else {
                $erreur = "Cet email est déjà utilisé.";
            }

            $stmt->close();
        }

        $check->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Inscription - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

    <section class="publier">
        <h2>Créer un compte</h2>

        <?php if ($success): ?>
            <p class="success"><i class="fa-solid fa-circle-check" style="color:#16a34a;"></i> Compte créé ! <a href="connexion.php">Se connecter</a></p>
        <?php endif; ?>

        <?php if ($erreur): ?>
            <p class="erreur"><i class="fa-solid fa-circle-xmark" style="color:white;"></i> <?= $erreur ?></p>
        <?php endif; ?>

        <form action="inscription.php" method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label>Nom complet</label>
                <input type="text" name="nom" 
                    value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                    placeholder="Ex: Mamadou Ndiaye" required>
            </div>

            <div class="form-group">
                <label>Nom d'utilisateur</label>
                <input type="text" name="username" 
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    placeholder="Ex: mamadou_gp" required>
                <small style="color:#94a3b8;">Lettres, chiffres et _ uniquement. Ex: mamadou_gp</small>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" 
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    placeholder="Ex: mamadou@gmail.com" required>
            </div>

            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="mot_de_passe" 
                    placeholder="Min 6 caractères" required>
            </div>

            <div class="form-group">
                <label>Confirmer le mot de passe</label>
                <input type="password" name="confirmation" 
                    placeholder="Répète ton mot de passe" required>
            </div>

            <button type="submit" class="btn-publier">Créer mon compte</button>

            <p style="text-align:center; margin-top:20px;">
                Déjà un compte ? <a href="connexion.php" style="color:#f97316;">Se connecter</a>
            </p>

        </form>
    </section>

    <?php include 'footer.php'; ?>

</body>
</html>