<?php
// ============================================================
// CONFIRMATION DE L'ADRESSE E-MAIL (lien reçu par e-mail)
// ============================================================
require_once 'init.php';

$jeton = $_GET['jeton'] ?? '';
$user_id = jeton_verifier($conn, $jeton, 'verification');

if ($user_id) {
    $stmt = $conn->prepare("UPDATE users SET email_verifie = 1 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    jeton_supprimer($conn, $user_id, 'verification');
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Confirmation de l'e-mail - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>
    <section class="publier" style="text-align:center;">
        <?php if ($user_id): ?>
            <p class="success"><i class="fa-solid fa-circle-check" style="color:var(--vert);"></i> Ton adresse e-mail est confirmée, merci !</p>
            <a href="<?= isset($_SESSION['user_id']) ? 'publier.php' : 'connexion.php' ?>" class="btn-contact">
                <?= isset($_SESSION['user_id']) ? 'Publier un trajet' : 'Se connecter' ?>
            </a>
        <?php else: ?>
            <p class="erreur"><i class="fa-solid fa-circle-xmark" style="color:white;"></i> Ce lien n'est plus valide (déjà utilisé ou expiré).</p>
            <p>Connecte-toi : un bandeau te proposera de recevoir un nouveau lien.</p>
        <?php endif; ?>
    </section>
<?php include 'footer.php'; ?>
</body>
</html>
