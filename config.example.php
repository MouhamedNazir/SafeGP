<?php
// ============================================================
// CONFIGURATION — copier ce fichier en config.php et le remplir.
// config.php n'est jamais envoyé sur GitHub (.gitignore).
// ============================================================

// ---------- Base de données ----------
$host = "localhost";
$user = "root";
$password = "votre_mot_de_passe";
$dbname = "safegp";

$conn = new mysqli($host, $user, $password, $dbname);

// ---------- Sécurité ----------
// true une fois le certificat SSL installé chez l'hébergeur :
// toute visite en http:// est alors redirigée vers https://
define('FORCER_HTTPS', false);

// true seulement pour déboguer en ligne : affiche les erreurs PHP aux visiteurs
define('MODE_DEBUG', false);

// Adresse publique du site, utilisée dans les liens des e-mails
define('SITE_URL', 'https://safegp.free.nf');

// ---------- E-mails (SMTP) ----------
// Nécessaire pour la confirmation d'inscription, le mot de passe oublié
// et les notifications. Laisser SMTP_HOTE vide pour désactiver.
// Exemple avec Gmail : activer la validation en 2 étapes, puis créer
// un « mot de passe d'application » sur https://myaccount.google.com/apppasswords
define('SMTP_HOTE', '');                 // ex : 'smtp.gmail.com' ou 'smtp-relay.brevo.com'
define('SMTP_PORT', 465);                // 465 (ssl) ou 587 (starttls)
define('SMTP_SECURITE', 'ssl');          // 'ssl' ou 'starttls'
define('SMTP_UTILISATEUR', '');          // ex : 'ton.adresse@gmail.com'
define('SMTP_MOT_DE_PASSE', '');         // mot de passe d'application, pas ton vrai mot de passe
define('EMAIL_EXPEDITEUR', '');          // adresse d'envoi (souvent la même que SMTP_UTILISATEUR)
define('EMAIL_NOM', 'SafeGP');
