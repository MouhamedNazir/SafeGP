<?php
// ============================================================
// INITIALISATION COMMUNE — à inclure en haut de chaque page,
// juste après config.php, AVANT tout affichage HTML.
// Rien n'est affiché ici : les redirections header() restent
// donc possibles ensuite. La barre de navigation (navbar.php)
// s'inclut, elle, après la balise <body>.
// ============================================================

require_once __DIR__ . '/csrf.php'; // démarre la session
require_once __DIR__ . '/fonctions.php';
csrf_verify(); // bloque tout POST sans jeton CSRF valide

// Rôle de l'utilisateur connecté, lu en base (pas seulement en session)
// tant que la connexion est ouverte : la navbar s'en sert pour le lien Admin
$nav_is_admin = false;
if (isset($_SESSION['user_id'])) {
    $check_admin = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $check_admin->bind_param("i", $_SESSION['user_id']);
    $check_admin->execute();
    $check_role = $check_admin->get_result()->fetch_assoc();
    $check_admin->close();
    $nav_is_admin = ($check_role['role'] ?? '') === 'admin';
}

// Pour l'admin : nombre de retours clients non lus (badge dans la navbar)
$nav_retours_non_lus = 0;
if ($nav_is_admin) {
    $nav_retours_non_lus = (int)$conn->query("SELECT COUNT(*) FROM retours WHERE lu = 0")->fetch_row()[0];
}
