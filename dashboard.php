<?php
include 'config.php';
include 'navbar.php';

// Seuls les connectés peuvent accéder au dashboard
if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ============================================================
// SUPPRESSION D'UN TRAJET
// On vérifie que le trajet appartient bien à cet utilisateur
// "AND user_id = ?" → sécurité pour qu'un user ne supprime pas
// le trajet d'un autre
// ============================================================
// En POST (et non en GET) : un simple lien ne doit jamais pouvoir supprimer
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['supprimer'])) {
    $id_trajet = (int)$_POST['supprimer'];

    $stmt = $conn->prepare("DELETE FROM trajets WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id_trajet, $user_id);
    $stmt->execute();
    $stmt->close();

    header("Location: dashboard.php?success=supprime");
    exit();
}

// On récupère tous les trajets de l'utilisateur connecté
// ORDER BY date_depart ASC → les plus proches en premier
$stmt = $conn->prepare("SELECT * FROM trajets WHERE user_id = ? ORDER BY date_depart ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$trajets = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Tableau de bord - SafeGP</title>
</head>
<body>

    <section class="profil">
        <h2>Mes trajets</h2>

        <!-- Message de succès après suppression ou modification -->
        <?php if (isset($_GET['success'])): ?>
            <p class="success">
                <i class="fa-solid fa-circle-check" style="color:#16a34a;"></i>
                <?php if ($_GET['success'] == 'supprime') echo "Trajet supprimé !"; ?>
                <?php if ($_GET['success'] == 'modifie') echo "Trajet modifié !"; ?>
            </p>
        <?php endif; ?>

        <a href="publier.php" class="btn-contact" style="display:inline-block; margin-bottom:30px;">
            + Publier un nouveau trajet
        </a>

        <?php if ($trajets->num_rows > 0): ?>

            <?php while($row = $trajets->fetch_assoc()): ?>
                <div class="annonce">

                    <!-- Header : trajet + badge statut + prix -->
                    <div class="annonce-header">
                        <span class="trajet-titre">
                            <i class="fa-solid fa-plane-departure"></i>
                            <?= htmlspecialchars($row['depart']) ?>
                            →
                            <i class="fa-solid fa-plane-arrival"></i>
                            <?= htmlspecialchars($row['arrivee']) ?>
                        </span>

                        <div style="display:flex; gap:10px; align-items:center;">
                            <?php
                                // Couleur du badge selon le statut
                                $couleurs = [
                                    'disponible' => '#16a34a',
                                    'en_route'   => '#f97316',
                                    'arrivé'     => '#94a3b8'
                                ];
                                $couleur = $couleurs[$row['statut']] ?? '#94a3b8';
                            ?>
                            <span style="background-color:<?= $couleur ?>; padding:4px 12px; border-radius:20px; font-size:13px;">
                                <?= htmlspecialchars($row['statut']) ?>
                            </span>
                            <!-- Prix — une seule fois ici -->
                            <span class="prix"><?= htmlspecialchars($row['prix_kilo']) ?> MAD/kg</span>
                        </div>
                    </div>

                    <!-- Body : détails du trajet -->
                    <div class="annonce-body">

                        <div class="info-ligne">
                            <span><i class="fa-solid fa-calendar"></i> Départ :</span>
                            <span><?= date('d/m/Y', strtotime($row['date_depart'])) ?></span>
                        </div>

                        <div class="info-ligne">
                            <span><i class="fa-solid fa-calendar"></i> Arrivée :</span>
                            <span><?= date('d/m/Y', strtotime($row['date_arrivee'])) ?></span>
                        </div>

                        <?php if (!empty($row['point_depot'])): ?>
                        <div class="info-ligne">
                            <span><i class="fa-solid fa-box"></i> Dépôt :</span>
                            <span><?= htmlspecialchars($row['point_depot']) ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($row['point_reception'])): ?>
                        <div class="info-ligne">
                            <span><i class="fa-solid fa-location-dot"></i> Réception :</span>
                            <span><?= htmlspecialchars($row['point_reception']) ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($row['commentaire'])): ?>
                        <div class="info-ligne">
                            <span><i class="fa-solid fa-comment"></i> Commentaire :</span>
                            <span><?= htmlspecialchars($row['commentaire']) ?></span>
                        </div>
                        <?php endif; ?>

                    </div>

                    <!-- Footer : changer statut + modifier + supprimer -->
                    <div class="annonce-footer" style="display:flex; gap:10px; flex-wrap:wrap;">

                        <!-- Formulaire pour changer le statut du trajet -->
                        <form action="changer_statut.php" method="POST" style="display:flex; gap:10px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_trajet" value="<?= $row['id'] ?>">
                            <select name="statut" style="padding:8px; border-radius:5px; background-color:#0f172a; color:white; border:none;">
                                <!-- "selected" sur la valeur actuelle pour pré-sélectionner -->
                                <option value="disponible" <?= $row['statut'] == 'disponible' ? 'selected' : '' ?>>disponible</option>
                                <option value="en_route" <?= $row['statut'] == 'en_route' ? 'selected' : '' ?>>en route</option>
                                <option value="arrivé" <?= $row['statut'] == 'arrivé' ? 'selected' : '' ?>>arrivé</option>
                            </select>
                            <button type="submit" class="btn-contact">Mettre à jour</button>
                        </form>

                        <!-- Bouton modifier → redirige vers modifier_trajet.php avec l'id -->
                        <a href="modifier_trajet.php?id=<?= $row['id'] ?>" 
                            class="btn-contact" 
                            style="padding:8px 14px; font-size:13px; display:flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-pen"></i> Modifier
                        </a>

                        <!-- Bouton supprimer → confirm() demande confirmation avant de supprimer -->
                        <form action="dashboard.php" method="POST" style="margin:0;"
                              onsubmit="return confirm('Tu veux vraiment supprimer ce trajet ?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="supprimer" value="<?= (int)$row['id'] ?>">
                            <button type="submit" class="btn-contact"
                                style="background-color:#991b1b; padding:8px 14px; font-size:13px; display:flex; align-items:center; gap:6px; border:none; cursor:pointer; font-family:inherit;">
                                <i class="fa-solid fa-trash"></i> Supprimer
                            </button>
                        </form>

                    </div>

                </div>
            <?php endwhile; ?>

        <?php else: ?>
            <!-- Message si aucun trajet publié -->
            <div class="aucun-resultat">
                <p><i class="fa-solid fa-face-frown"></i> Tu n'as pas encore publié de trajet.</p>
                <a href="publier.php">+ Publier mon premier trajet</a>
            </div>
        <?php endif; ?>

    </section>

    <?php include 'footer.php'; ?>

</body>
</html>