<?php
// ============================================================
// TARIFS — les 3 formules, en mensuel ou annuel
// Accessible à tous ; le bouton mène à abonnement.php
// ============================================================
require_once 'init.php';

$annuel = ($_GET['duree'] ?? '') === 'an';
$quota = isset($_SESSION['user_id']) ? quota_publication($conn, (int)$_SESSION['user_id']) : null;
$conn->close();

$avantages = [
    'gratuit'  => ['4 trajets publiés par mois', 'Messagerie et WhatsApp', 'Profil public'],
    'standard' => ['8 trajets publiés par mois', 'Messagerie et WhatsApp', 'Profil public', 'Idéal pour les GP réguliers'],
    'illimite' => ['Trajets illimités', 'Messagerie et WhatsApp', 'Profil public', 'Pour les GP professionnels'],
];
$og_title = 'Tarifs SafeGP — publiez vos trajets de GP';
$og_description = 'Gratuit : 4 trajets par mois. Standard : 8 trajets pour 500 FCFA/mois. Illimité : 750 FCFA/mois. 2 mois offerts en annuel.';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Tarifs - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

<section class="tarifs">
    <h2>Choisis ta formule</h2>
    <p class="tarifs-intro">La recherche de trajets et la messagerie sont <strong>gratuites pour tout le monde</strong>.
        Les formules ne concernent que les GP qui publient beaucoup de trajets.</p>

    <!-- Mensuel / annuel -->
    <div class="bascule-duree" role="group" aria-label="Durée">
        <a href="tarifs.php" class="<?= !$annuel ? 'actif' : '' ?>">Mensuel</a>
        <a href="tarifs.php?duree=an" class="<?= $annuel ? 'actif' : '' ?>">Annuel <span class="badge-offre">2 mois offerts</span></a>
    </div>

    <?php if ($quota): ?>
        <p class="tarifs-actuel">
            <i class="fa-solid fa-circle-info"></i> Ta formule actuelle : <strong><?= e($quota['nom']) ?></strong>
            <?php if ($quota['fin']): ?>jusqu'au <?= date('d/m/Y', strtotime($quota['fin'])) ?><?php endif; ?>
            · <?= $quota['illimite'] ? 'publications illimitées' : $quota['utilisees'] . ' / ' . $quota['limite'] . ' publications ce mois-ci' ?>
        </p>
    <?php endif; ?>

    <div class="grille-tarifs">
        <?php foreach (FORMULES as $code => $f): ?>
            <?php
                $prix = $annuel ? $f['prix_an'] : $f['prix_mois'];
                $mise_en_avant = $code === 'standard';
                $est_actuelle = $quota && $quota['formule'] === $code;
            ?>
            <div class="carte-tarif <?= $mise_en_avant ? 'carte-tarif-vedette' : '' ?>">
                <?php if ($mise_en_avant): ?><span class="ruban">Le plus choisi</span><?php endif; ?>
                <h3><?= e($f['nom']) ?></h3>
                <div class="tarif-prix">
                    <?php if ($prix === 0): ?>
                        <strong>0 FCFA</strong>
                    <?php else: ?>
                        <strong><?= e(prix_fcfa($prix)) ?></strong><span>/<?= $annuel ? 'an' : 'mois' ?></span>
                    <?php endif; ?>
                </div>
                <p class="tarif-equivalent">
                    <?php if ($prix > 0): ?>
                        <?= e(equivalents_prix($prix)) ?>
                        <?php if ($annuel): ?><br><s><?= e(prix_fcfa($f['prix_mois'] * 12)) ?></s> · soit <?= e(prix_fcfa((int)round($f['prix_an'] / 12))) ?>/mois<?php endif; ?>
                    <?php else: ?>
                        Pour toujours
                    <?php endif; ?>
                </p>
                <ul class="tarif-avantages">
                    <?php foreach ($avantages[$code] as $a): ?>
                        <li><i class="fa-solid fa-check"></i> <?= e($a) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($code === 'gratuit'): ?>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="inscription.php" class="btn-contact btn-secondaire btn-large">Créer mon compte</a>
                    <?php else: ?>
                        <span class="tarif-inclus">Inclus dans ton compte</span>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="abonnement.php?formule=<?= $code ?>&amp;duree=<?= $annuel ? 'an' : 'mois' ?>"
                       class="btn-contact btn-large <?= $mise_en_avant ? '' : 'btn-secondaire' ?>">
                        <?= $est_actuelle ? 'Prolonger' : 'Choisir ' . e($f['nom']) ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="tarifs-faq">
        <h3>Questions fréquentes</h3>
        <details>
            <summary>Comment payer ?</summary>
            <p>Par <strong>Wave</strong> ou <strong>Orange Money</strong>. Après avoir choisi ta formule, tu vois le numéro à payer ;
                tu saisis ensuite la référence de la transaction et nous activons ton abonnement après vérification (en général dans la journée).</p>
        </details>
        <details>
            <summary>Je ne suis pas dans la zone FCFA, comment faire ?</summary>
            <p>Les prix sont fixés en FCFA ; l'équivalent en euros, dollars et dirhams est indiqué à titre indicatif.
                Si tu ne peux pas payer par Wave ou Orange Money, <a href="retours.php?type=autre">écris-nous</a> : nous trouverons une solution.</p>
        </details>
        <details>
            <summary>Que se passe-t-il à la fin de l'abonnement ?</summary>
            <p>Tu reçois un rappel quelques jours avant. Sans renouvellement, ton compte repasse simplement en formule Gratuite :
                tes trajets restent en ligne, seule la limite de publication change.</p>
        </details>
        <details>
            <summary>Comment sont comptées les publications ?</summary>
            <p>Chaque trajet publié compte pour le mois calendaire en cours (du 1er au dernier jour du mois).
                Modifier ou supprimer un trajet ne rend pas la publication.</p>
        </details>
    </div>
</section>

<?php include 'footer.php'; ?>
</body>
</html>
