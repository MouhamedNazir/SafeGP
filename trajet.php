<?php
// ============================================================
// PAGE D'UNE ANNONCE (lien unique à partager)
// trajet.php?id=12 — avec un aperçu soigné sur WhatsApp / Facebook
// ============================================================
require_once 'init.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("
    SELECT trajets.*, users.nom, users.photo, users.username, users.disponible, users.verifie
    FROM trajets LEFT JOIN users ON users.id = trajets.user_id
    WHERE trajets.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $titre_404 = 'Annonce introuvable';
    $texte_404 = "Ce trajet a été supprimé par son GP, ou le lien est incorrect.";
    include '404.php';
}

$contacts = contacts_pour_trajets($conn, [$row]);
$stats_gp = avis_stats($conn, [$row['user_id']]);

// Autres trajets à venir du même GP
$stmt = $conn->prepare("SELECT id, depart, pays_depart, arrivee, pays_arrivee, date_depart, prix_kilo, devise
    FROM trajets WHERE user_id = ? AND id <> ? AND date_depart >= CURDATE() ORDER BY date_depart LIMIT 3");
$stmt->bind_param("ii", $row['user_id'], $id);
$stmt->execute();
$autres = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$mon_id = (int)($_SESSION['user_id'] ?? 0);
$passe = $row['date_arrivee'] < date('Y-m-d');

// Aperçu du lien partagé
$titre_trajet = $row['depart'] . ' → ' . $row['arrivee'];
$og_title = $titre_trajet . ' · ' . date('d/m/Y', strtotime($row['date_depart'])) . ' · ' . prix_kilo_fr($row['prix_kilo'], $row['devise']);
$og_description = 'GP : ' . ($row['nom'] ?? 'SafeGP')
    . ($row['kilos_total'] !== null ? ' · ' . ((float)$row['kilos_restants'] + 0) . ' kg disponibles' : '')
    . ($row['date_limite_depot'] ? ' · dépôt jusqu\'au ' . date('d/m/Y', strtotime($row['date_limite_depot'])) : '')
    . ' — Réservez votre place sur SafeGP.';
$lien = url_site() . '/trajet.php?id=' . $id;
$texte_partage = "✈️ " . $titre_trajet . " le " . date('d/m/Y', strtotime($row['date_depart'])) . " — " . prix_kilo_fr($row['prix_kilo'], $row['devise']) . "\n" . $lien;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title><?= e($titre_trajet) ?> - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

<section class="resultats page-trajet">
    <p class="fil-ariane"><a href="search_trajet.php">Trajets</a> › <?= e($titre_trajet) ?></p>

    <?php if ($passe): ?>
        <p class="quota quota-atteint"><i class="fa-solid fa-clock-rotate-left"></i> Ce trajet est terminé.
            <a href="search_trajet.php?pays_depart=<?= e($row['pays_depart']) ?>&amp;pays_arrivee=<?= e($row['pays_arrivee']) ?>">Voir les prochains trajets sur cette route</a></p>
    <?php endif; ?>

    <div class="annonce">
        <?php $avec_gp = true; include 'trajet_infos.php'; ?>

        <div class="annonce-footer">
            <?php if ($row['user_id'] && (int)$row['user_id'] !== $mon_id): ?>
                <a href="messagerie.php?with=<?= (int)$row['user_id'] ?>&amp;trajet=<?= $id ?>" class="btn-contact">
                    <i class="fa-solid fa-message"></i> Contacter le GP
                </a>
            <?php elseif ((int)$row['user_id'] === $mon_id): ?>
                <a href="modifier_trajet.php?id=<?= $id ?>" class="btn-contact btn-secondaire"><i class="fa-solid fa-pen"></i> Modifier</a>
            <?php endif; ?>
            <?php if (!empty($row['telephone'])): ?>
                <a href="tel:<?= e($row['telephone']) ?>" class="btn-contact btn-secondaire"><i class="fa-solid fa-phone"></i> <?= e($row['telephone']) ?></a>
            <?php endif; ?>
            <?php afficher_boutons_whatsapp($contacts[$id] ?? []); ?>
        </div>
    </div>

    <!-- Partager -->
    <div class="partage">
        <span><i class="fa-solid fa-share-nodes"></i> Partager cette annonce</span>
        <div class="partage-boutons">
            <a class="btn-contact btn-whatsapp" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($texte_partage) ?>"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
            <a class="btn-contact btn-facebook" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($lien) ?>"><i class="fa-brands fa-facebook-f"></i> Facebook</a>
            <button type="button" class="btn-contact btn-secondaire copier" data-copier="<?= e($lien) ?>"><i class="fa-regular fa-copy"></i> Copier le lien</button>
            <button type="button" class="btn-contact btn-secondaire partage-natif" hidden
                    data-titre="<?= e($og_title) ?>" data-texte="<?= e($texte_partage) ?>" data-lien="<?= e($lien) ?>"><i class="fa-solid fa-arrow-up-from-bracket"></i> Plus…</button>
        </div>
    </div>

    <?php if ($autres): ?>
        <h3 class="titre-section">Autres trajets de ce GP</h3>
        <div class="grille-trajets">
            <?php foreach ($autres as $t): ?>
                <a href="trajet.php?id=<?= (int)$t['id'] ?>" class="carte-trajet">
                    <div class="carte-trajet-route">
                        <span><?= drapeau($t['pays_depart']) ?> <?= e($t['depart']) ?></span>
                        <i class="fa-solid fa-plane"></i>
                        <span><?= drapeau($t['pays_arrivee']) ?> <?= e($t['arrivee']) ?></span>
                    </div>
                    <div class="carte-trajet-bas">
                        <span class="carte-trajet-gp"><i class="fa-regular fa-calendar"></i> <?= date('d/m/Y', strtotime($t['date_depart'])) ?></span>
                        <span class="prix"><?= e(prix_kilo_fr($t['prix_kilo'], $t['devise'])) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p style="margin-top:30px;"><a href="profil.php?id=<?= (int)$row['user_id'] ?>" class="lien-fleche">Voir le profil et les avis du GP <i class="fa-solid fa-arrow-right"></i></a>
        <a href="retours.php?type=probleme&amp;trajet=<?= $id ?>" class="lien-signaler"><i class="fa-regular fa-flag"></i> Signaler</a></p>
</section>

<?php include 'footer.php'; ?>
</body>
</html>
