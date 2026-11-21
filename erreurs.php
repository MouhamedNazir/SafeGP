<?php
// ============================================================
// GESTION DES ERREURS ET DE HTTPS — chargé en tout premier par init.php
//
// En local (localhost) : les erreurs PHP s'affichent, pour développer.
// En ligne : rien n'est affiché au visiteur. L'erreur est écrite dans
// logs/erreurs.log et le visiteur voit une page « Oups » propre.
// ============================================================

// « Local » = l'adresse demandée est locale ET la requête vient vraiment
// de cet ordinateur (REMOTE_ADDR). L'en-tête Host seul pouvant être
// falsifié par un visiteur, il ne suffit jamais.
function est_en_local(): bool
{
    if (PHP_SAPI === 'cli') return true;
    $boucle = ['127.0.0.1', '::1', 'localhost', '[::1]'];
    $hote = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
    if (str_starts_with($_SERVER['HTTP_HOST'] ?? '', '[::1]')) $hote = '[::1]';
    return in_array($hote, $boucle, true) && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}

function est_en_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? '') == 443
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

$mode_debug = est_en_local() || (defined('MODE_DEBUG') && MODE_DEBUG);

error_reporting(E_ALL);
ini_set('display_errors', $mode_debug ? '1' : '0');
ini_set('log_errors', '1');
if (is_dir(__DIR__ . '/logs') && is_writable(__DIR__ . '/logs')) {
    ini_set('error_log', __DIR__ . '/logs/erreurs.log');
}

// Erreur grave (base de données injoignable, bug...) → page propre
set_exception_handler(function (Throwable $ex) use ($mode_debug) {
    error_log('[SafeGP] ' . get_class($ex) . ' : ' . $ex->getMessage()
        . ' dans ' . $ex->getFile() . ':' . $ex->getLine());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    if ($mode_debug) {
        echo '<pre style="white-space:pre-wrap">' . htmlspecialchars((string)$ex) . '</pre>';
        return;
    }
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
       . '<title>Oups - SafeGP</title><link rel="stylesheet" href="style.css"></head><body>'
       . '<section class="aucun-resultat" style="padding:80px 20px;">'
       . '<p><i class="fa-solid fa-triangle-exclamation"></i> Oups, un problème technique est survenu.</p>'
       . '<p>Réessaie dans quelques instants.</p><a href="index.php">← Retour à l\'accueil</a>'
       . '</section></body></html>';
});

// ============================================================
// HTTPS OBLIGATOIRE
// Activé avec define('FORCER_HTTPS', true); dans config.php,
// une fois le certificat SSL installé chez l'hébergeur
// (sinon le site deviendrait inaccessible).
// ============================================================
function forcer_https_si_demande(): void
{
    if (!defined('FORCER_HTTPS') || !FORCER_HTTPS || est_en_local() || est_en_https()) {
        return;
    }
    $url = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: ' . $url, true, 301);
    exit();
}
