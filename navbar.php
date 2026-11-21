<?php
require_once __DIR__ . "/csrf.php";
csrf_verify(); // bloque tout POST sans jeton CSRF valide
?>

<nav>
    <div class="logo_container">
        <a href="index.php" style="display:flex; align-items:center; gap:10px; text-decoration:none; color:white;">
            <img src="images/SafeGP_logo.png" alt="SafeGP_logo" id="logo_img">
            <h1>Safe<span class="gp">GP</span></h1>
        </a>
    </div>

    <ul>
        <li><a href="index.php">Accueil</a></li>
        <li><a href="search_trajet.php">Rechercher</a></li>
        <li><a href="publier.php">Publier</a></li>
        <li><a href="messagerie.php"><i class="fa-solid fa-message"></i> Messages</a></li>

        <?php if (isset($_SESSION['user_id'])): ?>

            <?php
                // On vérifie le rôle de l'utilisateur connecté
                $check_admin = $conn->prepare("SELECT role FROM users WHERE id = ?");
                $check_admin->bind_param("i", $_SESSION['user_id']);
                $check_admin->execute();
                $check_role = $check_admin->get_result()->fetch_assoc();
                $check_admin->close();
            ?>

            <li class="dropdown">
                <span class="user-nom">
                    <?php if (!empty($_SESSION['user_photo'])): ?>
                        <img src="uploads/<?= htmlspecialchars($_SESSION['user_photo']) ?>" 
                             style="width:35px; height:35px; border-radius:50%; object-fit:cover; vertical-align:middle;">
                    <?php else: ?>
                        <span class="avatar-mini"><?= strtoupper(substr($_SESSION['user_nom'], 0, 1)) ?></span>
                    <?php endif; ?>
                    <?= htmlspecialchars($_SESSION['user_nom']) ?>
                    <i class="fa-solid fa-chevron-down" style="font-size:11px;"></i>
                </span>
                <ul class="dropdown-menu">
                    <li><a href="mon_profil.php"><i class="fa-solid fa-user"></i> Mon profil</a></li>
                    <li><a href="dashboard.php"><i class="fa-solid fa-list"></i> Mes trajets</a></li>
                    <li><a href="publier.php"><i class="fa-solid fa-plus"></i> Publier</a></li>
                    <hr style="border-color:#0f172a;">
                    <?php if ($check_role['role'] == 'admin'): ?>
                        <li><a href="admin.php"><i class="fa-solid fa-shield"></i> Admin</a></li>
                    <?php endif; ?>
                    <li><a href="deconnexion.php" style="color:#f97316;"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a></li>
                </ul>
            </li>

        <?php else: ?>
            <li><a href="connexion.php">Connexion</a></li>
            <li><a href="inscription.php" class="btn-nav-orange">S'inscrire</a></li>
        <?php endif; ?>
    </ul>

    <script>
        // Au clic sur le nom → on toggle le dropdown
        document.querySelector('.dropdown .user-nom').addEventListener('click', function(e) {
            e.stopPropagation();
            document.querySelector('.dropdown-menu').classList.toggle('show');
        });

        // Clic ailleurs → ferme le dropdown
        document.addEventListener('click', function() {
            document.querySelector('.dropdown-menu').classList.remove('show');
        });
    </script>
</nav>