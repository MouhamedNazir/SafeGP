<?php
require_once 'init.php';

// Seuls les utilisateurs connectés peuvent accéder à cette page
exiger_connexion();

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

    // Vérification 2 — taille : 8 Mo si le serveur peut compresser (GD), sinon 2 Mo
    } elseif ($fichier['size'] > (function_exists('imagecreatefromstring') ? 8 : 2) * 1024 * 1024) {
        $erreur = "L'image ne doit pas dépasser " . (function_exists('imagecreatefromstring') ? 8 : 2) . " Mo.";

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
            // Redimensionnement à 400 px et compression JPEG (pages plus légères)
            $destination = compresser_image($destination, 400);
            $nouveau_nom = basename($destination);
            // Ancienne photo avec une autre extension : on la supprime
            if (!empty($user['photo']) && $user['photo'] !== $nouveau_nom && preg_match('/^user_' . $user_id . '\.(jpe?g|png|webp)$/', $user['photo'])) {
                @unlink('uploads/' . $user['photo']);
            }
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

// ============================================================
// CHANGER DE MOT DE PASSE — l'ancien est demandé
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['changer_mdp'])) {
    $actuel = $_POST['mdp_actuel'] ?? '';
    $nouveau = $_POST['mdp_nouveau'] ?? '';
    if (!password_verify($actuel, $user['mot_de_passe'])) {
        $erreur = "Mot de passe actuel incorrect.";
    } elseif (strlen($nouveau) < 8) {
        $erreur = "Le nouveau mot de passe doit faire au moins 8 caractères.";
    } elseif ($nouveau !== ($_POST['mdp_confirmation'] ?? '')) {
        $erreur = "Les deux nouveaux mots de passe ne correspondent pas.";
    } else {
        $hash = password_hash($nouveau, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET mot_de_passe = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $user_id);
        $stmt->execute();
        $stmt->close();
        $user['mot_de_passe'] = $hash;
        session_regenerate_id(true);
        $success = "Mot de passe modifié !";
    }
}

// ============================================================
// CHANGER D'ADRESSE E-MAIL — confirmée par un lien envoyé à la
// nouvelle adresse (l'ancienne reste active en attendant)
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['changer_email'])) {
    $nouvel_email = trim($_POST['email_nouveau'] ?? '');
    $check = $conn->prepare("SELECT id FROM users WHERE (email = ? OR email_nouveau = ?) AND id <> ?");
    $check->bind_param("ssi", $nouvel_email, $nouvel_email, $user_id);
    $check->execute();
    $pris = (bool)$check->get_result()->fetch_row();
    $check->close();

    if (!password_verify($_POST['mdp_email'] ?? '', $user['mot_de_passe'])) {
        $erreur = "Mot de passe incorrect.";
    } elseif (!filter_var($nouvel_email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Adresse e-mail invalide.";
    } elseif (strcasecmp($nouvel_email, $user['email']) === 0) {
        $erreur = "C'est déjà ton adresse actuelle.";
    } elseif ($pris) {
        $erreur = "Cette adresse est déjà utilisée par un autre compte.";
    } elseif (!email_actif()) {
        $erreur = "Le changement d'adresse n'est pas disponible tant que l'envoi d'e-mails n'est pas configuré.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET email_nouveau = ? WHERE id = ?");
        $stmt->bind_param("si", $nouvel_email, $user_id);
        $stmt->execute();
        $stmt->close();
        $user['email_nouveau'] = $nouvel_email;
        $jeton = jeton_creer($conn, $user_id, 'changement_email', 60 * 24);
        email_envoyer($nouvel_email, 'Confirme ta nouvelle adresse - SafeGP', email_gabarit(
            'Confirme ta nouvelle adresse e-mail',
            '<p>Bonjour ' . htmlspecialchars($user['nom']) . ',</p><p>Clique sur le bouton pour utiliser cette adresse sur ton compte SafeGP. Le lien est valable 24 heures.</p>',
            'Confirmer cette adresse',
            url_site() . '/confirmer_email.php?jeton=' . $jeton
        ));
        $success = "Un lien de confirmation a été envoyé à " . $nouvel_email . ".";
    }
}

