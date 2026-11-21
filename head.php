<?php
// Adresse absolue du site (nécessaire pour l'aperçu des liens partagés)
$base_url = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
          . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
          . rtrim(str_replace(DIRECTORY_SEPARATOR, '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$og_title = $og_title ?? 'SafeGP — Envoyez vos colis partout dans le monde';
$og_description = $og_description ?? 'Trouvez un GP (voyageur de confiance) pour transporter vos colis, partout dans le monde.';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="dark light">

<!-- Thème clair / sombre : appliqué avant l'affichage pour éviter un flash.
     Choix enregistré dans le navigateur, sinon réglage du téléphone/ordinateur. -->
<script>
    (function () {
        var theme = null;
        try { theme = localStorage.getItem('safegp-theme'); } catch (e) {}
        if (theme !== 'clair' && theme !== 'sombre') {
            theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'clair' : 'sombre';
        }
        document.documentElement.setAttribute('data-theme', theme);
    })();
</script>
<meta name="description" content="<?= e($og_description) ?>">

<!-- Aperçu du lien quand on le partage (WhatsApp, Facebook, Messenger...) -->
<meta property="og:type" content="website">
<meta property="og:locale" content="<?= LANGUE === 'en' ? 'en_GB' : 'fr_FR' ?>">
<meta property="og:site_name" content="SafeGP">
<meta property="og:title" content="<?= e($og_title) ?>">
<meta property="og:description" content="<?= e($og_description) ?>">
<meta property="og:url" content="<?= e($base_url . '/' . basename($_SERVER['SCRIPT_NAME'] ?? 'index.php') . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')) ?>">
<meta property="og:image" content="<?= e($base_url) ?>/images/SafeGP_logo1.jpeg">
<meta property="og:image:width" content="1195">
<meta property="og:image:height" content="896">
<meta property="og:image:alt" content="Logo SafeGP">
<meta name="twitter:card" content="summary_large_image">

<!-- Icône de l'onglet -->
<link rel="icon" type="image/png" href="images/SafeGP_logo.png">
<link rel="apple-touch-icon" href="images/SafeGP_logo.png">

<!-- Police, icônes et drapeaux -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@7.2.3/css/flag-icons.min.css">

<link rel="stylesheet" href="style.css?v=6">
<script>window.SAFEGP_T = <?= textes_js() ?>;</script>
<script src="script.js" defer></script>
