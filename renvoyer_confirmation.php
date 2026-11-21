<?php
// ============================================================
// RENVOYER L'E-MAIL DE CONFIRMATION (bouton du bandeau)
// ============================================================
require_once 'init.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

// Un envoi maximum par minute
if (isset($_SESSION['dernier_envoi_confirmation']) && time() - $_SESSION['dernier_envoi_confirmation'] < 60) {
    header("Location: index.php?confirmation=attendre");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT nom, email, email_verifie FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

$etat = 'erreur';
if ($u && $u['email_verifie']) {
    $etat = 'deja';
} elseif ($u && email_envoyer_confirmation($conn, $user_id, $u['email'], $u['nom'])) {
    $etat = 'envoye';
    $_SESSION['dernier_envoi_confirmation'] = time();
}
$conn->close();
header("Location: index.php?confirmation=" . $etat);
exit();
