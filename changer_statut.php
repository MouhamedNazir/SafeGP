<?php
include 'config.php';
require_once 'csrf.php'; // démarre la session

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

// Uniquement en POST, avec un jeton CSRF valide
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: dashboard.php");
    exit();
}
csrf_verify();

$user_id = $_SESSION['user_id'];
$id_trajet = (int)$_POST['id_trajet'];
$statut = $_POST['statut'];

$statuts_autorises = ['disponible', 'en_route', 'arrivé'];

if (in_array($statut, $statuts_autorises)) {
    $stmt = $conn->prepare("UPDATE trajets SET statut = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $statut, $id_trajet, $user_id);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
header("Location: dashboard.php?success=modifie");
exit();
?>