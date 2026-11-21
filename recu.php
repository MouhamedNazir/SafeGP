<?php
// ============================================================
// REÇU D'ABONNEMENT — imprimable (ou « Enregistrer en PDF » depuis
// le navigateur). Visible par le membre concerné et par l'admin.
// ============================================================
require_once 'init.php';
exiger_connexion();

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT a.*, u.nom, u.username, u.email FROM abonnements a JOIN users u ON u.id = a.user_id WHERE a.id = ? AND a.statut IN ('valide', 'annule')");
$stmt->bind_param("i", $id);
$stmt->execute();
$a = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$a || ((int)$a['user_id'] !== (int)$_SESSION['user_id'] && !$nav_is_admin)) {
    $titre_404 = 'Reçu introuvable';
    $texte_404 = "Ce reçu n'existe pas ou ne t'appartient pas.";
    include '404.php';
}
$numero = numero_recu($a);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Reçu <?= e($numero) ?> - SafeGP</title>
    <meta name="robots" content="noindex">
</head>
<body class="page-recu">
<?php include 'navbar.php'; ?>

<section class="recu">
    <div class="recu-actions">
        <button type="button" class="btn-contact" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer / enregistrer en PDF</button>
        <a href="mon_profil.php#abonnement" class="lien-discret">← Mon abonnement</a>
    </div>

    <div class="recu-feuille">
        <div class="recu-entete">
            <div>
                <img src="images/SafeGP_logo.png" alt="SafeGP" class="recu-logo">
                <h2>Safe<span class="gp">GP</span></h2>
            </div>
            <div class="recu-titre">
                <h3>Reçu de paiement</h3>
                <p>N° <strong><?= e($numero) ?></strong></p>
                <p>Émis le <?= date('d/m/Y', strtotime($a['traite_le'])) ?></p>
            </div>
        </div>

        <?php if ($a['statut'] === 'annule'): ?>
            <p class="recu-annule">ANNULÉ le <?= date('d/m/Y', strtotime($a['annule_le'])) ?>
                <?= $a['rembourse_le'] ? '· remboursé le ' . date('d/m/Y', strtotime($a['rembourse_le'])) : '· remboursement en cours' ?></p>
        <?php endif; ?>

        <div class="recu-bloc">
            <h4>Client</h4>
            <p><strong><?= e($a['nom']) ?></strong> (@<?= e($a['username'] ?? '') ?>)<br><?= e($a['email']) ?></p>
        </div>

        <table class="recu-tableau">
            <thead><tr><th>Désignation</th><th>Période</th><th>Montant</th></tr></thead>
            <tbody>
                <tr>
                    <td>Abonnement SafeGP — formule <?= e(FORMULES[$a['formule']]['nom']) ?> (<?= $a['duree'] === 'an' ? '1 an' : '1 mois' ?>)</td>
                    <td>du <?= date('d/m/Y', strtotime($a['date_debut'])) ?> au <?= date('d/m/Y', strtotime($a['date_fin'])) ?></td>
                    <td><?= e(prix_fcfa((int)$a['montant'])) ?></td>
                </tr>
            </tbody>
            <tfoot><tr><td colspan="2">Total payé</td><td><strong><?= e(prix_fcfa((int)$a['montant'])) ?></strong></td></tr></tfoot>
        </table>

        <div class="recu-bloc">
            <h4>Paiement</h4>
            <p><?= e(MOYENS_PAIEMENT[$a['moyen']]) ?> · référence <code><?= e($a['reference']) ?></code>
                · reçu le <?= date('d/m/Y', strtotime($a['created_at'])) ?></p>
        </div>

        <p class="recu-pied">SafeGP — mise en relation entre voyageurs (GP) et expéditeurs de colis.
            Ce reçu confirme le paiement d'un abonnement au service ; il ne concerne aucun transport de colis.</p>
    </div>
</section>

<?php include 'footer.php'; ?>
</body>
</html>
