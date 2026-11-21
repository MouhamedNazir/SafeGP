<?php
// ============================================================
// CONDITIONS D'UTILISATION ET DE VENTE
// Modèle de base à relire et adapter (ce n'est pas un avis juridique)
// ============================================================
require_once 'init.php';
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Conditions d'utilisation - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

<section class="page-texte">
    <h2>Conditions d'utilisation</h2>
    <p class="texte-doux-bloc">Dernière mise à jour : <?= date('d/m/Y', filemtime(__FILE__)) ?></p>

    <h3>1. Le service</h3>
    <p>SafeGP met en relation des voyageurs qui disposent de place dans leurs bagages (« GP ») et des personnes qui souhaitent envoyer des colis.
        SafeGP est uniquement un intermédiaire : la plateforme ne transporte aucun colis, n'est pas partie aux accords passés entre membres
        et ne perçoit aucune somme sur ces transports.</p>

    <h3>2. Responsabilité des membres</h3>
    <ul>
        <li>Chaque membre est responsable des informations qu'il publie (trajets, prix, numéros de contact).</li>
        <li>Le GP et l'expéditeur conviennent librement du prix, du poids, du dépôt et de la livraison. Il leur appartient de vérifier le contenu des colis.</li>
        <li>Il est interdit de transporter ou d'envoyer des objets illégaux, dangereux ou réglementés (drogues, armes, contrefaçons, produits inflammables, argent liquide non déclaré...). Les lois douanières des pays de départ et d'arrivée s'appliquent.</li>
        <li>SafeGP ne peut être tenu responsable d'une perte, d'un retard, d'un dommage ou d'un litige entre membres.</li>
    </ul>

    <h3>3. Compte</h3>
    <p>Tu dois fournir une adresse e-mail valide et garder ton mot de passe secret. Tu peux supprimer ton compte à tout moment depuis « Mon profil ».
        SafeGP peut suspendre ou supprimer un compte en cas d'abus, de fraude ou de contenu signalé.</p>

    <h3>4. Formules et abonnements</h3>
    <ul>
        <li><strong>Gratuit</strong> : <?= FORMULES['gratuit']['limite'] ?> trajets publiés par mois calendaire.</li>
        <li><strong>Standard</strong> : <?= FORMULES['standard']['limite'] ?> trajets par mois — <?= e(prix_fcfa(FORMULES['standard']['prix_mois'])) ?>/mois ou <?= e(prix_fcfa(FORMULES['standard']['prix_an'])) ?>/an.</li>
        <li><strong>Illimité</strong> : trajets illimités — <?= e(prix_fcfa(FORMULES['illimite']['prix_mois'])) ?>/mois ou <?= e(prix_fcfa(FORMULES['illimite']['prix_an'])) ?>/an.</li>
        <li>Le paiement se fait par mobile money (Wave, Orange Money). L'abonnement est activé après vérification du paiement, pour la durée choisie, sans renouvellement automatique.</li>
        <li>À la fin de la période, le compte repasse en formule Gratuite ; les trajets déjà publiés restent en ligne.</li>
        <li>Une période commencée n'est pas remboursable, sauf erreur de notre part. Pour toute réclamation, utilise la page <a href="retours.php?type=autre">Donner mon avis</a>.</li>
    </ul>

    <h3>5. Données personnelles</h3>
    <p>Nous conservons les informations nécessaires au service : nom, nom d'utilisateur, e-mail, numéros de contact, photo, trajets, messages et paiements.
        Les numéros affichés sur tes annonces sont visibles par les visiteurs. Tes données ne sont ni vendues ni cédées.
        La suppression de ton compte efface ton profil, tes trajets, tes numéros et tes messages.</p>

    <h3>6. Contact</h3>
    <p>Pour toute question : <a href="retours.php">Donner mon avis</a>.</p>
</section>

<?php include 'footer.php'; ?>
</body>
</html>
