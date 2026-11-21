<?php
require_once 'init.php';

// ============================================================
// RECHERCHE DE TRAJETS — tous les critères sont facultatifs
// pays et ville de départ / d'arrivée, période de départ.
// Par défaut, les trajets déjà partis sont masqués.
// ============================================================
$f = [
    'pays_depart'  => strtoupper(trim($_GET['pays_depart'] ?? '')),
    'depart'       => trim($_GET['depart'] ?? ''),
    'pays_arrivee' => strtoupper(trim($_GET['pays_arrivee'] ?? '')),
    'arrivee'      => trim($_GET['arrivee'] ?? ''),
    'du'           => trim($_GET['du'] ?? ''),
    'au'           => trim($_GET['au'] ?? ''),
    'passes'       => !empty($_GET['passes']),
];
if (!isset(PAYS[$f['pays_depart']])) $f['pays_depart'] = '';
if (!isset(PAYS[$f['pays_arrivee']])) $f['pays_arrivee'] = '';

// Raccourcis de période
$aujourdhui = date('Y-m-d');
if (($_GET['periode'] ?? '') === 'semaine') {
    $f['du'] = $aujourdhui;
    $f['au'] = date('Y-m-d', strtotime('+7 days'));
} elseif (($_GET['periode'] ?? '') === 'mois') {
    $f['du'] = $aujourdhui;
    $f['au'] = date('Y-m-d', strtotime('+1 month'));
}
$date_valide = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);
if (!$date_valide($f['du'])) $f['du'] = '';
if (!$date_valide($f['au'])) $f['au'] = '';
if ($f['du'] && $f['au'] && $f['au'] < $f['du']) [$f['du'], $f['au']] = [$f['au'], $f['du']];

// Construction de la requête : chaque critère ajoute une condition
$conditions = [];
$types = '';
$valeurs = [];
$ajouter = function (string $sql, string $type, $valeur) use (&$conditions, &$types, &$valeurs) {
    $conditions[] = $sql;
    $types .= $type;
    $valeurs[] = $valeur;
};
$motif = fn($texte) => '%' . addcslashes($texte, '%_\\') . '%';

if ($f['pays_depart'])  $ajouter('trajets.pays_depart = ?', 's', $f['pays_depart']);
if ($f['pays_arrivee']) $ajouter('trajets.pays_arrivee = ?', 's', $f['pays_arrivee']);
if ($f['depart'] !== '')  $ajouter('trajets.depart LIKE ?', 's', $motif($f['depart']));
if ($f['arrivee'] !== '') $ajouter('trajets.arrivee LIKE ?', 's', $motif($f['arrivee']));
if ($f['du']) {
    $ajouter('trajets.date_depart >= ?', 's', $f['du']);
} elseif (!$f['passes']) {
    $ajouter('trajets.date_depart >= ?', 's', $aujourdhui);
}
if ($f['au']) $ajouter('trajets.date_depart <= ?', 's', $f['au']);

$sql = "
    SELECT trajets.*, users.nom, users.photo, users.username, users.disponible
    FROM trajets
    LEFT JOIN users ON trajets.user_id = users.id
    " . ($conditions ? 'WHERE ' . implode(' AND ', $conditions) : '') . "
    ORDER BY trajets.date_depart ASC, trajets.id DESC
    LIMIT 100";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$valeurs);
$stmt->execute();
$trajets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Numéros WhatsApp à afficher pour chaque trajet
$contacts = contacts_pour_trajets($conn, $trajets);
$conn->close();

