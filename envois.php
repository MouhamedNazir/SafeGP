<?php
// ============================================================
// MES ENVOIS : colis que j'envoie (expéditeur) et colis que je
// transporte (GP), avec les demandes à traiter en premier
// ============================================================
require_once 'init.php';
exiger_connexion();
$moi = (int)$_SESSION['user_id'];

$requete = "
    SELECT e.*, t.depart, t.arrivee, t.pays_depart, t.pays_arrivee, t.date_depart,
           g.nom AS gp_nom, g.username AS gp_username, x.nom AS exp_nom, x.username AS exp_username
    FROM envois e
    LEFT JOIN trajets t ON t.id = e.trajet_id
    JOIN users g ON g.id = e.gp_id
    JOIN users x ON x.id = e.expediteur_id
    WHERE e.%s = ?
    ORDER BY (e.statut = 'demande') DESC, FIELD(e.statut, 'refuse', 'annule', 'remis') ASC, t.date_depart ASC, e.id DESC";

$listes = [];
foreach (['gp_id' => 'transporte', 'expediteur_id' => 'envoye'] as $colonne => $cle) {
    $stmt = $conn->prepare(sprintf($requete, $colonne));
    $stmt->bind_param("i", $moi);
    $stmt->execute();
    $listes[$cle] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$conn->close();
$a_traiter = count(array_filter($listes['transporte'], fn($e) => $e['statut'] === 'demande'));

function ligne_envoi(array $e, bool $vue_gp): void
{
    $kg = str_replace('.', ',', (string)((float)$e['kilos'] + 0));
    ?>
    <a href="envoi.php?code=<?= e($e['code']) ?>" class="carte-envoi <?= $e['statut'] === 'demande' && $vue_gp ? 'a-traiter' : '' ?>">
        <div class="carte-envoi-haut">
            <span class="carte-trajet-route">
                <span><?= drapeau($e['pays_depart']) ?> <?= e($e['depart'] ?? '—') ?></span>
                <i class="fa-solid fa-plane"></i>
                <span><?= drapeau($e['pays_arrivee']) ?> <?= e($e['arrivee'] ?? '—') ?></span>
            </span>
            <span class="badge-envoi statut-<?= e($e['statut']) ?>"><i class="fa-solid <?= STATUTS_ENVOI[$e['statut']][1] ?>"></i> <?= e(libelle_statut_envoi($e['statut'])) ?></span>
        </div>
        <div class="carte-trajet-infos">
            <span><i class="fa-solid fa-barcode"></i> <?= e($e['code']) ?></span>
            <span><i class="fa-solid fa-weight-hanging"></i> <?= $kg ?> kg</span>
            <?php if ($e['date_depart']): ?><span><i class="fa-regular fa-calendar"></i> <?= date('d/m/Y', strtotime($e['date_depart'])) ?></span><?php endif; ?>
            <span><i class="fa-solid fa-user"></i> <?= $vue_gp ? 'de @' . e($e['exp_username'] ?? $e['exp_nom']) : 'GP @' . e($e['gp_username'] ?? $e['gp_nom']) ?></span>
        </div>
    </a>
    <?php
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Mes envois - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

<section class="resultats">
    <h2><i class="fa-solid fa-boxes-stacked"></i> Mes envois</h2>

    <h3 class="titre-section">Colis à transporter <?php if ($a_traiter): ?><span class="badge-nonlu"><?= $a_traiter ?></span><?php endif; ?></h3>
    <?php if ($listes['transporte']): ?>
        <div class="liste-envois">
            <?php foreach ($listes['transporte'] as $e) ligne_envoi($e, true); ?>
        </div>
    <?php else: ?>
        <p class="texte-doux-bloc">Aucune réservation sur tes trajets pour l'instant. Les expéditeurs réservent des kilos depuis la page de tes annonces.</p>
    <?php endif; ?>

    <h3 class="titre-section">Colis que j'envoie</h3>
    <?php if ($listes['envoye']): ?>
        <div class="liste-envois">
            <?php foreach ($listes['envoye'] as $e) ligne_envoi($e, false); ?>
        </div>
    <?php else: ?>
        <p class="texte-doux-bloc">Tu n'as encore réservé aucun envoi. <a href="search_trajet.php">Cherche un trajet</a> puis clique sur « Réserver des kilos ».</p>
    <?php endif; ?>
</section>

<?php include 'footer.php'; ?>
</body>
</html>
