<?php
// ============================================================
// Encadré « formule + publications du mois » (publier.php, dashboard.php)
// Variable attendue : $quota (résultat de quota_publication())
// ============================================================
$atteint = !$quota['illimite'] && $quota['restantes'] <= 0;
?>
<div class="quota <?= $quota['formule'] !== 'gratuit' ? 'quota-premium' : '' ?> <?= $atteint ? 'quota-atteint' : '' ?>">
    <div class="quota-ligne">
        <span>
            <i class="fa-solid <?= $quota['formule'] === 'gratuit' ? 'fa-gauge' : 'fa-crown' ?>"></i>
            Formule <strong><?= e($quota['nom']) ?></strong>
            <?php if ($quota['fin']): ?> · jusqu'au <?= date('d/m/Y', strtotime($quota['fin'])) ?><?php endif; ?>
        </span>
        <span>
            <?php if ($quota['illimite']): ?>
                Publications illimitées
            <?php else: ?>
                <strong><?= (int)$quota['utilisees'] ?> / <?= (int)$quota['limite'] ?></strong> publications ce mois-ci
            <?php endif; ?>
        </span>
    </div>
    <?php if (!$quota['illimite']): ?>
        <div class="quota-barre"><span style="width:<?= min(100, (int)round($quota['utilisees'] / max(1, $quota['limite']) * 100)) ?>%"></span></div>
    <?php endif; ?>
    <?php if ($atteint): ?>
        <p>Limite atteinte : tu pourras publier à nouveau le <?= date('d/m/Y', strtotime('first day of next month')) ?>,
            ou tout de suite en <a href="tarifs.php">passant à une formule supérieure</a>.</p>
    <?php elseif ($quota['formule'] !== 'illimite'): ?>
        <p><a href="tarifs.php">Besoin de publier plus ? Voir les formules</a></p>
    <?php endif; ?>
</div>
