<?php
require_once 'init.php';

$erreur = "";

// Anti force brute : 5 échecs en 15 minutes depuis une même adresse IP
// (pour un même e-mail, ou 20 tous e-mails confondus) → blocage 15 minutes
const ESSAIS_MAX = 5;
const ESSAIS_MAX_IP = 20;
$ip = substr($_SERVER['REMOTE_ADDR'] ?? 'inconnue', 0, 45);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';

    // Ménage : on oublie les échecs de plus de 15 minutes
    $conn->query("DELETE FROM connexions_echouees WHERE created_at < NOW() - INTERVAL 15 MINUTE");
    $stmt = $conn->prepare("SELECT SUM(email = ?) AS pour_email, COUNT(*) AS pour_ip FROM connexions_echouees WHERE ip = ?");
    $stmt->bind_param("ss", $email, $ip);
    $stmt->execute();
    $essais = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int)$essais['pour_email'] >= ESSAIS_MAX || (int)$essais['pour_ip'] >= ESSAIS_MAX_IP) {
        $erreur = "Trop de tentatives. Réessaie dans 15 minutes, ou utilise « Mot de passe oublié ».";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($mot_de_passe, $user['mot_de_passe'])) {
            // Connexion réussie : on efface les échecs et on renouvelle la session
            $stmt = $conn->prepare("DELETE FROM connexions_echouees WHERE ip = ? AND email = ?");
            $stmt->bind_param("ss", $ip, $email);
            $stmt->execute();
            $stmt->close();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nom'] = $user['nom'];
            $_SESSION['user_photo'] = $user['photo'];

            // Retour à la page demandée avant la connexion (si elle est sur ce site)
            $suite = $_POST['suite'] ?? '';
            $suite = preg_match('/^[a-z_]+\.php(\?[^\s]*)?$/i', $suite) ? $suite : 'index.php';
            header("Location: " . $suite);
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO connexions_echouees (ip, email) VALUES (?, ?)");
        $stmt->bind_param("ss", $ip, $email);
        $stmt->execute();
        $stmt->close();
        $erreur = "Email ou mot de passe incorrect.";
    }
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
            <input type="hidden" name="suite" value="<?= e($_GET['suite'] ?? $_POST['suite'] ?? '') ?>">

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email">
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