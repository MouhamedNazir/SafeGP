<?php
// ============================================================
// MOT DE PASSE OUBLIÉ — envoie un lien de réinitialisation
// Le message affiché est le même que l'adresse existe ou non :
// on ne révèle pas qui est inscrit sur le site.
// ============================================================
require_once 'init.php';

$envoye = false;
$erreur = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');

    if (!email_actif()) {
        $erreur = "L'envoi d'e-mails n'est pas encore disponible. Contacte-nous depuis la page « Donner mon avis ».";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Adresse e-mail invalide.";
    } elseif (isset($_SESSION['dernier_mdp_oublie']) && time() - $_SESSION['dernier_mdp_oublie'] < 60) {
        $erreur = "Patiente une minute avant de redemander un lien.";
    } else {
        $stmt = $conn->prepare("SELECT id, nom, email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($u) {
            email_envoyer_reinitialisation($conn, (int)$u['id'], $u['email'], $u['nom']);
        }
        $_SESSION['dernier_mdp_oublie'] = time();
        $envoye = true;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Mot de passe oublié - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>
    <section class="publier">
        <h2>Mot de passe oublié</h2>

        <?php if ($envoye): ?>
            <p class="success"><i class="fa-solid fa-envelope" style="color:#16a34a;"></i>
                Si un compte existe avec cette adresse, un e-mail avec un lien de réinitialisation vient d'être envoyé.
                Pense à regarder dans les spams. Le lien est valable 1 heure.</p>
            <?php if (!smtp_configure()): ?><p style="text-align:center; color:#94a3b8;"><small>(Mode local : l'e-mail est enregistré dans <code>logs/emails/</code>.)</small></p><?php endif; ?>
        <?php else: ?>
            <?php if ($erreur): ?>
                <p class="erreur"><i class="fa-solid fa-circle-xmark" style="color:white;"></i> <?= e($erreur) ?></p>
            <?php endif; ?>
            <p style="text-align:center; color:#94a3b8; margin-bottom:25px;">Indique l'adresse e-mail de ton compte : on t'envoie un lien pour choisir un nouveau mot de passe.</p>
            <form action="mot_de_passe_oublie.php" method="POST">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
                </div>
                <button type="submit" class="btn-publier">Recevoir le lien</button>
            </form>
        <?php endif; ?>
        <p style="text-align:center; margin-top:20px;"><a href="connexion.php" style="color:#f97316;">← Retour à la connexion</a></p>
    </section>
<?php include 'footer.php'; ?>
</body>
</html>
