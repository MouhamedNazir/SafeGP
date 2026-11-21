<?php
// ============================================================
// PAGE INTROUVABLE (404)
// Utilisée par .htaccess (ErrorDocument) et par les pages dont
// l'élément demandé n'existe pas (trajet supprimé, profil inconnu...)
// ============================================================
require_once __DIR__ . '/init.php';
http_response_code(404);
$titre_404 = $titre_404 ?? 'Page introuvable';
$texte_404 = $texte_404 ?? "Cette page n'existe pas ou n'existe plus.";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/head.php'; ?>
    <title><?= e($titre_404) ?> - SafeGP</title>
    <meta name="robots" content="noindex">
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<section class="page-404">
    <div class="page-404-icone" aria-hidden="true">
        <i class="fa-solid fa-plane"></i>
        <span>404</span>
    </div>
    <h2><?= e($titre_404) ?></h2>
    <p><?= e($texte_404) ?></p>
    <div class="page-404-actions">
        <a href="index.php" class="btn-contact"><i class="fa-solid fa-house"></i> Accueil</a>
        <a href="search_trajet.php" class="btn-contact btn-secondaire"><i class="fa-solid fa-magnifying-glass"></i> Rechercher un trajet</a>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
<?php exit(); ?>
