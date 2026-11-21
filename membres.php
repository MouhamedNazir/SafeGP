<?php
// ============================================================
// RECHERCHE D'UTILISATEURS — par nom ou @username
// Sans recherche : les derniers inscrits
// ============================================================
require_once 'init.php';

$q = trim($_GET['q'] ?? '');
$q_sql = ltrim($q, '@'); // "@moussa" → "moussa"

$requete = "
    SELECT users.id, users.nom, users.username, users.photo, users.disponible,
           COUNT(trajets.id) AS nb_trajets,
           SUM(trajets.statut = 'disponible') AS nb_dispo
    FROM users
    LEFT JOIN trajets ON trajets.user_id = users.id
";

if ($q_sql !== '') {
    // On échappe % et _ pour qu'ils soient cherchés tels quels
    $motif = '%' . addcslashes($q_sql, '%_\\') . '%';
    $stmt = $conn->prepare($requete . "
        WHERE users.nom LIKE ? OR users.username LIKE ?
        GROUP BY users.id
        ORDER BY users.disponible DESC, nb_dispo DESC, users.nom ASC
        LIMIT 50");
    $stmt->bind_param("ss", $motif, $motif);
} else {
    $stmt = $conn->prepare($requete . "
        GROUP BY users.id
        ORDER BY users.created_at DESC
        LIMIT 12");
}
$stmt->execute();
$membres = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Membres - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

    <section class="search">
        <h2>Rechercher un membre</h2>
        <form action="membres.php" method="GET">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom ou @nom d'utilisateur" autofocus>
            <button type="submit">Rechercher</button>
        </form>
    </section>

    <section class="gps-actifs">
        <?php if ($q !== ''): ?>
            <p class="sous-titre"><?= count($membres) ?> résultat<?= count($membres) > 1 ? 's' : '' ?> pour « <?= e($q) ?> »</p>
        <?php else: ?>
            <p class="sous-titre">Derniers inscrits</p>
        <?php endif; ?>

        <?php if ($membres): ?>
        <div class="gps-grid">
            <?php foreach ($membres as $m): ?>
            <a href="profil.php?id=<?= (int)$m['id'] ?>" class="gp-card">
                <div class="gp-card-avatar">
                    <?= avatar($m['nom'], $m['photo']) ?>
                </div>
                <div class="gp-card-info">
                    <h4><?= e($m['nom']) ?></h4>
                    <?php if (!empty($m['username'])): ?>
                        <p style="color:var(--orange);">@<?= e($m['username']) ?></p>
                    <?php endif; ?>
                    <p><?= badge_dispo_gp($m['disponible']) ?></p>
                    <p class="gp-card-trajet">
                        <i class="fa-solid fa-plane"></i>
                        <?= (int)$m['nb_trajets'] ?> trajet<?= $m['nb_trajets'] > 1 ? 's' : '' ?>
                        <?php if ($m['nb_dispo']): ?> · <?= (int)$m['nb_dispo'] ?> disponible<?= $m['nb_dispo'] > 1 ? 's' : '' ?><?php endif; ?>
                    </p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="aucun-resultat">
                <p><i class="fa-solid fa-face-frown"></i> Aucun membre trouvé.</p>
            </div>
        <?php endif; ?>
    </section>

<?php include 'footer.php'; ?>
</body>
</html>
