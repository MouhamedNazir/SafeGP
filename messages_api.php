<?php
// ============================================================
// API DE LA MESSAGERIE (appelée en JavaScript par messagerie.php)
//   GET  ?action=nouveaux&with=ID&apres=DERNIER_ID → nouveaux messages
//   POST action=envoyer, destinataire_id, contenu, trajet_id, csrf_token
// Réponses en JSON. Le jeton CSRF est vérifié par init.php.
// ============================================================
require_once 'init.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function repondre(array $donnees, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    repondre(['erreur' => t('Non connecté')], 401);
}
$moi = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'nouveaux') {
    $autre = (int)($_GET['with'] ?? 0);
    $apres = (int)($_GET['apres'] ?? 0);
    messages_marquer_lus($conn, $moi, $autre);
    repondre([
        'messages'  => messages_conversation($conn, $moi, $autre, $apres),
        'vu_jusqua' => message_vu_jusqua($conn, $moi, $autre),
    ]);
}

if ($action === 'envoyer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $destinataire = (int)($_POST['destinataire_id'] ?? 0);
    $trajet_id = (int)($_POST['trajet_id'] ?? 0) ?: null;
    $resultat = message_envoyer($conn, $moi, $_SESSION['user_nom'] ?? 'Un membre', $destinataire, (string)($_POST['contenu'] ?? ''), $trajet_id);
    if (is_string($resultat)) {
        repondre(['erreur' => t($resultat)], 400);
    }
    repondre(['ok' => true, 'id' => $resultat]);
}

repondre(['erreur' => t('Action inconnue')], 400);
