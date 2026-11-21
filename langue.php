<?php
// ============================================================
// LANGUE DU SITE (français / anglais)
//
// Le site est écrit en français. En anglais, la page HTML produite
// est traduite juste avant l'envoi au navigateur : chaque texte
// d'interface est remplacé par sa traduction (lang/en.php).
// Seuls les textes qui correspondent EXACTEMENT à une entrée du
// dictionnaire (ou à un motif) sont traduits : les contenus des
// membres (noms, messages, commentaires) ne sont jamais modifiés.
//
// Choix de la langue : bouton FR / EN (?lang=en), mémorisé dans un
// cookie ; à la première visite, langue préférée du navigateur.
// ============================================================

const LANGUES = ['fr', 'en'];

function langue_detectee(): string
{
    if (isset($_GET['lang']) && in_array($_GET['lang'], LANGUES, true)) {
        if (!headers_sent()) {
            setcookie('langue', $_GET['lang'], [
                'expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax',
                'secure' => function_exists('est_en_https') && est_en_https(), 'httponly' => true,
            ]);
        }
        return $_GET['lang'];
    }
    if (isset($_COOKIE['langue']) && in_array($_COOKIE['langue'], LANGUES, true)) {
        return $_COOKIE['langue'];
    }
    // Première visite : la langue préférée du navigateur (français sinon anglais)
    $prefere = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'fr', 0, 2));
    return $prefere === 'fr' || $prefere === '' ? 'fr' : 'en';
}

define('LANGUE', PHP_SAPI === 'cli' ? 'fr' : langue_detectee());

// Dictionnaire chargé une seule fois
function dictionnaire(string $langue): array
{
    static $cache = [];
    if (!isset($cache[$langue])) {
        $fichier = __DIR__ . '/lang/' . $langue . '.php';
        $cache[$langue] = ($langue !== 'fr' && is_file($fichier)) ? require $fichier : ['textes' => [], 'motifs' => [], 'js' => []];
    }
    return $cache[$langue];
}

// Traduit un texte isolé (utilisé dans le code : JSON, dates...)
function t(string $texte, ?string $langue = null, bool $email = false): string
{
    $langue = $langue ?? LANGUE;
    if ($langue === 'fr') return $texte;
    $dico = dictionnaire($langue);
    if (isset($dico['textes'][$texte])) return $dico['textes'][$texte];
    // Textes avec une partie variable (nom, date, nombre...) : motifs
    $motifs = $dico['motifs'] + ($email ? ($dico['motifs_email'] ?? []) : []);
    foreach ($motifs as $motif => $remplacement) {
        if (preg_match($motif, $texte)) {
            return is_callable($remplacement)
                ? preg_replace_callback($motif, $remplacement, $texte)
                : preg_replace($motif, $remplacement, $texte);
        }
    }
    return $texte;
}

// Traduit une page HTML complète (ou un e-mail)
function traduire_html(string $html, string $langue, bool $email = false): string
{
    if ($langue === 'fr' || $html === '') return $html;
    $dico = dictionnaire($langue);

    // 1. On met de côté ce qui ne doit jamais être traduit :
    //    scripts, styles, zones de saisie (contenu tapé par les membres)
    $proteges = [];
    $html = preg_replace_callback('#<(script|style|textarea)\b[^>]*>.*?</\1>#si', function ($m) use (&$proteges) {
        $proteges[] = $m[0];
        return "\x01" . (count($proteges) - 1) . "\x01";
    }, $html);

    // 2. Textes entre les balises : traduits seulement s'ils correspondent exactement
    $html = preg_replace_callback('/>([^<>]+)</u', function ($m) use ($langue, $email) {
        $brut = $m[1];
        $texte = preg_replace('/\s+/u', ' ', trim($brut));
        if ($texte === '' || !preg_match('/\p{L}/u', $texte)) return $m[0];
        $decode = html_entity_decode($texte, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $traduit = t($decode, $langue, $email);
        if ($traduit === $decode) return $m[0];
        $debut = substr($brut, 0, strlen($brut) - strlen(ltrim($brut)));
        $fin = substr($brut, strlen(rtrim($brut)));
        return '>' . $debut . htmlspecialchars($traduit, ENT_QUOTES, 'UTF-8', false) . $fin . '<';
    }, $html);

    // 3. Attributs lisibles : placeholder, title, aria-label, alt, contenu des balises meta
    $html = preg_replace_callback('/\b(placeholder|title|aria-label|alt|content|label)="([^"]*)"/u', function ($m) use ($langue) {
        $valeur = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match('/\p{L}{2}/u', $valeur)) return $m[0];
        $traduit = t($valeur, $langue);
        return $traduit === $valeur ? $m[0] : $m[1] . '="' . htmlspecialchars($traduit, ENT_QUOTES, 'UTF-8') . '"';
    }, $html);

    // 4. Messages de confirmation JavaScript (onsubmit="return confirm('...')")
    $html = preg_replace_callback("/confirm\\('((?:[^'\\\\]|\\\\.)*)'\\)/u", function ($m) use ($langue) {
        $texte = str_replace("\\'", "'", html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $traduit = t($texte, $langue);
        return $traduit === $texte ? $m[0] : "confirm('" . htmlspecialchars(str_replace("'", "\\'", $traduit), ENT_QUOTES, 'UTF-8') . "')";
    }, $html);

    // 5. Langue déclarée de la page
    $html = preg_replace('/<html lang="fr"/', '<html lang="' . $langue . '"', $html);

    // 6. On remet les parties protégées
    return preg_replace_callback("/\x01(\d+)\x01/", fn($m) => $proteges[(int)$m[1]], $html);
}

// Tampon de sortie : toute la page est traduite avant l'envoi
// (les réponses JSON de l'API ne passent pas par ici)
function demarrer_traduction(): void
{
    if (LANGUE === 'fr') return;
    ob_start(function ($html) {
        foreach (headers_list() as $entete) {
            if (stripos($entete, 'Content-Type:') === 0 && stripos($entete, 'text/html') === false) return $html;
        }
        return traduire_html($html, LANGUE);
    });
}

// Lien vers la page actuelle dans une autre langue
function lien_langue(string $langue): string
{
    $q = $_GET;
    $q['lang'] = $langue;
    return htmlspecialchars(basename($_SERVER['SCRIPT_NAME'] ?? 'index.php') . '?' . http_build_query($q));
}

// Textes utilisés par script.js
function textes_js(): string
{
    return json_encode(dictionnaire(LANGUE)['js'] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
}
