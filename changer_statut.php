<?php
require_once 'init.php'; // session + jeton CSRF vérifié

exiger_connexion();

// Uniquement en POST, avec un jeton CSRF valide
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: dashboard.php");
    exit();
}
csrf_verify();

$user_id = $_SESSION['user_id'];
$id_trajet = (int)($_POST['id_trajet'] ?? 0);
$statut = $_POST['statut'] ?? '';

$statuts_autorises = ['disponible', 'en_route', 'arrivé'];

if (in_array($statut, $statuts_autorises, true)) {
    $stmt = $conn->prepare("UPDATE trajets SET statut = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $statut, $id_trajet, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Kilos encore disponibles (si le trajet a une capacité renseignée)
if (isset($_POST['kilos_restants']) && $_POST['kilos_restants'] !== '') {
    $kilos = $_POST['kilos_restants'];
    $stmt = $conn->prepare("SELECT kilos_total FROM trajets WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id_trajet, $user_id);
    $stmt->execute();
    $trajet = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($trajet && $trajet['kilos_total'] !== null) {
        if (!is_numeric($kilos) || $kilos < 0 || $kilos > $trajet['kilos_total']) {
            $conn->close();
            header("Location: dashboard.php?erreur=kilos");
            exit();
        }
        $kilos = (float)$kilos;
        $stmt = $conn->prepare("UPDATE trajets SET kilos_restants = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("dii", $kilos, $id_trajet, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

$conn->close();
header("Location: dashboard.php?success=kilos");
exit();
?>