<?php
require_once 'init.php';

// Seuls les utilisateurs connectés peuvent accéder à cette page
if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = "";
$erreur = "";

// On récupère toutes les infos de l'utilisateur connecté depuis la DB
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ============================================================
// UPLOAD DE PHOTO
// On vérifie que le formulaire photo a été soumis
// et qu'un fichier a bien été envoyé (size > 0)
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['photo']) && $_FILES['photo']['size'] > 0) {
    $fichier = $_FILES['photo'];
    
    // On récupère l'extension du fichier (jpg, png...) en minuscule
    $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    $extensions_autorisees = ['jpg', 'jpeg', 'png', 'webp'];

    // Vérification 1 — l'extension est-elle autorisée ?
    if (!in_array($extension, $extensions_autorisees)) {
        $erreur = "Format non autorisé. Utilise JPG, PNG ou WEBP.";

    // Vérification 2 — la taille ne dépasse pas 2MB ?
    } elseif ($fichier['size'] > 2 * 1024 * 1024) {
        $erreur = "L'image ne doit pas dépasser 2MB.";

    // Vérification 3 — le contenu est-il vraiment une image ?
    // L'extension se falsifie facilement (ex: script.php renommé en photo.jpg),
    // donc on lit le vrai type du fichier avec finfo + getimagesize
    } elseif (
        $fichier['error'] !== UPLOAD_ERR_OK
        || !in_array((new finfo(FILEINFO_MIME_TYPE))->file($fichier['tmp_name']), ['image/jpeg', 'image/png', 'image/webp'], true)
        || @getimagesize($fichier['tmp_name']) === false
    ) {
        $erreur = "Le fichier n'est pas une image valide.";

    } else {
        // On crée un nom unique basé sur l'id user pour éviter les conflits
        // Ex: user_1.jpg — si l'user rechange sa photo, ça écrase l'ancienne
        $nouveau_nom = "user_" . $user_id . "." . $extension;
        $destination = "uploads/" . $nouveau_nom;

        // On déplace le fichier du dossier temporaire vers uploads/
        if (move_uploaded_file($fichier['tmp_name'], $destination)) {
            // On met à jour le nom de la photo dans la DB
            $stmt = $conn->prepare("UPDATE users SET photo = ? WHERE id = ?");
            $stmt->bind_param("si", $nouveau_nom, $user_id);
            $stmt->execute();
            $stmt->close();
            
            // On met à jour aussi le tableau $user pour afficher la nouvelle photo
            $user['photo'] = $nouveau_nom;
            // Mettre à jour la session avec la nouvelle photo
            $_SESSION['user_photo'] = $nouveau_nom;
            $success = "Photo mise à jour !";
        } else {
            $erreur = "Erreur lors de l'upload.";
        }
    }
}




// ============================================================
// MISE À JOUR DES INFORMATIONS DU PROFIL
// Ce formulaire est différent du formulaire photo
// On le détecte avec isset($_POST['nom'])
// ============================================================
// ============================================================
// SUPPRESSION DU COMPTE — mot de passe exigé pour confirmer
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['supprimer_compte'])) {
    $nb_admins = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetch_row()[0];

    if (!password_verify($_POST['mot_de_passe_suppression'] ?? '', $user['mot_de_passe'])) {
        $erreur = "Mot de passe incorrect : ton compte n'a pas été supprimé.";
    } elseif (empty($_POST['confirmer_suppression'])) {
        $erreur = "Coche la case pour confirmer la suppression.";
    } elseif ($user['role'] === 'admin' && $nb_admins <= 1) {
        $erreur = "Tu es le seul administrateur : nomme un autre admin avant de supprimer ton compte.";
    } elseif (supprimer_utilisateur($conn, $user_id)) {
        $conn->close();
        session_unset();
        session_destroy();
        header("Location: index.php?compte=supprime");
        exit();
    } else {
        $erreur = "La suppression a échoué, réessaie plus tard.";
    }
}

