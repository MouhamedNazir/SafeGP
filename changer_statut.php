<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

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