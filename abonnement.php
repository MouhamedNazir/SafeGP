<?php
// ============================================================
// SOUSCRIRE UN ABONNEMENT
// 1. le membre voit le montant et le numéro Wave / Orange Money
// 2. il paie depuis son téléphone
// 3. il saisit la référence de la transaction → demande « en attente »
// 4. l'admin vérifie et valide dans le panel admin
// ============================================================
require_once 'init.php';

exiger_connexion();
$user_id = (int)$_SESSION['user_id'];

$formule = $_GET['formule'] ?? $_POST['formule'] ?? 'standard';
$duree = ($_GET['duree'] ?? $_POST['duree'] ?? 'mois') === 'an' ? 'an' : 'mois';
if (!in_array($formule, ['standard', 'illimite'], true)) $formule = 'standard';
$montant = prix_formule($formule, $duree);
$erreur = "";

// Numéros de paiement (définis dans config.php)
$numeros = array_filter([
    'wave'         => defined('PAIEMENT_WAVE') ? PAIEMENT_WAVE : '',
    'orange_money' => defined('PAIEMENT_ORANGE_MONEY') ? PAIEMENT_ORANGE_MONEY : '',
]);
$titulaire = defined('PAIEMENT_TITULAIRE') ? PAIEMENT_TITULAIRE : '';

// Coordonnées bancaires (virement, surtout depuis l'étranger)
$banque = [];
foreach (['BANQUE_NOM' => 'Banque', 'BANQUE_IBAN' => 'IBAN', 'BANQUE_RIB' => 'RIB', 'BANQUE_SWIFT' => 'Code SWIFT / BIC'] as $const => $libelle) {
    if (defined($const) && constant($const) !== '') $banque[$libelle] = constant($const);
}
$virement_possible = isset($banque['IBAN']) || isset($banque['RIB']);

// Une seule demande en attente à la fois
$stmt = $conn->prepare("SELECT * FROM abonnements WHERE user_id = ? AND statut = 'en_attente' ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$en_attente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST" && !$en_attente) {
    $moyen = $_POST['moyen'] ?? '';
    $reference = trim($_POST['reference'] ?? '');
    $telephone = vide_en_null($_POST['telephone'] ?? '');

    if (!isset(MOYENS_PAIEMENT[$moyen])) {
        $erreur = "Choisis le moyen de paiement utilisé.";
    } elseif (mb_strlen($reference) < 4 || mb_strlen($reference) > 100) {
        $erreur = "Indique la référence de la transaction (reçue par SMS ou dans l'application).";
    } elseif ($telephone !== null && !telephone_valide($telephone)) {
        $erreur = "Numéro de téléphone invalide.";
    } elseif (!empty($_FILES['preuve']['name']) && !($preuve = enregistrer_fichier_prive($_FILES['preuve'], $erreur))) {
        // $erreur est renseignée par enregistrer_fichier_prive()
    } else {
        $preuve = $preuve ?? null;
        $stmt = $conn->prepare("INSERT INTO abonnements (user_id, formule, duree, montant, moyen, reference, telephone, preuve) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ississss", $user_id, $formule, $duree, $montant, $moyen, $reference, $telephone, $preuve);
        $stmt->execute();
        $stmt->close();

        // Prévenir les admins
        $admins = $conn->query("SELECT email FROM users WHERE role = 'admin'");
        while ($admin = $admins->fetch_assoc()) {
            email_envoyer($admin['email'], 'Paiement à vérifier : ' . prix_fcfa($montant), email_gabarit(
                'Nouvelle demande d\'abonnement',
                '<p><strong>' . htmlspecialchars($_SESSION['user_nom'] ?? '') . '</strong> demande la formule <strong>'
                . htmlspecialchars(FORMULES[$formule]['nom']) . '</strong> (' . ($duree === 'an' ? '1 an' : '1 mois') . ').</p>'
                . '<p>Montant : <strong>' . prix_fcfa($montant) . '</strong><br>Moyen : ' . MOYENS_PAIEMENT[$moyen]
                . '<br>Référence : <strong>' . htmlspecialchars($reference) . '</strong>'
                . ($telephone ? '<br>Payé depuis : ' . htmlspecialchars($telephone) : '') . '</p>',
                'Vérifier dans le panel admin',
                url_site() . '/admin.php#abonnements'
            ));
        }
        $conn->close();
        header("Location: abonnement.php?envoye=1");
        exit();
    }
}

