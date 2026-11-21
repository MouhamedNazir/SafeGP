<?php
// ============================================================
// RETOURS CLIENTS — suggestions, problèmes, demandes
// Accessible à tous (connecté ou non). Les retours arrivent
// dans le panel admin, section « Retours clients ».
// ============================================================
require_once 'init.php';

$types = ['suggestion' => 'Une suggestion', 'probleme' => 'Un problème sur le site', 'autre' => 'Autre (question, passage au premium...)'];
$connecte = isset($_SESSION['user_id']);
$success = false;
$erreur = "";
$type = $_GET['type'] ?? 'suggestion';
// Signalement d'une annonce : le message est pré-rempli avec sa référence
$trajet_signale = (int)($_GET['trajet'] ?? 0);
if (!isset($types[$type])) $type = 'suggestion';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type = $_POST['type'] ?? '';
    $message = trim($_POST['message'] ?? '');
    $nom = $connecte ? ($_SESSION['user_nom'] ?? null) : vide_en_null($_POST['nom'] ?? '');
    $email = vide_en_null($_POST['email'] ?? '');
    $user_id = $connecte ? (int)$_SESSION['user_id'] : null;

    // Anti-spam : champ caché que seuls les robots remplissent,
    // et un envoi maximum toutes les 30 secondes par visiteur
    if (!empty($_POST['site_web'])) {
        $success = true; // on fait semblant, sans rien enregistrer
    } elseif (isset($_SESSION['dernier_retour']) && time() - $_SESSION['dernier_retour'] < 30) {
        $erreur = "Merci de patienter quelques secondes avant d'envoyer un autre message.";
    } elseif (!isset($types[$type])) {
        $erreur = "Choisis le type de message.";
    } elseif (mb_strlen($message) < 5) {
        $erreur = "Ton message est un peu court.";
    } elseif (mb_strlen($message) > 3000) {
        $erreur = "Ton message est trop long (3000 caractères maximum).";
    } elseif ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Adresse e-mail invalide.";
    } else {
        $nom = $nom !== null ? mb_substr($nom, 0, 100) : null;
        $stmt = $conn->prepare("INSERT INTO retours (user_id, nom, email, type, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $nom, $email, $type, $message);
        if ($stmt->execute()) {
            $success = true;
            $_SESSION['dernier_retour'] = time();

            // Prévenir les admins par e-mail (à l'adresse de leur compte)
            $libelle = $types[$type];
            $admins = $conn->query("SELECT email FROM users WHERE role = 'admin'");
            while ($admin = $admins->fetch_assoc()) {
                email_envoyer($admin['email'], 'Nouveau retour client : ' . $libelle, email_gabarit(
                    'Nouveau retour : ' . $libelle,
                    '<p><strong>De :</strong> ' . htmlspecialchars($nom ?? 'Anonyme') . ($email ? ' (' . htmlspecialchars($email) . ')' : '') . '</p>'
                    . '<blockquote style="border-left:3px solid #f97316;margin:0;padding:5px 15px;color:#334155;">'
                    . nl2br(htmlspecialchars($message)) . '</blockquote>',
                    'Voir dans le panel admin',
                    url_site() . '/admin.php#retours'
                ));
            }
        } else {
            $erreur = "Erreur lors de l'envoi, réessaie plus tard.";
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Donner mon avis - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

    <section class="publier">
        <h2><i class="fa-solid fa-lightbulb"></i> Donner mon avis</h2>
        <p style="text-align:center; color:var(--texte-doux); margin-bottom:30px;">
            Une idée pour améliorer SafeGP, un souci sur le site, une question ? Écris-nous, on lit tout.
        </p>

        <?php if ($success): ?>
            <p class="success"><i class="fa-solid fa-circle-check" style="color:var(--vert);"></i> Merci ! Ton message a bien été envoyé.</p>
            <p style="text-align:center;"><a href="index.php">← Retour à l'accueil</a></p>
        <?php else: ?>

            <?php if ($erreur): ?>
                <p class="erreur"><i class="fa-solid fa-circle-xmark" style="color:white;"></i> <?= e($erreur) ?></p>
            <?php endif; ?>

            <form action="retours.php" method="POST">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label>C'est à propos de...</label>
                    <select name="type" class="select-form">
                        <?php foreach ($types as $val => $libelle): ?>
                            <option value="<?= e($val) ?>" <?= $type === $val ? 'selected' : '' ?>><?= e($libelle) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Ton message</label>
                    <textarea name="message" required maxlength="3000" style="height:160px;"
                              placeholder="Ex : Ce serait pratique de pouvoir filtrer les trajets par date..."><?= e($_POST['message'] ?? ($trajet_signale ? "Je signale l'annonce n°" . $trajet_signale . " (trajet.php?id=" . $trajet_signale . ") car : " : '')) ?></textarea>
                </div>

                <?php if (!$connecte): ?>
                <div class="form-group">
                    <label>Ton nom <span class="facultatif">(facultatif)</span></label>
                    <input type="text" name="nom" maxlength="100" value="<?= e($_POST['nom'] ?? '') ?>">
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Ton e-mail <span class="facultatif">(facultatif, pour qu'on puisse te répondre)</span></label>
                    <input type="email" name="email" maxlength="150" value="<?= e($_POST['email'] ?? '') ?>">
                </div>

                <!-- Piège à robots : invisible pour les humains -->
                <div class="champ-piege" aria-hidden="true">
                    <label>Site web</label>
                    <input type="text" name="site_web" tabindex="-1" autocomplete="off">
                </div>

                <button type="submit" class="btn-publier"><i class="fa-solid fa-paper-plane"></i> Envoyer</button>
            </form>
        <?php endif; ?>
    </section>

<?php include 'footer.php'; ?>
</body>
</html>
