<?php // Barre de navigation : à inclure juste après <body> (init.php doit être inclus avant) ?>
<nav>
    <div class="logo_container">
        <a href="index.php" style="display:flex; align-items:center; gap:10px; text-decoration:none; color:white;">
            <img src="images/SafeGP_logo.png" alt="SafeGP_logo" id="logo_img">
            <h1>Safe<span class="gp">GP</span></h1>
        </a>
    </div>

    <!-- Bouton menu (visible seulement sur mobile) -->
    <button type="button" class="nav-burger" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="nav-liens">
        <i class="fa-solid fa-bars"></i>
        <?php if (!empty($nav_messages_non_lus) || !empty($nav_retours_non_lus)): ?><span class="burger-point"></span><?php endif; ?>
    </button>

    <ul id="nav-liens">
        <li><a href="index.php">Accueil</a></li>
        <li><a href="search_trajet.php">Rechercher</a></li>
        <li><a href="membres.php"><i class="fa-solid fa-users"></i> Membres</a></li>
        <li><a href="publier.php">Publier</a></li>
        <li><a href="messagerie.php"><i class="fa-solid fa-message"></i> Messages<?php if (!empty($nav_messages_non_lus)): ?><span class="badge-nonlu" title="Messages non lus"><?= (int)$nav_messages_non_lus ?></span><?php endif; ?></a></li>

        <?php if (isset($_SESSION['user_id'])): ?>

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
                    <?php if (!empty($nav_is_admin)): ?>
                        <li><a href="admin.php"><i class="fa-solid fa-shield"></i> Admin
                            <?php if (!empty($nav_retours_non_lus)): ?><span class="badge-nonlu" title="Retours clients non lus"><?= (int)$nav_retours_non_lus ?></span><?php endif; ?>
                        </a></li>
                    <?php endif; ?>
                    <li><a href="retours.php"><i class="fa-solid fa-lightbulb"></i> Donner mon avis</a></li>
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
        // (le menu n'existe que si l'utilisateur est connecté)
        const navUser = document.querySelector('.dropdown .user-nom');
        const navMenu = document.querySelector('.dropdown-menu');
        if (navUser && navMenu) {
            navUser.addEventListener('click', function(e) {
                e.stopPropagation();
                navMenu.classList.toggle('show');
            });

            // Clic ailleurs → ferme le dropdown
            document.addEventListener('click', function() {
                navMenu.classList.remove('show');
            });
        }

        // Menu mobile : le bouton ☰ ouvre / ferme la liste des liens
        const burger = document.querySelector('.nav-burger');
        const navLiens = document.getElementById('nav-liens');
        burger.addEventListener('click', function(e) {
            e.stopPropagation();
            const ouvert = navLiens.classList.toggle('ouvert');
            burger.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
            burger.querySelector('i').className = ouvert ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
        });
    </script>
</nav>

<?php if (!empty($nav_email_a_confirmer)): ?>
<div class="bandeau-info">
    <i class="fa-solid fa-envelope"></i>
    Confirme ton adresse e-mail grâce au lien que nous t'avons envoyé pour pouvoir publier des trajets.
    <form action="renvoyer_confirmation.php" method="POST">
        <?= csrf_field() ?>
        <button type="submit" class="btn-lien">Renvoyer l'e-mail</button>
    </form>
</div>
<?php endif; ?>
<?php if (isset($_GET['confirmation'])): ?>
<div class="bandeau-info">
    <?php
        $msgs_confirmation = [
            'envoye'   => "E-mail de confirmation renvoyé. Pense à regarder dans les spams.",
            'deja'     => "Ton adresse e-mail est déjà confirmée.",
            'attendre' => "Patiente une minute avant de redemander l'e-mail.",
            'erreur'   => "L'e-mail n'a pas pu être envoyé, réessaie plus tard.",
        ];
        echo e($msgs_confirmation[$_GET['confirmation']] ?? '');
    ?>
</div>
<?php endif; ?>

