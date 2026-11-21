<?php
require_once 'init.php';

$success = false;
$email_envoye = false;
$erreur = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom = trim($_POST['nom']);
    $username = strtolower(trim($_POST['username'])); // on met en minuscule
    $email = trim($_POST['email']);
    $mot_de_passe = $_POST['mot_de_passe'];
    $confirmation = $_POST['confirmation'];

    if ($nom === '' || mb_strlen($nom) > 100) {
        $erreur = "Indique ton nom complet.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Adresse e-mail invalide.";

    } elseif (strlen($mot_de_passe) < 8) {
        $erreur = "Le mot de passe doit faire au moins 8 caractères.";

    } elseif ($mot_de_passe !== $confirmation) {
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

        // E-mail déjà inscrit ? (vérifié avant l'insertion : sinon MySQL
        // lève une erreur de doublon qui interromprait la page)
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->store_result();
        $email_pris = $check_email->num_rows > 0;
        $check_email->close();

        if ($check->num_rows > 0) {
            $erreur = "Ce nom d'utilisateur est déjà pris.";
        } elseif ($email_pris) {
            $erreur = "Cet email est déjà utilisé.";
        } else {
            // password_hash() = bcrypt, avec un sel aléatoire par mot de passe
            $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (nom, username, email, mot_de_passe) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nom, $username, $email, $hash);

            if ($stmt->execute()) {
                $success = true;
                // E-mail de confirmation (si l'envoi d'e-mails est configuré)
                $email_envoye = email_actif() && email_envoyer_confirmation($conn, $stmt->insert_id, $email, $nom);
            } else {
                $erreur = "Erreur lors de l'inscription, réessaie.";
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
            <p class="success"><i class="fa-solid fa-circle-check" style="color:var(--vert);"></i> Compte créé ! <a href="connexion.php">Se connecter</a></p>
            <?php if ($email_envoye): ?>
                <p class="quota"><i class="fa-solid fa-envelope"></i> Un e-mail de confirmation vient d'être envoyé à <strong><?= e($email) ?></strong>.
                Clique sur le lien qu'il contient pour pouvoir publier des trajets.
                <?php if (!smtp_configure()): ?><br><small>(Mode local : l'e-mail est enregistré dans <code>logs/emails/</code>.)</small><?php endif; ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($erreur): ?>
            <p class="erreur"><i class="fa-solid fa-circle-xmark" style="color:white;"></i> <?= e($erreur) ?></p>
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
                <small style="color:var(--texte-doux);">Lettres, chiffres et _ uniquement. Ex: mamadou_gp</small>
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
                    placeholder="Au moins 8 caractères" minlength="8" required>
            </div>

            <div class="form-group">
                <label>Confirmer le mot de passe</label>
                <input type="password" name="confirmation" 
                    placeholder="Répète ton mot de passe" required>
            </div>

            <button type="submit" class="btn-publier">Créer mon compte</button>

            <p style="text-align:center; margin-top:20px;">
                Déjà un compte ? <a href="connexion.php" style="color:var(--orange);">Se connecter</a>
            </p>

        </form>
    </section>

    <?php include 'footer.php'; ?>

</body>
</html>