<?php
// ============================================================
// ALERTES : créer (depuis la recherche) ou supprimer (depuis le profil)
// ============================================================
require_once 'init.php';
exiger_connexion();
$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: mon_profil.php#alertes");
    exit();
}

if (isset($_POST['supprimer'])) {
    $id = (int)$_POST['supprimer'];
    $stmt = $conn->prepare("DELETE FROM alertes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: mon_profil.php?alerte=supprimee#alertes");
    exit();
}

// Création : au moins un critère (pays ou ville)
$pays_depart = strtoupper(trim($_POST['pays_depart'] ?? ''));
$pays_arrivee = strtoupper(trim($_POST['pays_arrivee'] ?? ''));
$a = [
    'pays_depart'  => isset(PAYS[$pays_depart]) ? $pays_depart : null,
    'depart'       => vide_en_null(mb_substr($_POST['depart'] ?? '', 0, 100)),
    'pays_arrivee' => isset(PAYS[$pays_arrivee]) ? $pays_arrivee : null,
    'arrivee'      => vide_en_null(mb_substr($_POST['arrivee'] ?? '', 0, 100)),
];
$retour = 'search_trajet.php?' . http_build_query(array_filter($a));

$nb = (int)$conn->query("SELECT COUNT(*) FROM alertes WHERE user_id = " . $user_id)->fetch_row()[0];
if (!array_filter($a)) {
    $etat = 'vide';
} elseif ($nb >= ALERTES_MAX) {
    $etat = 'max';
} else {
    // Pas de doublon exact
    $stmt = $conn->prepare("SELECT id FROM alertes WHERE user_id = ? AND pays_depart <=> ? AND depart <=> ? AND pays_arrivee <=> ? AND arrivee <=> ?");
    $stmt->bind_param("issss", $user_id, $a['pays_depart'], $a['depart'], $a['pays_arrivee'], $a['arrivee']);
    $stmt->execute();
    $existe = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    if ($existe) {
        $etat = 'existe';
    } else {
        $stmt = $conn->prepare("INSERT INTO alertes (user_id, pays_depart, depart, pays_arrivee, arrivee) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $a['pays_depart'], $a['depart'], $a['pays_arrivee'], $a['arrivee']);
        $stmt->execute();
        $stmt->close();
        $etat = 'creee';
    }
}
$conn->close();
header("Location: " . $retour . "&alerte=" . $etat);
exit();
