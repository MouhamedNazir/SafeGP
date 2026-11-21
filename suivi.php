<?php
// ============================================================
// SUIVI PUBLIC D'UN COLIS avec son code (sans compte)
// N'affiche que le trajet et l'avancement : ni contenu, ni téléphones.
// ============================================================
require_once 'init.php';

$code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_GET['code'] ?? ''));
$e = null;
$etapes = [];
if ($code !== '') {
    // Petit frein contre les essais de codes au hasard
    $_SESSION['essais_suivi'] = ($_SESSION['essais_suivi'] ?? 0) + 1;
    if ($_SESSION['essais_suivi'] <= 60) {
        $stmt = $conn->prepare("SELECT e.id, e.code, e.statut, e.kilos, e.destinataire_nom, t.depart, t.arrivee, t.pays_depart, t.pays_arrivee,
                t.date_depart, t.date_arrivee, g.username AS gp_username, g.nom AS gp_nom, g.verifie, e.gp_id
            FROM envois e LEFT JOIN trajets t ON t.id = e.trajet_id JOIN users g ON g.id = e.gp_id WHERE e.code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $e = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($e) {
            $etapes = $conn->query("SELECT statut, created_at FROM envois_etapes WHERE envoi_id = " . (int)$e['id'] . " ORDER BY id")->fetch_all(MYSQLI_ASSOC);
        }
    }
}
$conn->close();
$frise = $e ? envoi_frise($etapes) : [];
// Prénom du destinataire seulement (confidentialité)
$prenom = $e ? explode(' ', trim($e['destinataire_nom']))[0] : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Suivre un colis - SafeGP</title>
    <meta name="robots" content="noindex">
</head>
<body>
<?php include 'navbar.php'; ?>

<section class="publier page-suivi">
    <h2><i class="fa-solid fa-magnifying-glass-location"></i> Suivre un colis</h2>

    <form action="suivi.php" method="GET" class="form-suivi">
        <input type="text" name="code" value="<?= e($code) ?>" placeholder="Code de suivi (ex : SGP7K3M9X)" maxlength="12" required autocomplete="off">
        <button type="submit" class="btn-contact"><i class="fa-solid fa-magnifying-glass"></i> Suivre</button>
    </form>

    <?php if ($code !== '' && !$e): ?>
        <p class="erreur"><i class="fa-solid fa-circle-xmark"></i> Aucun colis ne correspond à ce code. Vérifie-le auprès de l'expéditeur.</p>
    <?php elseif ($e): ?>
        <div class="resultat-suivi">
            <div class="envoi-entete">
                <div>
                    <span class="envoi-code-label">Colis pour <?= e($prenom) ?></span>
                    <h3 class="envoi-code"><?= e($e['code']) ?></h3>
                    <p class="carte-trajet-route">
                        <span><?= drapeau($e['pays_depart']) ?> <?= e($e['depart'] ?? '—') ?></span>
                        <i class="fa-solid fa-plane"></i>
                        <span><?= drapeau($e['pays_arrivee']) ?> <?= e($e['arrivee'] ?? '—') ?></span>
                    </p>
                </div>
                <span class="badge-envoi statut-<?= e($e['statut']) ?>"><i class="fa-solid <?= STATUTS_ENVOI[$e['statut']][1] ?>"></i> <?= e(libelle_statut_envoi($e['statut'])) ?></span>
            </div>

            <?php if (!in_array($e['statut'], ['refuse', 'annule'], true)): ?>
                <ol class="frise frise-verticale">
                    <?php foreach ($frise as $statut => $date): ?>
                        <li class="<?= $date ? 'fait' : '' ?> <?= $statut === $e['statut'] ? 'actuel' : '' ?>">
                            <span class="frise-point"><i class="fa-solid <?= STATUTS_ENVOI[$statut][1] ?>"></i></span>
                            <span class="frise-texte"><?= e(libelle_statut_envoi($statut)) ?></span>
                            <?php if ($date): ?><small><?= date('d/m/Y H:i', strtotime($date)) ?></small><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <p class="aide-contact">
                Transporté par <a href="profil.php?id=<?= (int)$e['gp_id'] ?>">@<?= e($e['gp_username'] ?? $e['gp_nom']) ?></a> <?= badge_verifie($e['verifie']) ?>
                <?php if ($e['date_arrivee']): ?> · arrivée prévue le <?= date('d/m/Y', strtotime($e['date_arrivee'])) ?><?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <p class="aide-contact" style="text-align:center;">Le code de suivi est donné à l'expéditeur quand il réserve des kilos auprès d'un GP. Il peut te l'envoyer par WhatsApp.</p>
    <?php endif; ?>
</section>

<?php include 'footer.php'; ?>
</body>
</html>
