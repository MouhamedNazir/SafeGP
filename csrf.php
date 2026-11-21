<?php
// ============================================================
// PROTECTION CSRF
// Chaque session reçoit un jeton secret. Chaque formulaire POST
// le renvoie dans un champ caché, et on refuse toute requête POST
// dont le jeton ne correspond pas. Un site tiers ne connaît pas
// ce jeton, il ne peut donc pas forger une action à ta place.
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Champ caché à placer dans chaque <form method="POST">
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

// À appeler avant tout traitement : bloque les POST sans jeton valide
function csrf_verify(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $envoye = $_POST['csrf_token'] ?? '';
    if (!is_string($envoye) || !hash_equals(csrf_token(), $envoye)) {
        http_response_code(403);
        exit('Requête invalide (jeton de sécurité manquant ou expiré). Recharge la page et réessaie.');
    }
}
