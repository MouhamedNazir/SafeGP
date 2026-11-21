<?php
require_once 'init.php';

// Prochains départs disponibles (les 6 plus proches)
$aujourdhui = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT trajets.id, trajets.depart, trajets.pays_depart, trajets.arrivee, trajets.pays_arrivee,
           trajets.date_depart, trajets.prix_kilo, trajets.devise, trajets.kilos_total, trajets.kilos_restants,
           users.id AS gp_id, users.nom, users.username, users.photo
    FROM trajets
    INNER JOIN users ON users.id = trajets.user_id
    WHERE trajets.statut = 'disponible' AND users.disponible = 1 AND trajets.date_depart >= ?
    ORDER BY trajets.date_depart ASC
    LIMIT 6
");
$stmt->bind_param("s", $aujourdhui);
$stmt->execute();
$prochains = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// GPs disponibles ayant au moins un trajet disponible (le plus récent en premier)
$gps = $conn->query("
    SELECT users.id, users.nom, users.username, users.photo,
           COUNT(trajets.id) AS nb_trajets, MAX(trajets.id) AS dernier
    FROM users
    INNER JOIN trajets ON users.id = trajets.user_id
    WHERE trajets.statut = 'disponible' AND users.disponible = 1
    GROUP BY users.id, users.nom, users.username, users.photo
    ORDER BY dernier DESC
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// Quelques chiffres pour rassurer les visiteurs
$stats = $conn->query("
    SELECT (SELECT COUNT(*) FROM users) AS membres,
           (SELECT COUNT(*) FROM trajets) AS trajets,
           (SELECT COUNT(DISTINCT pays) FROM (SELECT pays_depart AS pays FROM trajets UNION SELECT pays_arrivee FROM trajets) p WHERE pays IS NOT NULL) AS pays
")->fetch_assoc();
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<?php include 'head.php'; ?>
    <title>SafeGP — Envoyez vos colis partout dans le monde</title>
</head>
<body>
<?php include 'navbar.php'; ?>
<?php if (isset($_GET['compte']) && $_GET['compte'] === 'supprime'): ?>
    <div class="bandeau-info">Ton compte a bien été supprimé. Merci d'avoir utilisé SafeGP.</div>
<?php endif; ?>

    <!-- HERO -->
    <section class="hero">
        <div class="hero-decor" aria-hidden="true">
            <!-- Trajectoire d'avion en pointillés -->
            <svg viewBox="0 0 1200 400" preserveAspectRatio="none">
                <path d="M -20 330 C 250 80, 500 360, 760 150 S 1100 60, 1230 120" class="hero-trajectoire"/>
            </svg>
            <i class="fa-solid fa-plane hero-avion"></i>
            <i class="fa-solid fa-earth-africa hero-globe"></i>
        </div>

        <p class="hero-surtitre"><i class="fa-solid fa-shield-halved"></i> Des voyageurs de confiance, partout dans le monde</p>
        <h2>Envoyez vos colis avec un <span class="texte-orange">GP</span>,<br>où que vous soyez</h2>
        <p class="hero-texte">Trouvez un voyageur qui fait le trajet, comparez les prix au kilo et échangez directement avec lui.</p>

        <!-- Recherche rapide -->
        <form action="search_trajet.php" method="GET" class="hero-recherche" id="recherche">
            <div class="hero-champ">
                <label for="h-pays-depart"><i class="fa-solid fa-plane-departure"></i> Départ</label>
                <select id="h-pays-depart" name="pays_depart"><?= options_pays(null, 'Tous les pays') ?></select>
            </div>
            <div class="hero-champ">
                <label for="h-pays-arrivee"><i class="fa-solid fa-plane-arrival"></i> Arrivée</label>
                <select id="h-pays-arrivee" name="pays_arrivee"><?= options_pays(null, 'Tous les pays') ?></select>
            </div>
            <div class="hero-champ">
                <label for="h-du"><i class="fa-regular fa-calendar"></i> À partir du</label>
                <input id="h-du" type="date" name="du" min="<?= $aujourdhui ?>">
            </div>
            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Rechercher</button>
        </form>

        <div class="hero-chiffres">
            <div><strong><?= (int)$stats['membres'] ?></strong><span>membres</span></div>
            <div><strong><?= (int)$stats['trajets'] ?></strong><span>trajets publiés</span></div>
            <div><strong><?= (int)$stats['pays'] ?></strong><span>pays desservis</span></div>
        </div>
    </section>

    <!-- PROCHAINS DÉPARTS -->
    <?php if ($prochains): ?>
    <section class="bloc-accueil">
        <div class="bloc-titre">
            <h2>Prochains départs</h2>
            <a href="search_trajet.php" class="lien-fleche">Tous les trajets <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="grille-trajets">
            <?php foreach ($prochains as $t): ?>
            <a href="profil.php?id=<?= (int)$t['gp_id'] ?>#trajet-<?= (int)$t['id'] ?>" class="carte-trajet">
                <div class="carte-trajet-route">
                    <span><?= drapeau($t['pays_depart']) ?> <?= e($t['depart']) ?></span>
                    <i class="fa-solid fa-plane"></i>
                    <span><?= drapeau($t['pays_arrivee']) ?> <?= e($t['arrivee']) ?></span>
                </div>
                <div class="carte-trajet-infos">
                    <span><i class="fa-regular fa-calendar"></i> <?= date('d/m/Y', strtotime($t['date_depart'])) ?></span>
                    <?php if ($t['kilos_total'] !== null): ?>
                        <span><i class="fa-solid fa-weight-hanging"></i> <?= (float)$t['kilos_restants'] + 0 ?> kg dispo</span>
                    <?php endif; ?>
                </div>
                <div class="carte-trajet-bas">
                    <span class="carte-trajet-gp">@<?= e($t['username'] ?? $t['nom']) ?></span>
                    <span class="prix"><?= e(prix_kilo_fr($t['prix_kilo'], $t['devise'])) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- COMMENT ÇA MARCHE -->
    <section class="bloc-accueil">
        <div class="bloc-titre"><h2>Comment ça marche ?</h2></div>
        <div class="etapes">
            <div class="etape">
                <span class="etape-numero">1</span>
                <i class="fa-solid fa-magnifying-glass-location"></i>
                <h3>Cherchez un trajet</h3>
                <p>Choisissez les pays, la ville et la période : SafeGP vous montre les GP qui font le voyage.</p>
            </div>
            <div class="etape">
                <span class="etape-numero">2</span>
                <i class="fa-solid fa-comments"></i>
                <h3>Discutez avec le GP</h3>
                <p>Posez vos questions dans la messagerie ou sur WhatsApp : poids, prix, point de dépôt.</p>
            </div>
            <div class="etape">
                <span class="etape-numero">3</span>
                <i class="fa-solid fa-box-open"></i>
                <h3>Déposez votre colis</h3>
                <p>Remettez votre colis au point de dépôt convenu, il est livré à destination.</p>
            </div>
        </div>
        <p class="etapes-gp">Vous voyagez bientôt ? <a href="publier.php">Publiez votre trajet</a> et rentabilisez vos kilos libres.</p>
    </section>

    <!-- GPS DISPONIBLES -->
    <?php if ($gps): ?>
    <section class="bloc-accueil gps-actifs">
        <div class="bloc-titre">
            <h2>GPs disponibles</h2>
            <a href="membres.php" class="lien-fleche">Tous les membres <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="gps-grid">
            <?php foreach ($gps as $gp): ?>
            <a href="profil.php?id=<?= (int)$gp['id'] ?>" class="gp-card">
                <div class="gp-card-avatar">
                    <?= avatar($gp['nom'], $gp['photo']) ?>
                </div>
                <div class="gp-card-info">
                    <h4><?= e($gp['nom']) ?></h4>
                    <p class="texte-orange">@<?= e($gp['username'] ?? '') ?></p>
                    <p class="gp-card-trajet"><i class="fa-solid fa-plane"></i> <?= (int)$gp['nb_trajets'] ?> trajet<?= $gp['nb_trajets'] > 1 ? 's' : '' ?> disponible<?= $gp['nb_trajets'] > 1 ? 's' : '' ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

<?php include 'footer.php'; ?>
</body>
</html>
