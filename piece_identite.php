<?php
// ============================================================
// AFFICHER UNE PIÈCE D'IDENTITÉ — réservé à l'admin
// Le fichier est lu depuis verifications/ (fermé au web) et n'est
// jamais mis en cache par le navigateur.
// ============================================================
require_once 'init.php';
exiger_connexion();
if (!$nav_is_admin) {
    http_response_code(403);
    exit('Accès refusé');
}
$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT fichier FROM verifications WHERE id = ? AND statut = 'en_attente'");
$stmt->bind_param("i", $id);
$stmt->execute();
$fichier = $stmt->get_result()->fetch_row()[0] ?? null;
$stmt->close();
$conn->close();

$chemin = $fichier && preg_match('/^[a-f0-9]{32}\.(img|jpg)$/', $fichier) ? __DIR__ . '/verifications/' . $fichier : null;
if (!$chemin || !is_file($chemin)) {
    http_response_code(404);
    exit('Fichier introuvable (déjà traité ?)');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($chemin);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415);
    exit('Format inattendu');
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($chemin));
header('Cache-Control: no-store, private');
header('Content-Disposition: inline; filename="piece-' . $id . '"');
readfile($chemin);
