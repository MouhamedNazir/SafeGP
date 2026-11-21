<?php
// ============================================================
// RÉSERVER DES KILOS sur un trajet (formulaire de trajet.php)
// ============================================================
require_once 'init.php';
exiger_connexion();

$trajet_id = (int)($_POST['trajet_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$trajet_id) {
    header("Location: search_trajet.php");
    exit();
}
if ($nav_email_a_confirmer) {
    $_SESSION['erreur_reservation'] = "Confirme d'abord ton adresse e-mail pour réserver.";
    header("Location: trajet.php?id=" . $trajet_id . "#reserver");
    exit();
}
$resultat = envoi_reserver($conn, (int)$_SESSION['user_id'], $trajet_id, $_POST);
$conn->close();

if (str_starts_with($resultat, '!')) {
    // Erreur : on revient sur l'annonce avec le message et les valeurs saisies
    $_SESSION['erreur_reservation'] = substr($resultat, 1);
    $_SESSION['saisie_reservation'] = array_intersect_key($_POST, array_flip(['kilos', 'contenu', 'destinataire_nom', 'destinataire_tel']));
    header("Location: trajet.php?id=" . $trajet_id . "#reserver");
    exit();
}
header("Location: envoi.php?code=" . urlencode($resultat) . "&nouveau=1");
exit();
