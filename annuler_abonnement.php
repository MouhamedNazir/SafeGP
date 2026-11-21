<?php
// ============================================================
// ANNULER UN ABONNEMENT (sous 48 h, si aucun trajet publié depuis)
// ============================================================
require_once 'init.php';
exiger_connexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: mon_profil.php#abonnement");
    exit();
}
$erreur = abonnement_annuler($conn, (int)($_POST['abonnement_id'] ?? 0), (int)$_SESSION['user_id']);
$conn->close();
// Message affiché une seule fois sur « Mon profil » (passé par la session, pas par l'URL)
$_SESSION['message_annulation'] = $erreur ?: 'ok';
header("Location: mon_profil.php#abonnement");
exit();
