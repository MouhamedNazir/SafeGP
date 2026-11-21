<?php
// ============================================================
// INITIALISATION COMMUNE — à inclure tout en haut de chaque page :
//     require_once 'init.php';
// Charge la gestion des erreurs, la config (connexion à la base),
// la session et les fonctions. Rien n'est affiché ici : les
// redirections header() restent donc possibles ensuite.
// La barre de navigation (navbar.php) s'inclut après <body>.
// ============================================================

require_once __DIR__ . '/erreurs.php';   // en premier : capte aussi une panne de la base
require_once __DIR__ . '/langue.php';    // français / anglais (LANGUE)
require_once __DIR__ . '/config.php';    // $conn
forcer_https_si_demande();
$conn->set_charset('utf8mb4');

// Un seul fuseau horaire pour PHP et MySQL (UTC, comme Dakar) :
// les dates enregistrées et affichées restent cohérentes
date_default_timezone_set('Africa/Dakar');
$conn->query("SET time_zone = '+00:00'");

// En-têtes de sécurité : pas d'intégration du site dans une autre page
// (anti-clickjacking), pas de devinette du type de fichier, référent limité
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

require_once __DIR__ . '/csrf.php';      // démarre la session (cookie sécurisé)
demarrer_traduction();                    // en anglais : la page est traduite avant l'envoi
require_once __DIR__ . '/pays.php';
require_once __DIR__ . '/fonctions.php';
require_once __DIR__ . '/email.php';

// Une fois par jour : petites tâches de ménage (voir taches_quotidiennes())
$drapeau_maj = __DIR__ . '/logs/maj_quotidienne.txt';
if (@file_get_contents($drapeau_maj) !== date('Y-m-d')) {
    @file_put_contents($drapeau_maj, date('Y-m-d'));
    taches_quotidiennes($conn);
}
csrf_verify(); // bloque tout POST sans jeton CSRF valide

// Infos de l'utilisateur connecté pour la navbar, lues en base
// (pas seulement en session) tant que la connexion est ouverte
$nav_is_admin = false;
$nav_messages_non_lus = 0;
$nav_envois_a_traiter = 0;
$nav_abonnement_fin = null;
$nav_email_a_confirmer = false;
if (isset($_SESSION['user_id'])) {
    $check_admin = $conn->prepare("
        SELECT id, nom, email, role, email_verifie, formule, abonnement_fin, rappel_abonnement,
               (SELECT COUNT(*) FROM messages WHERE destinataire_id = users.id AND lu = 0) AS non_lus,
               (SELECT COUNT(*) FROM envois WHERE gp_id = users.id AND statut = 'demande') AS envois_a_traiter
        FROM users WHERE id = ?");
    $check_admin->bind_param("i", $_SESSION['user_id']);
    $check_admin->execute();
    $check_role = $check_admin->get_result()->fetch_assoc();
    $check_admin->close();

    // Changement de langue : mémorisé sur le compte (langue des e-mails)
    if (isset($_GET['lang']) && in_array($_GET['lang'], LANGUES, true)) {
        $maj_langue = $conn->prepare("UPDATE users SET langue = ? WHERE id = ?");
        $maj_langue->bind_param("si", $_GET['lang'], $_SESSION['user_id']);
        $maj_langue->execute();
        $maj_langue->close();
    }

    if (!$check_role) {
        // Compte supprimé entre-temps : on déconnecte
        session_unset();
    } else {
        $nav_is_admin = $check_role['role'] === 'admin';
        $nav_messages_non_lus = (int)$check_role['non_lus'];
        $nav_envois_a_traiter = (int)$check_role['envois_a_traiter'];
        $nav_email_a_confirmer = !$check_role['email_verifie'] && email_actif();

        // Abonnement qui se termine dans 3 jours ou moins : bandeau + e-mail (une fois)
        $fin = $check_role['abonnement_fin'];
        if ($check_role['formule'] !== 'gratuit' && $fin && $fin >= date('Y-m-d') && $fin <= date('Y-m-d', strtotime('+3 days'))) {
            $nav_abonnement_fin = $fin;
            if ($check_role['rappel_abonnement'] !== $fin) {
                $maj = $conn->prepare("UPDATE users SET rappel_abonnement = ? WHERE id = ?");
                $maj->bind_param("si", $fin, $check_role['id']);
                $maj->execute();
                $maj->close();
                email_envoyer($check_role['email'], 'Ton abonnement se termine bientôt - SafeGP', email_gabarit(
                    'Ton abonnement se termine le ' . date('d/m/Y', strtotime($fin)),
                    '<p>Bonjour ' . htmlspecialchars($check_role['nom']) . ',</p><p>Ta formule <strong>'
                    . htmlspecialchars(FORMULES[$check_role['formule']]['nom'] ?? '') . '</strong> se termine le <strong>'
                    . date('d/m/Y', strtotime($fin)) . '</strong>. Ensuite, ton compte repassera en formule Gratuite (4 publications par mois).</p>',
                    'Renouveler mon abonnement',
                    url_site() . '/tarifs.php'
                ));
            }
        }
    }
}

// Pour l'admin : retours clients non lus + paiements à vérifier (badge dans la navbar)
$nav_retours_non_lus = 0;
if ($nav_is_admin) {
    $nav_retours_non_lus = (int)$conn->query("SELECT COUNT(*) FROM retours WHERE lu = 0")->fetch_row()[0]
        + (int)$conn->query("SELECT COUNT(*) FROM abonnements WHERE statut = 'en_attente' OR (statut = 'annule' AND rembourse_le IS NULL)")->fetch_row()[0]
        + (int)$conn->query("SELECT COUNT(*) FROM verifications WHERE statut = 'en_attente'")->fetch_row()[0];
}