// Titre lisible de la recherche
$lieu = function ($ville, $pays) {
    if ($ville !== '' && $pays) return $ville . ' (' . nom_pays($pays) . ')';
    if ($ville !== '') return $ville;
    if ($pays) return nom_pays($pays);
    return 'Partout';
};
$titre = $lieu($f['depart'], $f['pays_depart']) . ' → ' . $lieu($f['arrivee'], $f['pays_arrivee']);
$mon_id = (int)($_SESSION['user_id'] ?? 0);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Rechercher un trajet - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

    <section class="resultats">
        <h2>Rechercher un trajet</h2>

        <!-- Filtres -->
        <form action="search_trajet.php" method="GET" class="filtres">
            <div class="filtres-ligne">
                <div class="filtre">
                    <label for="f-pays-depart">Pays de départ</label>
                    <select id="f-pays-depart" name="pays_depart"><?= options_pays($f['pays_depart'], 'Tous les pays') ?></select>
                </div>
                <div class="filtre">
                    <label for="f-depart">Ville de départ</label>
                    <input id="f-depart" type="text" name="depart" value="<?= e($f['depart']) ?>" placeholder="Toutes les villes">
                </div>
                <button type="button" class="filtre-inverser" title="Inverser départ et arrivée" aria-label="Inverser départ et arrivée">
                    <i class="fa-solid fa-right-left"></i>
                </button>
                <div class="filtre">
                    <label for="f-pays-arrivee">Pays d'arrivée</label>
                    <select id="f-pays-arrivee" name="pays_arrivee"><?= options_pays($f['pays_arrivee'], 'Tous les pays') ?></select>
                </div>
                <div class="filtre">
                    <label for="f-arrivee">Ville d'arrivée</label>
                    <input id="f-arrivee" type="text" name="arrivee" value="<?= e($f['arrivee']) ?>" placeholder="Toutes les villes">
                </div>
            </div>
            <div class="filtres-ligne">
                <div class="filtre">
                    <label for="f-du">Départ à partir du</label>
                    <input id="f-du" type="date" name="du" value="<?= e($f['du']) ?>">
                </div>
                <div class="filtre">
                    <label for="f-au">Jusqu'au</label>
                    <input id="f-au" type="date" name="au" value="<?= e($f['au']) ?>">
                </div>
                <div class="filtre filtre-raccourcis">
                    <span class="filtre-label">Raccourcis</span>
                    <div>
                        <button type="submit" name="periode" value="semaine" class="puce">Cette semaine</button>
                        <button type="submit" name="periode" value="mois" class="puce">Ce mois-ci</button>
                    </div>
                </div>
                <label class="case-a-cocher filtre-passes">
                    <input type="checkbox" name="passes" value="1" <?= $f['passes'] ? 'checked' : '' ?>>
                    Inclure les trajets passés
                </label>
            </div>
            <div class="filtres-actions">
                <a href="search_trajet.php" class="lien-discret">Effacer les filtres</a>
                <button type="submit" class="btn-contact"><i class="fa-solid fa-magnifying-glass"></i> Rechercher</button>
            </div>
        </form>

        <p class="resultats-resume">
            <strong><?= count($trajets) ?></strong> trajet<?= count($trajets) > 1 ? 's' : '' ?> · <?= e($titre) ?>
            <?php if ($f['du'] || $f['au']): ?>
                · départ <?= $f['du'] ? 'du ' . date('d/m/Y', strtotime($f['du'])) : '' ?> <?= $f['au'] ? 'au ' . date('d/m/Y', strtotime($f['au'])) : '' ?>
            <?php elseif (!$f['passes']): ?>
                · à venir
            <?php endif; ?>
        </p>

        <?php if ($trajets): ?>

            <?php foreach ($trajets as $row): ?>
                <div class="annonce">
                    <?php $avec_gp = true; include 'trajet_infos.php'; ?>

                    <div class="annonce-footer">
                        <?php if ($row['user_id'] && (int)$row['user_id'] !== $mon_id): ?>
                            <a href="messagerie.php?with=<?= (int)$row['user_id'] ?>&amp;trajet=<?= (int)$row['id'] ?>" class="btn-contact">
                                <i class="fa-solid fa-message"></i> Contacter pour ce trajet
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($row['telephone'])): ?>
                            <a href="tel:<?= e($row['telephone']) ?>" class="btn-contact btn-secondaire">
                                <i class="fa-solid fa-phone"></i> <?= e($row['telephone']) ?>
                            </a>
                        <?php endif; ?>

                        <?php afficher_boutons_whatsapp($contacts[$row['id']] ?? []); ?>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php else: ?>
            <div class="aucun-resultat">
                <p><i class="fa-solid fa-face-frown"></i> Aucun trajet ne correspond à ta recherche.</p>
                <p>Essaie d'élargir la période ou de retirer un filtre.</p>
            </div>
        <?php endif; ?>

    </section>

    <?php include 'footer.php'; ?>

</body>
</html>