$quota = quota_publication($conn, $user_id);
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Abonnement - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

<section class="publier">
    <h2>Abonnement <?= e(FORMULES[$formule]['nom']) ?></h2>

    <?php if (isset($_GET['envoye']) || $en_attente): ?>
        <?php $d = $en_attente; ?>
        <p class="success"><i class="fa-solid fa-hourglass-half"></i> Demande reçue ! Nous vérifions ton paiement et activons ton abonnement (en général dans la journée). Tu recevras un e-mail.</p>
        <?php if ($d): ?>
            <div class="recap-paiement">
                <p><span>Formule</span><strong><?= e(FORMULES[$d['formule']]['nom']) ?> · <?= $d['duree'] === 'an' ? '1 an' : '1 mois' ?></strong></p>
                <p><span>Montant</span><strong><?= e(prix_fcfa((int)$d['montant'])) ?></strong></p>
                <p><span>Moyen</span><strong><?= e(MOYENS_PAIEMENT[$d['moyen']]) ?></strong></p>
                <p><span>Référence</span><strong><?= e($d['reference']) ?></strong></p>
                <p><span>Envoyée le</span><strong><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></strong></p>
            </div>
        <?php endif; ?>
        <p style="text-align:center;"><a href="mon_profil.php#abonnement">Voir mon abonnement</a></p>
    <?php else: ?>

        <!-- Récapitulatif -->
        <div class="recap-paiement">
            <p><span>Formule</span><strong><?= e(FORMULES[$formule]['nom']) ?> — <?= FORMULES[$formule]['limite'] ? FORMULES[$formule]['limite'] . ' publications / mois' : 'publications illimitées' ?></strong></p>
            <p><span>Durée</span><strong><?= $duree === 'an' ? '1 an (2 mois offerts)' : '1 mois' ?></strong>
                <a href="abonnement.php?formule=<?= $formule ?>&amp;duree=<?= $duree === 'an' ? 'mois' : 'an' ?>" class="lien-discret">passer en <?= $duree === 'an' ? 'mensuel' : 'annuel' ?></a></p>
            <p class="recap-total"><span>À payer</span><strong><?= e(prix_fcfa($montant)) ?></strong></p>
            <p class="tarif-equivalent"><?= e(equivalents_prix($montant)) ?></p>
            <?php if ($quota['formule'] === $formule && $quota['fin']): ?>
                <p class="aide-contact">Ton abonnement actuel sera prolongé à partir du <?= date('d/m/Y', strtotime($quota['fin'] . ' +1 day')) ?>.</p>
            <?php elseif ($quota['formule'] !== 'gratuit'): ?>
                <p class="aide-contact">Ta formule actuelle (<?= e($quota['nom']) ?>) sera remplacée dès la validation.</p>
            <?php endif; ?>
        </div>

        <!-- Étape 1 : payer -->
        <h3 class="form-section">1. Paie <?= e(prix_fcfa($montant)) ?></h3>
        <?php if ($numeros): ?>
            <div class="moyens-paiement">
                <?php foreach ($numeros as $moyen => $numero): ?>
                    <div class="moyen-paiement moyen-<?= $moyen ?>">
                        <strong><?= e(MOYENS_PAIEMENT[$moyen]) ?></strong>
                        <span class="numero-paiement"><?= e($numero) ?></span>
                        <button type="button" class="btn-lien copier" data-copier="<?= e(preg_replace('/\s+/', '', $numero)) ?>"><i class="fa-regular fa-copy"></i> Copier</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($titulaire): ?><p class="aide-contact">Au nom de : <strong><?= e($titulaire) ?></strong></p><?php endif; ?>
        <?php endif; ?>

        <?php if ($virement_possible): ?>
            <details class="bloc-repliable virement">
                <summary><i class="fa-solid fa-building-columns"></i> Payer par virement bancaire (depuis l'étranger)</summary>
                <div class="coordonnees-banque">
                    <?php if ($titulaire): ?><p><span>Titulaire</span><strong><?= e($titulaire) ?></strong></p><?php endif; ?>
                    <?php foreach ($banque as $libelle => $valeur): ?>
                        <p><span><?= e($libelle) ?></span><strong><?= e($valeur) ?></strong>
                            <button type="button" class="btn-lien copier" data-copier="<?= e(preg_replace('/\s+/', '', $valeur)) ?>"><i class="fa-regular fa-copy"></i></button></p>
                    <?php endforeach; ?>
                    <p class="aide-contact">Montant à virer : <strong><?= e(prix_fcfa($montant)) ?></strong> <small>(<?= e(equivalents_prix($montant)) ?>)</small><br>
                        <span>Indique ton nom d'utilisateur SafeGP dans le motif du virement. Les frais bancaires sont à ta charge.</span></p>
                </div>
            </details>
        <?php endif; ?>

        <?php if (!$numeros && !$virement_possible): ?>
            <p class="quota quota-atteint">Les numéros de paiement ne sont pas encore configurés. <a href="retours.php?type=autre">Contacte-nous</a> pour t'abonner.</p>
        <?php endif; ?>

        <!-- Étape 2 : référence -->
        <h3 class="form-section">2. Indique la référence du paiement</h3>
        <?php if ($erreur): ?>
            <p class="erreur"><i class="fa-solid fa-circle-xmark"></i> <?= e($erreur) ?></p>
        <?php endif; ?>
        <form action="abonnement.php" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="formule" value="<?= e($formule) ?>">
            <input type="hidden" name="duree" value="<?= e($duree) ?>">
            <div class="form-group">
                <label>Moyen de paiement utilisé</label>
                <select name="moyen" required>
                    <option value="">Choisir</option>
                    <?php foreach (MOYENS_PAIEMENT as $code => $nom): ?>
                        <option value="<?= $code ?>" <?= ($_POST['moyen'] ?? '') === $code ? 'selected' : '' ?>><?= e($nom) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Référence de la transaction</label>
                <input type="text" name="reference" maxlength="100" required value="<?= e($_POST['reference'] ?? '') ?>" placeholder="Ex : T2411210945ABC (SMS) ou référence du virement">
            </div>
            <div class="form-group">
                <label>Numéro qui a payé <span class="facultatif">(facultatif, aide à retrouver le paiement)</span></label>
                <input type="tel" name="telephone" value="<?= e($_POST['telephone'] ?? '') ?>" placeholder="Ex : +221 77 123 45 67">
            </div>
            <div class="form-group">
                <label>Capture du reçu de paiement <span class="facultatif">(conseillé : ta demande est validée plus vite)</span></label>
                <input type="file" name="preuve" accept=".jpg,.jpeg,.png,.webp">
                <small>Capture d'écran du SMS ou de l'application Wave / Orange Money. Visible uniquement par l'équipe SafeGP.</small>
            </div>
            <button type="submit" class="btn-publier"><i class="fa-solid fa-paper-plane"></i> Envoyer ma demande</button>
            <p class="aide-contact" style="text-align:center; margin-top:15px;">En t'abonnant, tu acceptes les <a href="conditions.php">conditions d'utilisation</a>.</p>
        </form>
    <?php endif; ?>
</section>

<?php include 'footer.php'; ?>
</body>
</html>
