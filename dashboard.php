<?php
require_once 'init.php';

// Seuls les connectés peuvent accéder au dashboard
exiger_connexion();

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
$trajets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$contacts = contacts_pour_trajets($conn, $trajets);
$quota = quota_publication($conn, $user_id);

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Tableau de bord - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

    <section class="profil">
        <h2>Mes trajets</h2>

        <!-- Message de succès après suppression ou modification -->
        <?php if (isset($_GET['erreur']) && $_GET['erreur'] == 'kilos'): ?>
            <p class="erreur"><i class="fa-solid fa-circle-xmark" style="color:white;"></i> Les kilos restants doivent être entre 0 et la capacité totale.</p>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <p class="success">
                <i class="fa-solid fa-circle-check" style="color:var(--vert);"></i>
                <?php if ($_GET['success'] == 'supprime') echo "Trajet supprimé !"; ?>
                <?php if ($_GET['success'] == 'modifie') echo "Trajet modifié !"; ?>
                <?php if ($_GET['success'] == 'kilos') echo "Trajet mis à jour !"; ?>
            </p>
        <?php endif; ?>

        <a href="publier.php" class="btn-contact" style="margin-bottom:15px;">
            + Publier un nouveau trajet
        </a>
        <?php include 'quota_bandeau.php'; ?>
        <?php if (!empty($nav_envois_a_traiter)): ?>
            <div class="bandeau-info"><i class="fa-solid fa-box"></i> <?= (int)$nav_envois_a_traiter ?> réservation(s) de colis attendent ta réponse. <a href="envois.php">Voir mes envois</a></div>
        <?php endif; ?>

        <?php if ($trajets): ?>

            <?php foreach ($trajets as $row): ?>
                <div class="annonce">

                    <?php $avec_gp = false; include 'trajet_infos.php'; ?>

                    <!-- Numéros affichés sur cette annonce -->
                    <?php if (!empty($contacts[$row['id']])): ?>
                    <div class="contacts-annonce">
                        <span><i class="fa-brands fa-whatsapp"></i> Affiché sur l'annonce :</span>
                        <?php foreach ($contacts[$row['id']] as $c): ?>
                            <span class="contact-puce"><?= e($c['nom']) ?> · <?= e($c['numero']) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Footer : changer statut + modifier + supprimer -->
                    <div class="annonce-footer">

                        <!-- Formulaire pour changer le statut du trajet -->
                        <form action="changer_statut.php" method="POST" class="form-statut">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_trajet" value="<?= $row['id'] ?>">
                            <select name="statut" class="select-petit">
                                <!-- "selected" sur la valeur actuelle pour pré-sélectionner -->
                                <option value="disponible" <?= $row['statut'] == 'disponible' ? 'selected' : '' ?>>disponible</option>
                                <option value="en_route" <?= $row['statut'] == 'en_route' ? 'selected' : '' ?>>en route</option>
                                <option value="arrivé" <?= $row['statut'] == 'arrivé' ? 'selected' : '' ?>>arrivé</option>
                            </select>
                            <?php if ($row['kilos_total'] !== null): ?>
                                <label class="kilos-maj" title="Kilos encore disponibles">
                                    <input type="number" name="kilos_restants" step="0.1" min="0" max="<?= e($row['kilos_total']) ?>"
                                           value="<?= e($row['kilos_restants']) ?>">
                                    / <?= e($row['kilos_total'] + 0) ?> kg
                                </label>
                            <?php endif; ?>
                            <button type="submit" class="btn-contact">Mettre à jour</button>
                        </form>

                        <!-- Bouton modifier → redirige vers modifier_trajet.php avec l'id -->
                        <a href="trajet.php?id=<?= (int)$row['id'] ?>" class="btn-contact btn-secondaire"><i class="fa-solid fa-share-nodes"></i> Voir / partager</a>
                        <a href="modifier_trajet.php?id=<?= $row['id'] ?>" class="btn-contact btn-secondaire">
                            <i class="fa-solid fa-pen"></i> Modifier
                        </a>

                        <!-- Bouton supprimer → confirm() demande confirmation avant de supprimer -->
                        <form action="dashboard.php" method="POST" class="form-inline"
                              onsubmit="return confirm('Tu veux vraiment supprimer ce trajet ?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="supprimer" value="<?= (int)$row['id'] ?>">
                            <button type="submit" class="btn-contact btn-supprimer">
                                <i class="fa-solid fa-trash"></i> Supprimer
                            </button>
                        </form>

                    </div>

                </div>
            <?php endforeach; ?>

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