$contacts_valeurs = contacts_profil($conn, $user_id);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['nom'])) {
    $nom = trim($_POST['nom']);
    
    // On force le username en minuscule pour éviter les doublons
    $username = strtolower(trim($_POST['username']));
    $disponible = !empty($_POST['disponible']) ? 1 : 0;
    $notif_email = !empty($_POST['notif_email']) ? 1 : 0;
    $joignable_debut = vide_en_null($_POST['joignable_debut'] ?? '');
    $joignable_fin = vide_en_null($_POST['joignable_fin'] ?? '');
    $contacts_valeurs = contacts_depuis_post($erreur);
    $format_heure = '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';

    if ($erreur) {
        // erreur déjà renseignée par contacts_depuis_post()

    // Validation username — minimum 3 caractères
    } elseif (strlen($username) < 3) {
        $erreur = "Le nom d'utilisateur doit faire au moins 3 caractères.";

    // Validation username — que lettres, chiffres et _
    } elseif (!preg_match('/^[a-z0-9_]+$/', $username)) {
        $erreur = "Le nom d'utilisateur ne peut contenir que des lettres, chiffres et _.";

    // Horaires joignable : les deux ou aucun
    } elseif (($joignable_debut === null) !== ($joignable_fin === null)) {
        $erreur = "Indique l'heure de début et l'heure de fin où tu es joignable (ou laisse les deux vides).";
    } elseif (($joignable_debut && !preg_match($format_heure, $joignable_debut))
           || ($joignable_fin && !preg_match($format_heure, $joignable_fin))) {
        $erreur = "Horaire invalide.";

    } else {
        // On vérifie que le username n'est pas déjà pris par quelqu'un d'autre
        // "AND id != ?" → on exclut l'utilisateur lui-même de la vérification
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->bind_param("si", $username, $user_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $erreur = "Ce nom d'utilisateur est déjà pris.";
        } else {
            // Tout est bon → on met à jour les infos
            $stmt = $conn->prepare("UPDATE users SET nom = ?, username = ?, disponible = ?, notif_email = ?, joignable_debut = ?, joignable_fin = ? WHERE id = ?");
            $stmt->bind_param("ssiissi", $nom, $username, $disponible, $notif_email, $joignable_debut, $joignable_fin, $user_id);

            if ($stmt->execute()) {
                contacts_enregistrer($conn, $user_id, null, $contacts_valeurs);
                $success = "Profil mis à jour !";
                
                // On met à jour la session pour que la navbar affiche le nouveau nom
                $_SESSION['user_nom'] = $nom;
                
                // On met à jour le tableau $user pour afficher les nouvelles infos
                $user['nom'] = $nom;
                $user['username'] = $username;
                $user['disponible'] = $disponible;
                $user['notif_email'] = $notif_email;
                $user['joignable_debut'] = $joignable_debut;
                $user['joignable_fin'] = $joignable_fin;
            } else {
                $erreur = "Erreur lors de la mise à jour.";
            }
            $stmt->close();
        }
        $check->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Mon profil - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

    <section class="profil">
        <h2>Mon profil</h2>

        <?php if ($success): ?>
            <p class="success"><i class="fa-solid fa-circle-check" style="color:var(--vert);"></i> <?= e($success) ?></p>
        <?php endif; ?>

        <?php if ($erreur): ?>
            
            <p class="erreur" ><i class="fa-solid fa-circle-xmark" style="color: white;"></i> <?= e($erreur) ?></p>
        <?php endif; ?>

        <!-- Avatar + infos de base -->
        <div class="profil-header">
            <div class="profil-avatar">
                <?php if (!empty($user['photo'])): ?>
                    <!-- Si l'user a une photo → on l'affiche -->
                    <img src="uploads/<?= htmlspecialchars($user['photo']) ?>" 
                         alt="photo" 
                         style="width:70px; height:70px; border-radius:50%; object-fit:cover;">
                <?php else: ?>
                    <!-- Sinon → on affiche la première lettre du nom -->
                    <?= strtoupper(substr($user['nom'], 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div>
                <h3><?= htmlspecialchars($user['nom']) ?></h3>
                <p style="color:var(--orange);">@<?= htmlspecialchars($user['username']) ?></p>
                <p class="profil-date">Membre depuis <?= date('d/m/Y', strtotime($user['created_at'])) ?></p>
            </div>
        </div>

        <!-- Formulaire 1 — Changer la photo -->
        <!-- enctype="multipart/form-data" est obligatoire pour envoyer des fichiers -->
        <div class="form-group">
            <label>Changer ma photo de profil</label>
            <form action="mon_profil.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp"
                       style="color:var(--texte); margin-bottom:10px;">
                <button type="submit" class="btn-publier">Mettre à jour la photo</button>
            </form>
        </div>

        <hr style="border-color:var(--surface); margin: 40px 0;">

        <!-- Formulaire 2 — Modifier les infos -->
        <h3 style="color:var(--orange); margin-bottom:20px;">Mes informations</h3>
        <form action="mon_profil.php" method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label>Nom complet</label>
                <input type="text" name="nom" 
                       value="<?= htmlspecialchars($user['nom']) ?>" required>
            </div>

            <div class="form-group">
                <label>Nom d'utilisateur</label>
                <input type="text" name="username" 
                       value="<?= htmlspecialchars($user['username']) ?>" required>
                <small style="color:var(--texte-doux);">Lettres, chiffres et _ uniquement</small>
            </div>

            <div class="form-group">
                <label>Mes numéros WhatsApp</label>
                <?php include 'contacts_champs.php'; ?>
                <small style="color:var(--texte-doux);">Ces numéros seront visibles sur tes annonces.</small>
            </div>

            <h3 style="color:var(--orange); margin:30px 0 20px;">Ma disponibilité</h3>

            <label class="case-a-cocher">
                <input type="checkbox" name="disponible" value="1" <?= $user['disponible'] ? 'checked' : '' ?>>
                Je prends des colis en ce moment
            </label>
            <small style="color:var(--texte-doux); display:block; margin:-10px 0 20px;">Décoché, ton profil affiche « Indisponible pour le moment » et tu n'apparais plus dans les GPs disponibles de l'accueil.</small>

            <div class="form-row">
                <div class="form-group">
                    <label>Joignable de</label>
                    <input type="time" name="joignable_debut" value="<?= e(substr((string)$user['joignable_debut'], 0, 5)) ?>">
                </div>
                <div class="form-group">
                    <label>à</label>
                    <input type="time" name="joignable_fin" value="<?= e(substr((string)$user['joignable_fin'], 0, 5)) ?>">
                </div>
            </div>
            <small style="color:var(--texte-doux); display:block; margin:-10px 0 20px;">Plage horaire où les clients peuvent t'appeler (facultatif).</small>

            <h3 style="color:var(--orange); margin:30px 0 20px;">Notifications</h3>
            <label class="case-a-cocher">
                <input type="checkbox" name="notif_email" value="1" <?= $user['notif_email'] ? 'checked' : '' ?>>
                Recevoir un e-mail quand quelqu'un m'envoie un message
            </label>

            <button type="submit" class="btn-publier">Sauvegarder</button>

        </form>

        <!-- Zone de danger : suppression définitive du compte -->
        <details class="zone-danger">
            <summary><i class="fa-solid fa-triangle-exclamation"></i> Supprimer mon compte</summary>
            <p>Cette action est <strong>définitive</strong> : ton profil, tes trajets, tes numéros et tes messages seront supprimés.</p>
            <form action="mon_profil.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="supprimer_compte" value="1">
                <div class="form-group">
                    <label>Ton mot de passe</label>
                    <input type="password" name="mot_de_passe_suppression" required autocomplete="current-password">
                </div>
                <label class="case-a-cocher">
                    <input type="checkbox" name="confirmer_suppression" value="1" required>
                    Je comprends que c'est définitif
                </label>
                <button type="submit" class="btn-danger">Supprimer définitivement mon compte</button>
            </form>
        </details>

    </section>

    <?php include 'footer.php'; ?>

</body>
</html>