<?php
// Adresse absolue du site (nécessaire pour l'aperçu des liens partagés)
$base_url = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
          . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
          . rtrim(str_replace(DIRECTORY_SEPARATOR, '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$og_title = $og_title ?? 'SafeGP — Envoyez vos colis entre le Maroc et le Sénégal';
$og_description = $og_description ?? 'Trouvez un GP de confiance pour transporter vos colis entre le Maroc et le Sénégal.';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= e($og_description) ?>">

<!-- Aperçu du lien quand on le partage (WhatsApp, Facebook, Messenger...) -->
<meta property="og:type" content="website">
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

<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="script.js" defer></script>
