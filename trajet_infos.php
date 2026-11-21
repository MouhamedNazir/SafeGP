<?php
// ============================================================
// En-tête + détails d'une annonce de trajet
// (search_trajet.php, profil.php, dashboard.php)
// Variables attendues :
//   $row     → le trajet (avec nom, username, photo, disponible du GP si $avec_gp)
//   $avec_gp → true pour afficher la ligne du GP
// ============================================================
$couleurs_statut = ['disponible' => '#16a34a', 'en_route' => '#f97316', 'arrivé' => '#94a3b8'];
$libelles_statut = ['disponible' => 'disponible', 'en_route' => 'en route', 'arrivé' => 'arrivé'];
$kg = fn($x) => str_replace('.', ',', rtrim(rtrim(number_format((float)$x, 1, '.', ''), '0'), '.'));
$depot_cloture = !empty($row['date_limite_depot']) && $row['date_limite_depot'] < date('Y-m-d');
?>
<div class="annonce-header">
    <span class="trajet-titre">
        <i class="fa-solid fa-plane-departure"></i> <?= e($row['depart']) ?>
        →
        <i class="fa-solid fa-plane-arrival"></i> <?= e($row['arrivee']) ?>
    </span>

    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <span class="badge-statut" style="background-color:<?= $couleurs_statut[$row['statut']] ?? '#94a3b8' ?>;">
            <?= e($libelles_statut[$row['statut']] ?? $row['statut']) ?>
        </span>
        <span class="prix"><?= e(prix_kilo_fr($row['prix_kilo'], $row['devise'] ?? 'MAD')) ?></span>
    </div>
</div>

<?php if (!empty($avec_gp) && !empty($row['nom'])): ?>
<div class="gp-nom">
    <?php if (!empty($row['photo'])): ?>
        <img src="uploads/<?= e($row['photo']) ?>" alt=""
             style="width:40px; height:40px; border-radius:50%; object-fit:cover; vertical-align:middle;">
    <?php else: ?>
        <span class="avatar-mini"><?= e(strtoupper(mb_substr($row['nom'], 0, 1))) ?></span>
    <?php endif; ?>
    <a href="profil.php?id=<?= (int)$row['user_id'] ?>">@<?= e($row['username'] ?? $row['nom']) ?></a>
    <?= badge_dispo_gp($row['disponible'] ?? 1) ?>
</div>
<?php endif; ?>

<div class="annonce-body">

    <?php if (!empty($row['pour_autrui']) && !empty($row['voyageur_nom'])): ?>
    <div class="info-ligne">
        <span><i class="fa-solid fa-user"></i> Voyageur :</span>
        <span><?= e($row['voyageur_nom']) ?> <small style="color:#94a3b8;">(annonce publiée pour lui/elle)</small></span>
    </div>
    <?php endif; ?>

    <div class="info-ligne">
        <span><i class="fa-solid fa-calendar"></i> Départ :</span>
        <span><?= e(date_heure_fr($row['date_depart'], $row['heure_depart'] ?? null)) ?></span>
    </div>

    <div class="info-ligne">
        <span><i class="fa-solid fa-calendar"></i> Arrivée :</span>
        <span><?= e(date_heure_fr($row['date_arrivee'], $row['heure_arrivee'] ?? null)) ?></span>
    </div>

    <?php if ($row['kilos_total'] !== null && $row['kilos_total'] !== ''): ?>
    <div class="info-ligne">
        <span><i class="fa-solid fa-weight-hanging"></i> Kilos :</span>
        <?php if ((float)$row['kilos_restants'] <= 0): ?>
            <span class="kilos-complet">Complet (<?= $kg($row['kilos_total']) ?> kg)</span>
        <?php else: ?>
            <span><strong><?= $kg($row['kilos_restants']) ?> kg disponibles</strong> sur <?= $kg($row['kilos_total']) ?> kg</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($row['date_limite_depot'])): ?>
    <div class="info-ligne">
        <span><i class="fa-solid fa-hourglass-half"></i> Dépôt jusqu'au :</span>
        <span <?= $depot_cloture ? 'class="kilos-complet"' : '' ?>>
            <?= date('d/m/Y', strtotime($row['date_limite_depot'])) ?><?= $depot_cloture ? ' (dépôts clôturés)' : '' ?>
        </span>
    </div>
    <?php endif; ?>

    <?php if (!empty($row['point_depot']) || !empty($row['horaires_depot'])): ?>
    <div class="info-ligne">
        <span><i class="fa-solid fa-box"></i> Dépôt :</span>
        <span>
            <?= e($row['point_depot']) ?>
            <?php if (!empty($row['horaires_depot'])): ?><small class="horaires"><i class="fa-regular fa-clock"></i> <?= e($row['horaires_depot']) ?></small><?php endif; ?>
        </span>
    </div>
    <?php endif; ?>

    <?php if (!empty($row['point_reception']) || !empty($row['horaires_reception'])): ?>
    <div class="info-ligne">
        <span><i class="fa-solid fa-location-dot"></i> Réception :</span>
        <span>
            <?= e($row['point_reception']) ?>
            <?php if (!empty($row['horaires_reception'])): ?><small class="horaires"><i class="fa-regular fa-clock"></i> <?= e($row['horaires_reception']) ?></small><?php endif; ?>
        </span>
    </div>
    <?php endif; ?>

    <?php if (!empty($row['commentaire'])): ?>
    <div class="info-ligne">
        <span><i class="fa-solid fa-comment"></i> Commentaire :</span>
        <span><?= e($row['commentaire']) ?></span>
    </div>
    <?php endif; ?>

</div>
