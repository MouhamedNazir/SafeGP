<footer>
    <div class="footer-content">
        <div class="footer-logo">
            <img src="images/SafeGP_logo.png" alt="logo" class="footer-logo-img">
            <h2>Safe<span class="gp">GP</span></h2>
            <p>Envoyez vos colis partout dans le monde avec des voyageurs de confiance.</p>
        </div>

        <div class="footer-liens">
            <h4>Liens utiles</h4>
            <ul>
                <li><a href="index.php">Accueil</a></li>
                <li><a href="search_trajet.php">Rechercher un trajet</a></li>
                <li><a href="publier.php">Publier un trajet</a></li>
                <li><a href="tarifs.php">Tarifs</a></li>
                <li><a href="membres.php">Rechercher un membre</a></li>
                <li><a href="retours.php">Donner mon avis</a></li>
                <li><a href="inscription.php">S'inscrire</a></li>
                <li><a href="connexion.php">Connexion</a></li>
            </ul>
        </div>

        <div class="footer-contact">
            <h4>Contact</h4>
            <p><a href="retours.php"><i class="fa-solid fa-envelope"></i> Écrire à l'équipe</a></p>
            <?php foreach ((defined('CONTACT_TELEPHONES') ? CONTACT_TELEPHONES : []) as $tel): ?>
                <p><a href="tel:<?= e(preg_replace('/\s+/', '', $tel)) ?>"><i class="fa-solid fa-mobile-screen"></i> <?= e($tel) ?></a></p>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="footer-bas">
        <p>© <?= date('Y') ?> SafeGP — Tous droits réservés · <a href="conditions.php">Conditions d'utilisation</a></p>
        <p class="footer-dev">Conçu et développé par <strong>Mouhamed Nazirou Ndiaye</strong> ·
            <a href="https://www.linkedin.com/in/mouhamed-nazirou-ndiaye" target="_blank" rel="noopener"><i class="fa-brands fa-linkedin"></i> LinkedIn</a> ·
            <a href="https://github.com/MouhamedNazir" target="_blank" rel="noopener"><i class="fa-brands fa-github"></i> GitHub</a></p>
    </div>
</footer>