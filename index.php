<?php
include 'config.php';
include 'init.php';

// On récupère les 6 derniers GPs qui ont des trajets disponibles
$stmt = $conn->prepare("
    SELECT users.id, users.nom, users.username, users.photo, 
           trajets.depart, trajets.arrivee
    FROM users 
    INNER JOIN trajets ON users.id = trajets.user_id
    WHERE trajets.statut = 'disponible'
    GROUP BY users.id
    ORDER BY trajets.id DESC
    LIMIT 6
");
$stmt->execute();
$gps = $stmt->get_result();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<?php include 'head.php'; ?>
    <title>SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

    

    <!-- HERO -->
    <section class="hero">
        <h2>Trouvez un transporteur rapidement</h2>
        <p>Envoyez vos colis facilement Partout dans le monde <br> Trouvez des Transporteurs plus facilement.</p>
        <button onclick="window.location.href='#recherche'">Trouver un GP</button>
    </section>

    <!-- SEARCH -->
    <section class="search" id="recherche">
        <h2>Rechercher un trajet</h2>
        <!--
        <form>
            <input type="text" placeholder="Ville de départ">
            <input type="text" placeholder="Ville d'arrivée">
            <button type="submit">Rechercher</button>
        </form>
    
    -->

        <form action="search_trajet.php" method="GET">
            <input type="text" name="depart" placeholder="Ville de départ" required>
            <input type="text" name="arrivee" placeholder="Ville d'arrivée" required>
            <button type="submit">Rechercher</button>
        </form>
    </section>
    <!-- INFO -->
    <section class="info">
        <div class="card">
            <h3>Rapide</h3>
            <p>Trouvez un transporteur en quelques clics</p>
        </div>

        <div class="card">
            <h3>Sécurisé</h3>
            <p>Des utilisateurs vérifiés</p>
        </div>

        <div class="card">
            <h3>Simple</h3>
            <p>Interface facile à utiliser</p>
        </div>
    </section>

    <!-- SECTION GPS ACTIFS -->
<section class="gps-actifs">
    <h2>GPs disponibles</h2>
    <p class="sous-titre">Transporteurs actifs en ce moment</p>

    <div class="gps-grid">
        <?php while($gp = $gps->fetch_assoc()): ?>
        <a href="profil.php?id=<?= $gp['id'] ?>" class="gp-card">
            
            <!-- Photo ou initiale -->
            <div class="gp-card-avatar">
                <?php if (!empty($gp['photo'])): ?>
                    <img src="uploads/<?= htmlspecialchars($gp['photo']) ?>" 
                         alt="photo"
                         style="width:70px; height:70px; border-radius:50%; object-fit:cover;">
                <?php else: ?>
                    <?= strtoupper(substr($gp['nom'], 0, 1)) ?>
                <?php endif; ?>
            </div>

            <!-- Infos -->
            <div class="gp-card-info">
                <h4><?= htmlspecialchars($gp['nom']) ?></h4>
                <p style="color:#f97316;">@<?= htmlspecialchars($gp['username'] ?? '') ?></p>
                <p class="gp-card-trajet">
                    <i class="fa-solid fa-plane-departure"></i>
                    <?= htmlspecialchars($gp['depart']) ?>
                    →
                    <i class="fa-solid fa-plane-arrival"></i>
                    <?= htmlspecialchars($gp['arrivee']) ?>
                </p>
            </div>

        </a>
        <?php endwhile; ?>
    </div>
</section>
<?php include 'footer.php'; ?>
</body>
</html>