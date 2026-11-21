<?php
// ============================================================
// AFFICHER UN FICHIER PRIVÉ du dossier preuves/ (fermé au web)
//   ?type=preuve&id=N → capture du paiement d'un abonnement (le membre, l'admin)
//   ?type=etape&id=N  → photo d'une étape d'un envoi (le GP, l'expéditeur, l'admin)
// ============================================================
require_once 'init.php';
exiger_connexion();

$moi = (int)$_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);
$fichier = null;

if (($_GET['type'] ?? '') === 'preuve') {
    $stmt = $conn->prepare("SELECT preuve, user_id FROM abonnements WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $l = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($l && ($nav_is_admin || (int)$l['user_id'] === $moi)) $fichier = $l['preuve'];
} elseif (($_GET['type'] ?? '') === 'etape') {
    $stmt = $conn->prepare("SELECT et.photo, e.gp_id, e.expediteur_id FROM envois_etapes et JOIN envois e ON e.id = et.envoi_id WHERE et.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $l = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($l && ($nav_is_admin || in_array($moi, [(int)$l['gp_id'], (int)$l['expediteur_id']], true))) $fichier = $l['photo'];
}
$conn->close();

$chemin = $fichier && preg_match('/^[a-f0-9]{32}\.(img|jpg)$/', $fichier) ? __DIR__ . '/preuves/' . $fichier : null;
if (!$chemin || !is_file($chemin)) {
    http_response_code(404);
    exit('Fichier introuvable');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($chemin);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415);
    exit('Format inattendu');
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($chemin));
header('Cache-Control: no-store, private');
readfile($chemin);
