<?php
include 'config.php';
include 'navbar.php';

// Seuls les connectés peuvent accéder à la messagerie
if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ============================================================
// ENVOI D'UN MESSAGE
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['contenu'])) {
    $destinataire_id = (int)$_POST['destinataire_id'];
    $contenu = trim($_POST['contenu']);

    if (!empty($contenu) && $destinataire_id !== $user_id) {
        $stmt = $conn->prepare("INSERT INTO messages (expediteur_id, destinataire_id, contenu) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $destinataire_id, $contenu);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: messagerie.php?with=" . $destinataire_id);
    exit();
}

// ============================================================
// CONVERSATION ACTIVE
// Si ?with=X dans l'URL → on affiche la conversation avec X
// ============================================================
$with_id = (int)($_GET['with'] ?? 0);

if ($with_id) {
    // Marquer les messages reçus comme lus
    $stmt = $conn->prepare("UPDATE messages SET lu = 1 
        WHERE expediteur_id = ? AND destinataire_id = ? AND lu = 0");
    $stmt->bind_param("ii", $with_id, $user_id);
    $stmt->execute();
    $stmt->close();

    // Récupérer les infos de l'interlocuteur
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $with_id);
    $stmt->execute();
    $interlocuteur = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Récupérer les messages de la conversation
    $stmt = $conn->prepare("
        SELECT messages.*, users.nom, users.photo
        FROM messages
        JOIN users ON messages.expediteur_id = users.id
        WHERE (expediteur_id = ? AND destinataire_id = ?)
           OR (expediteur_id = ? AND destinataire_id = ?)
        ORDER BY created_at ASC
    ");
    $stmt->bind_param("iiii", $user_id, $with_id, $with_id, $user_id);
    $stmt->execute();
    $messages = $stmt->get_result();
    $stmt->close();
}

// ============================================================
// LISTE DES CONVERSATIONS
// On récupère toutes les personnes avec qui on a échangé
// ============================================================
$conversations = $conn->prepare("
    SELECT DISTINCT 
        users.id, users.nom, users.photo, users.username,
        -- Nombre de messages non lus
        (SELECT COUNT(*) FROM messages 
         WHERE expediteur_id = users.id 
         AND destinataire_id = ? 
         AND lu = 0) as non_lus
    FROM messages
    JOIN users ON (
        CASE 
            WHEN messages.expediteur_id = ? THEN messages.destinataire_id = users.id
            ELSE messages.expediteur_id = users.id
        END
    )
    WHERE messages.expediteur_id = ? OR messages.destinataire_id = ?
    ORDER BY non_lus DESC
");
$conversations->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$conversations->execute();
$convs = $conversations->get_result();
$conversations->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Messagerie - SafeGP</title>
</head>
<body>

<section class="messagerie">

    <div class="messagerie-container">

        <!-- LISTE DES CONVERSATIONS -->
        <div class="conv-liste">
            <h3><i class="fa-solid fa-message"></i> Messages</h3>

            <?php if ($convs->num_rows > 0): ?>
                <?php while($conv = $convs->fetch_assoc()): ?>
                <a href="messagerie.php?with=<?= $conv['id'] ?>" 
                   class="conv-item <?= ($with_id == $conv['id']) ? 'active' : '' ?>">
                    
                    <!-- Avatar -->
                    <div class="conv-avatar">
                        <?php if (!empty($conv['photo'])): ?>
                            <img src="uploads/<?= htmlspecialchars($conv['photo']) ?>"
                                 style="width:45px; height:45px; border-radius:50%; object-fit:cover;">
                        <?php else: ?>
                            <?= strtoupper(substr($conv['nom'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>

                    <!-- Nom + badge non lus -->
                    <div style="flex:1;">
                        <strong><?= htmlspecialchars($conv['nom']) ?></strong>
                        <p style="color:#94a3b8; font-size:12px; margin:2px 0 0;">
                            @<?= htmlspecialchars($conv['username'] ?? '') ?>
                        </p>
                    </div>

                    <!-- Badge non lus -->
                    <?php if ($conv['non_lus'] > 0): ?>
                        <span class="badge-nonlu"><?= $conv['non_lus'] ?></span>
                    <?php endif; ?>

                </a>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color:#94a3b8; padding:20px; text-align:center;">
                    Aucune conversation
                </p>
            <?php endif; ?>
        </div>

        <!-- CONVERSATION ACTIVE -->
        <div class="conv-messages">
            <?php if ($with_id && $interlocuteur): ?>

                <!-- Header conversation -->
                <div class="conv-header">
                    <div class="conv-avatar">
                        <?php if (!empty($interlocuteur['photo'])): ?>
                            <img src="uploads/<?= htmlspecialchars($interlocuteur['photo']) ?>"
                                 style="width:45px; height:45px; border-radius:50%; object-fit:cover;">
                        <?php else: ?>
                            <?= strtoupper(substr($interlocuteur['nom'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong><?= htmlspecialchars($interlocuteur['nom']) ?></strong>
                        <p style="color:#94a3b8; font-size:12px; margin:0;">
                            @<?= htmlspecialchars($interlocuteur['username'] ?? '') ?>
                        </p>
                    </div>
                </div>

                <!-- Messages -->
                <div class="messages-liste" id="messages-liste">
                    <?php while($msg = $messages->fetch_assoc()): ?>
                        <div class="message <?= ($msg['expediteur_id'] == $user_id) ? 'envoye' : 'recu' ?>">
                            <p><?= htmlspecialchars($msg['contenu']) ?></p>
                            <span class="msg-date">
                                <?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?>
                            </span>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Formulaire d'envoi -->
                <form action="messagerie.php" method="POST" class="msg-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="destinataire_id" value="<?= $with_id ?>">
                    <input type="text" name="contenu" 
                           placeholder="Écrire un message..." 
                           autocomplete="off" required>
                    <button type="submit">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>

            <?php else: ?>
                <div class="conv-vide">
                    <i class="fa-solid fa-message" style="font-size:50px; color:#1e293b;"></i>
                    <p>Sélectionne une conversation ou contacte un GP depuis son profil</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

</section>

<?php include 'footer.php'; ?>

<script>
    // Auto-scroll vers le bas des messages
    const liste = document.getElementById('messages-liste');
    if (liste) liste.scrollTop = liste.scrollHeight;
</script>

</body>
</html>