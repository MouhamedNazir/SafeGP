<?php
include 'config.php';
include 'navbar.php';

// Vérification — seuls les admins peuvent accéder
// Si pas connecté ou pas admin → redirection
if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

// On vérifie le rôle dans la DB — pas seulement en session
// car quelqu'un pourrait modifier sa session manuellement
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($admin['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// ============================================================
// ACTIONS ADMIN
// ============================================================

// Supprimer un trajet abusif
if (isset($_GET['supprimer_trajet'])) {
    $id = (int)$_GET['supprimer_trajet'];
    $stmt = $conn->prepare("DELETE FROM trajets WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin.php?success=trajet_supprime");
    exit();
}

// Supprimer un utilisateur
if (isset($_GET['supprimer_user'])) {
    $id = (int)$_GET['supprimer_user'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin.php?success=user_supprime");
    exit();
}

// Changer le rôle d'un utilisateur
if (isset($_POST['changer_role'])) {
    $id = (int)$_POST['user_id'];
    $role = $_POST['role'];
    $roles_autorises = ['user', 'admin'];
    if (in_array($role, $roles_autorises)) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $role, $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: admin.php?success=role_modifie");
    exit();
}

// ============================================================
// STATISTIQUES JE LES QFFICHES IICI
// ============================================================

// Nombre total d'utilisateurs
$total_users = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];

// Nombre total de trajets
$total_trajets = $conn->query("SELECT COUNT(*) as total FROM trajets")->fetch_assoc()['total'];

// Nombre de trajets disponibles
$trajets_dispo = $conn->query("SELECT COUNT(*) as total FROM trajets WHERE statut = 'disponible'")->fetch_assoc()['total'];

// ============================================================
// DONNÉES
// ============================================================

// Tous les utilisateurs
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");

// Tous les trajets
$trajets = $conn->query("
    SELECT trajets.*, users.nom 
    FROM trajets 
    LEFT JOIN users ON trajets.user_id = users.id 
    ORDER BY trajets.id DESC
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Admin - SafeGP</title>
</head>
<body>

<section class="profil">
    <h2><i class="fa-solid fa-shield"></i> Panel Admin</h2>

    <?php if (isset($_GET['success'])): ?>
        <p class="success">
            <i class="fa-solid fa-circle-check"></i>
            <?php
                if ($_GET['success'] == 'trajet_supprime') echo "Trajet supprimé !";
                if ($_GET['success'] == 'user_supprime') echo "Utilisateur supprimé !";
                if ($_GET['success'] == 'role_modifie') echo "Rôle modifié !";
            ?>
        </p>
    <?php endif; ?>

    <!-- STATISTIQUES -->
    <div style="display:flex; gap:20px; margin-bottom:40px; flex-wrap:wrap;">
        <div class="stat-card">
            <i class="fa-solid fa-users" style="font-size:30px; color:#f97316;"></i>
            <h3><?= $total_users ?></h3>
            <p>Utilisateurs</p>
        </div>
        <div class="stat-card">
            <i class="fa-solid fa-plane" style="font-size:30px; color:#f97316;"></i>
            <h3><?= $total_trajets ?></h3>
            <p>Trajets total</p>
        </div>
        <div class="stat-card">
            <i class="fa-solid fa-circle-check" style="font-size:30px; color:#16a34a;"></i>
            <h3><?= $trajets_dispo ?></h3>
            <p>Trajets disponibles</p>
        </div>
    </div>

    <!-- UTILISATEURS -->
    <h3 style="color:#f97316; margin-bottom:20px;">
        <i class="fa-solid fa-users"></i> Utilisateurs
    </h3>

    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nom</th>
                <th>Username</th>
                <th>Email</th>
                <th>Rôle</th>
                <th>Inscrit le</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while($user = $users->fetch_assoc()): ?>
            <tr>
                <td><?= $user['id'] ?></td>
                <td><?= htmlspecialchars($user['nom']) ?></td>
                <td>@<?= htmlspecialchars($user['username'] ?? '') ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td>
                    <!-- Changer le rôle -->
                    <form action="admin.php" method="POST" style="display:inline;">
                        <input type="hidden" name="changer_role" value="1">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <select name="role" onchange="this.form.submit()"
                                style="padding:4px; border-radius:5px; background-color:#0f172a; color:white; border:none;">
                            <option value="user" <?= $user['role'] == 'user' ? 'selected' : '' ?>>user</option>
                            <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>admin</option>
                        </select>
                    </form>
                </td>
                <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                <td>
                    <?php if ($user['role'] != 'admin'): ?>
                        <a href="admin.php?supprimer_user=<?= $user['id'] ?>"
                           style="color:#f97316;"
                           onclick="return confirm('Supprimer cet utilisateur ?')">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    <?php else: ?>
                        <span style="color:#94a3b8;">protégé</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- TRAJETS -->
    <h3 style="color:#f97316; margin:40px 0 20px;">
        <i class="fa-solid fa-plane"></i> Trajets
    </h3>

    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>GP</th>
                <th>Trajet</th>
                <th>Dates</th>
                <th>Prix</th>
                <th>Statut</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $trajets->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['nom'] ?? 'Inconnu') ?></td>
                <td><?= htmlspecialchars($row['depart']) ?> → <?= htmlspecialchars($row['arrivee']) ?></td>
                <td><?= date('d/m/Y', strtotime($row['date_depart'])) ?></td>
                <td><?= htmlspecialchars($row['prix_kilo']) ?> MAD</td>
                <td>
                    <?php
                        $couleurs = [
                            'disponible' => '#16a34a',
                            'en_route'   => '#f97316',
                            'arrivé'     => '#94a3b8'
                        ];
                        $couleur = $couleurs[$row['statut']] ?? '#94a3b8';
                    ?>
                    <span style="background-color:<?= $couleur ?>; padding:3px 10px; border-radius:20px; font-size:12px;">
                        <?= htmlspecialchars($row['statut']) ?>
                    </span>
                </td>
                <td>
                    <a href="admin.php?supprimer_trajet=<?= $row['id'] ?>"
                       style="color:#f97316;"
                       onclick="return confirm('Supprimer ce trajet ?')">
                        <i class="fa-solid fa-trash"></i>
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</section>

<?php include 'footer.php'; ?>

</body>
</html>