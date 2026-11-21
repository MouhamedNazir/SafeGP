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
require_once __DIR__ . '/config.php';    // $conn
forcer_https_si_demande();
$conn->set_charset('utf8mb4');

require_once __DIR__ . '/csrf.php';      // démarre la session (cookie sécurisé)
require_once __DIR__ . '/pays.php';
require_once __DIR__ . '/fonctions.php';
require_once __DIR__ . '/email.php';
csrf_verify(); // bloque tout POST sans jeton CSRF valide

// Infos de l'utilisateur connecté pour la navbar, lues en base
// (pas seulement en session) tant que la connexion est ouverte
$nav_is_admin = false;
$nav_messages_non_lus = 0;
$nav_email_a_confirmer = false;
if (isset($_SESSION['user_id'])) {
    $check_admin = $conn->prepare("
        SELECT role, email_verifie,
               (SELECT COUNT(*) FROM messages WHERE destinataire_id = users.id AND lu = 0) AS non_lus
        FROM users WHERE id = ?");
    $check_admin->bind_param("i", $_SESSION['user_id']);
    $check_admin->execute();
    $check_role = $check_admin->get_result()->fetch_assoc();
    $check_admin->close();

    if (!$check_role) {
        // Compte supprimé entre-temps : on déconnecte
        session_unset();
    } else {
        $nav_is_admin = $check_role['role'] === 'admin';
        $nav_messages_non_lus = (int)$check_role['non_lus'];
        $nav_email_a_confirmer = !$check_role['email_verifie'] && email_actif();
    }
}

// Pour l'admin : nombre de retours clients non lus (badge dans la navbar)
$nav_retours_non_lus = 0;
if ($nav_is_admin) {
    $nav_retours_non_lus = (int)$conn->query("SELECT COUNT(*) FROM retours WHERE lu = 0")->fetch_row()[0];
}