// ============================================================
// BADGE « GP VÉRIFIÉ » : envoi d'une photo de pièce d'identité
// Stockée dans verifications/ (dossier fermé au web), sous un nom
// aléatoire, puis supprimée dès que l'admin a vérifié.
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['piece_identite'])) {
    $deja = $conn->query("SELECT COUNT(*) FROM verifications WHERE user_id = " . (int)$user_id . " AND statut = 'en_attente'")->fetch_row()[0];
    $probleme = image_envoyee_valide($_FILES['piece_identite'], 8 * 1024 * 1024);
    if ($user['verifie']) {
        $erreur = "Ton compte est déjà vérifié.";
    } elseif ($deja) {
        $erreur = "Ta demande est déjà en cours de vérification.";
    } elseif ($probleme) {
        $erreur = $probleme;
    } else {
        $nom_fichier = bin2hex(random_bytes(16)) . '.img';
        $chemin = __DIR__ . '/verifications/' . $nom_fichier;
        if (move_uploaded_file($_FILES['piece_identite']['tmp_name'], $chemin)) {
            $chemin = compresser_image($chemin, 1600, 85);
            $nom_fichier = basename($chemin);
            $stmt = $conn->prepare("INSERT INTO verifications (user_id, fichier) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $nom_fichier);
            $stmt->execute();
            $stmt->close();
            $admins = $conn->query("SELECT email FROM users WHERE role = 'admin'");
            while ($admin = $admins->fetch_assoc()) {
                email_envoyer($admin['email'], 'Pièce d\'identité à vérifier - SafeGP', email_gabarit(
                    'Demande de badge « vérifié »',
                    '<p><strong>' . htmlspecialchars($user['nom']) . '</strong> (@' . htmlspecialchars($user['username'] ?? '') . ') a envoyé sa pièce d\'identité.</p>',
                    'Vérifier dans le panel admin',
                    url_site() . '/admin.php#verifications'
                ));
            }
            $success = "Pièce envoyée : nous la vérifions et tu recevras un e-mail.";
        } else {
            $erreur = "L'envoi a échoué, réessaie.";
        }
    }
}
$stmt = $conn->prepare("SELECT * FROM verifications WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ma_verification = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Alertes de trajets
$stmt = $conn->prepare("SELECT * FROM alertes WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$mes_alertes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Abonnement : formule active + historique des demandes
$quota = quota_publication($conn, $user_id);
$stmt = $conn->prepare("SELECT * FROM abonnements WHERE user_id = ? ORDER BY id DESC LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$historique_abonnements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
                <?= avatar($user['nom'], $user['photo']) ?>
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

        <!-- MON ABONNEMENT -->
        <h3 id="abonnement" class="titre-section">Mon abonnement</h3>
        <?php include 'quota_bandeau.php'; ?>
        <p><a href="tarifs.php" class="btn-contact"><i class="fa-solid fa-crown"></i> <?= $quota['formule'] === 'gratuit' ? 'Voir les formules' : 'Prolonger ou changer de formule' ?></a></p>
        <?php if ($historique_abonnements): ?>
            <div class="table-defilante">
            <table class="admin-table">
                <thead><tr><th>Date</th><th>Formule</th><th>Montant</th><th>Statut</th></tr></thead>
                <tbody>
                <?php foreach ($historique_abonnements as $h): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($h['created_at'])) ?></td>
                        <td><?= e(FORMULES[$h['formule']]['nom']) ?> · <?= $h['duree'] === 'an' ? '1 an' : '1 mois' ?></td>
                        <td><?= e(prix_fcfa((int)$h['montant'])) ?></td>
                        <td>
                            <?php if ($h['statut'] === 'en_attente'): ?><span class="statut-attente">En cours de vérification</span>
                            <?php elseif ($h['statut'] === 'valide'): ?><span class="statut-ok">Validé</span> <small class="texte-doux-bloc">jusqu'au <?= date('d/m/Y', strtotime($h['date_fin'])) ?></small>
                            <?php else: ?><span class="statut-refus">Refusé</span> <small class="texte-doux-bloc"><?= e($h['motif_refus']) ?></small><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

        <!-- BADGE VÉRIFIÉ -->
        <h3 id="verification" class="titre-section">Badge « GP vérifié »</h3>
        <?php if ($user['verifie']): ?>
            <p><?= badge_verifie(1) ?> Ton identité est vérifiée : le badge s'affiche sur ton profil et tes annonces.</p>
        <?php elseif ($ma_verification && $ma_verification['statut'] === 'en_attente'): ?>
            <p class="quota"><i class="fa-solid fa-hourglass-half"></i> Ta pièce d'identité est en cours de vérification (envoyée le <?= date('d/m/Y', strtotime($ma_verification['created_at'])) ?>).</p>
        <?php else: ?>
            <?php if ($ma_verification && $ma_verification['statut'] === 'refuse'): ?>
                <p class="quota quota-atteint">Ta dernière demande a été refusée : <?= e($ma_verification['motif_refus']) ?></p>
            <?php endif; ?>
            <p class="aide-contact">Les expéditeurs font davantage confiance aux GP vérifiés. Envoie une photo lisible de ta carte d'identité ou de ton passeport :
                elle n'est visible que par l'équipe SafeGP et elle est <strong>supprimée dès la vérification</strong>.</p>
            <form action="mon_profil.php#verification" method="POST" enctype="multipart/form-data" class="form-verification">
                <?= csrf_field() ?>
                <input type="file" name="piece_identite" accept=".jpg,.jpeg,.png,.webp" required>
                <button type="submit" class="btn-contact"><i class="fa-solid fa-id-card"></i> Envoyer ma pièce</button>
            </form>
        <?php endif; ?>

        <!-- MES ALERTES -->
        <h3 id="alertes" class="titre-section">Mes alertes de trajets</h3>
        <?php if (($_GET['alerte'] ?? '') === 'supprimee'): ?><p class="success">Alerte supprimée.</p><?php endif; ?>
        <?php if ($mes_alertes): ?>
            <ul class="liste-alertes">
                <?php foreach ($mes_alertes as $a): ?>
                    <li>
                        <a href="search_trajet.php?<?= e(http_build_query(array_filter(['pays_depart' => $a['pays_depart'], 'depart' => $a['depart'], 'pays_arrivee' => $a['pays_arrivee'], 'arrivee' => $a['arrivee']]))) ?>">
                            <i class="fa-regular fa-bell"></i> <?= e(alerte_libelle($a)) ?>
                        </a>
                        <form action="alertes.php" method="POST" class="form-inline">
                            <?= csrf_field() ?>
                            <button type="submit" name="supprimer" value="<?= (int)$a['id'] ?>" class="btn-lien" title="Supprimer cette alerte"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="texte-doux-bloc">Aucune alerte. Fais une recherche (pays ou ville), puis clique sur « Créer une alerte ».</p>
        <?php endif; ?>

        <!-- SÉCURITÉ DU COMPTE -->
        <h3 class="titre-section">Sécurité du compte</h3>
        <details class="bloc-repliable">
            <summary><i class="fa-solid fa-key"></i> Changer mon mot de passe</summary>
            <form action="mon_profil.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="changer_mdp" value="1">
                <div class="form-group">
                    <label>Mot de passe actuel</label>
                    <input type="password" name="mdp_actuel" required autocomplete="current-password">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nouveau mot de passe</label>
                        <input type="password" name="mdp_nouveau" minlength="8" required autocomplete="new-password" placeholder="Au moins 8 caractères">
                    </div>
                    <div class="form-group">
                        <label>Confirmer</label>
                        <input type="password" name="mdp_confirmation" minlength="8" required autocomplete="new-password">
                    </div>
                </div>
                <button type="submit" class="btn-publier">Changer le mot de passe</button>
            </form>
        </details>
        <details class="bloc-repliable">
            <summary><i class="fa-solid fa-envelope"></i> Changer mon adresse e-mail <small class="texte-doux-bloc">(actuelle : <?= e($user['email']) ?>)</small></summary>
            <?php if (!empty($user['email_nouveau'])): ?>
                <p class="aide-contact">En attente de confirmation : <strong><?= e($user['email_nouveau']) ?></strong> (clique sur le lien reçu à cette adresse).</p>
            <?php endif; ?>
            <form action="mon_profil.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="changer_email" value="1">
                <div class="form-group">
                    <label>Nouvelle adresse e-mail</label>
                    <input type="email" name="email_nouveau" required maxlength="150">
                </div>
                <div class="form-group">
                    <label>Ton mot de passe</label>
                    <input type="password" name="mdp_email" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-publier">Recevoir le lien de confirmation</button>
            </form>
        </details>

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