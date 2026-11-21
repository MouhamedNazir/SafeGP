<?php
// ============================================================
// CONFIRMER UNE NOUVELLE ADRESSE E-MAIL (lien reçu à cette adresse)
// ============================================================
require_once 'init.php';

$user_id = jeton_verifier($conn, $_GET['jeton'] ?? '', 'changement_email');
$ok = false;
if ($user_id) {
    $stmt = $conn->prepare("SELECT email_nouveau FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $nouvel_email = $stmt->get_result()->fetch_row()[0] ?? null;
    $stmt->close();

    // L'adresse a pu être prise entre-temps par un autre compte
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
    $check->bind_param("si", $nouvel_email, $user_id);
    $check->execute();
    $prise = (bool)$check->get_result()->fetch_row();
    $check->close();

    if ($nouvel_email && !$prise) {
        $stmt = $conn->prepare("UPDATE users SET email = email_nouveau, email_nouveau = NULL, email_verifie = 1 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        $ok = true;
    }
    jeton_supprimer($conn, $user_id, 'changement_email');
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Nouvelle adresse e-mail - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>
    <section class="publier" style="text-align:center;">
        <?php if ($ok): ?>
            <p class="success"><i class="fa-solid fa-circle-check"></i> Ta nouvelle adresse e-mail est enregistrée. Utilise-la pour te connecter.</p>
        <?php else: ?>
            <p class="erreur"><i class="fa-solid fa-circle-xmark"></i> Ce lien n'est plus valide (déjà utilisé, expiré, ou adresse déjà prise).</p>
        <?php endif; ?>
        <a href="mon_profil.php" class="btn-contact">Mon profil</a>
    </section>
<?php include 'footer.php'; ?>
</body>
</html>
