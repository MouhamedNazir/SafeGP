<?php
// ============================================================
// REÇU DE DÉPÔT D'UN COLIS (entre le GP et l'expéditeur)
// Disponible dès que le GP a confirmé avoir reçu le colis.
// Imprimable ou « Enregistrer en PDF » depuis le navigateur.
// ============================================================
require_once 'init.php';
exiger_connexion();
$moi = (int)$_SESSION['user_id'];
$code = strtoupper(trim($_GET['code'] ?? ''));

$stmt = $conn->prepare("
    SELECT e.*, t.point_depot, t.point_reception,
           g.nom AS gp_nom, g.username AS gp_username, g.verifie AS gp_verifie, x.nom AS exp_nom, x.username AS exp_username,
           (SELECT created_at FROM envois_etapes WHERE envoi_id = e.id AND statut = 'depose' ORDER BY id LIMIT 1) AS depose_le,
           (SELECT note FROM envois_etapes WHERE envoi_id = e.id AND statut = 'depose' ORDER BY id LIMIT 1) AS note_depot
    FROM envois e
    LEFT JOIN trajets t ON t.id = e.trajet_id
    JOIN users g ON g.id = e.gp_id
    JOIN users x ON x.id = e.expediteur_id
    WHERE e.code = ? AND e.statut IN ('depose', 'en_route', 'arrive', 'remis')");
$stmt->bind_param("s", $code);
$stmt->execute();
$e = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$e || (!$nav_is_admin && !in_array($moi, [(int)$e['gp_id'], (int)$e['expediteur_id']], true))) {
    $titre_404 = 'Reçu introuvable';
    $texte_404 = "Ce reçu n'existe pas encore (le colis doit d'abord être déposé) ou ne te concerne pas.";
    include '404.php';
}
$devise = DEVISES[$e['devise']][0] ?? '';
$prix = rtrim(rtrim(number_format((float)$e['prix_total'], 2, ',', ' '), '0'), ',') . ' ' . $devise;
$kg = str_replace('.', ',', (string)((float)$e['kilos'] + 0));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Reçu de dépôt <?= e($e['code']) ?> - SafeGP</title>
    <meta name="robots" content="noindex">
</head>
<body class="page-recu">
<?php include 'navbar.php'; ?>

<section class="recu">
    <div class="recu-actions">
        <button type="button" class="btn-contact" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer / enregistrer en PDF</button>
        <a href="envoi.php?code=<?= e($e['code']) ?>" class="lien-discret">← Retour à l'envoi</a>
    </div>

    <div class="recu-feuille">
        <div class="recu-entete">
            <div>
                <img src="images/SafeGP_logo.png" alt="SafeGP" class="recu-logo">
                <h2>Safe<span class="gp">GP</span></h2>
            </div>
            <div class="recu-titre">
                <h3>Reçu de dépôt de colis</h3>
                <p>Code de suivi <strong><?= e($e['code']) ?></strong></p>
                <p>Déposé le <?= date('d/m/Y à H:i', strtotime($e['depose_le'])) ?></p>
            </div>
        </div>

        <div class="recu-colonnes">
            <div class="recu-bloc">
                <h4>GP (transporteur)</h4>
                <p><strong><?= e($e['gp_nom']) ?></strong> (@<?= e($e['gp_username'] ?? '') ?>)<?= $e['gp_verifie'] ? ' <span>— identité vérifiée</span>' : '' ?></p>
            </div>
            <div class="recu-bloc">
                <h4>Expéditeur</h4>
                <p><strong><?= e($e['exp_nom']) ?></strong> (@<?= e($e['exp_username'] ?? '') ?>)</p>
            </div>
            <div class="recu-bloc">
                <h4>Destinataire</h4>
                <p><strong><?= e($e['destinataire_nom']) ?></strong><?= $e['destinataire_tel'] ? '<br>' . e($e['destinataire_tel']) : '' ?></p>
            </div>
        </div>

        <table class="recu-tableau">
            <thead><tr><th>Trajet</th><th>Contenu déclaré</th><th>Poids</th><th>Prix</th></tr></thead>
            <tbody>
                <tr>
                    <td><?= e(($e['depart'] ?? '—') . ' → ' . ($e['arrivee'] ?? '—')) ?><br>
                        <small><?= e(nom_pays($e['pays_depart']) . ' → ' . nom_pays($e['pays_arrivee'])) ?><?= $e['date_depart'] ? ' · départ le ' . date('d/m/Y', strtotime($e['date_depart'])) : '' ?></small></td>
                    <td><?= e($e['contenu']) ?></td>
                    <td><?= $kg ?> kg</td>
                    <td><?= e($prix) ?></td>
                </tr>
            </tbody>
            <tfoot><tr><td colspan="3">Total convenu</td><td><strong><?= e($prix) ?></strong></td></tr></tfoot>
        </table>

        <?php if ($e['note_depot']): ?>
            <div class="recu-bloc"><h4>Note du GP au dépôt</h4><p><?= e($e['note_depot']) ?></p></div>
        <?php endif; ?>

        <div class="recu-bloc">
            <h4>Suivi</h4>
            <p>Statut actuel : <strong><?= e(libelle_statut_envoi($e['statut'])) ?></strong> — suivi en ligne : <?= e(url_site()) ?>/suivi.php?code=<?= e($e['code']) ?></p>
        </div>

        <div class="recu-signatures">
            <div>Signature du GP</div>
            <div>Signature de l'expéditeur</div>
        </div>

        <p class="recu-pied">Le GP confirme avoir reçu le colis décrit ci-dessus. Le paiement du transport est réglé directement entre le GP et l'expéditeur.
            SafeGP met les membres en relation et n'est pas partie au contrat de transport (voir les conditions d'utilisation).</p>
    </div>
</section>

<?php include 'footer.php'; ?>
</body>
</html>
