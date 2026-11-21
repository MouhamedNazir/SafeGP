<?php
require_once 'init.php';

// Seuls les connectés peuvent accéder à la messagerie
if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// ============================================================
// ENVOI D'UN MESSAGE (sans JavaScript ; sinon messages_api.php)
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['contenu'])) {
    $destinataire_id = (int)($_POST['destinataire_id'] ?? 0);
    $trajet_id = (int)($_POST['trajet_id'] ?? 0) ?: null;
    message_envoyer($conn, $user_id, $_SESSION['user_nom'] ?? 'Un membre', $destinataire_id, (string)$_POST['contenu'], $trajet_id);
    header("Location: messagerie.php?with=" . $destinataire_id);
    exit();
}

// ============================================================
// CONVERSATION ACTIVE (?with=X), éventuellement à propos d'un trajet (&trajet=T)
// ============================================================
$with_id = (int)($_GET['with'] ?? 0);
$interlocuteur = null;
$messages = [];
$vu_jusqua = 0;
$trajet_cite = null;

if ($with_id && $with_id !== $user_id) {
    $stmt = $conn->prepare("SELECT id, nom, username, photo, disponible FROM users WHERE id = ?");
    $stmt->bind_param("i", $with_id);
    $stmt->execute();
    $interlocuteur = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($interlocuteur) {
        messages_marquer_lus($conn, $user_id, $with_id);
        $messages = messages_conversation($conn, $user_id, $with_id);
        $vu_jusqua = message_vu_jusqua($conn, $user_id, $with_id);

        // « Contacter pour ce trajet » : le trajet doit appartenir à l'interlocuteur
        $trajet_demande = (int)($_GET['trajet'] ?? 0);
        if ($trajet_demande) {
            $stmt = $conn->prepare("SELECT * FROM trajets WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $trajet_demande, $with_id);
            $stmt->execute();
            $t = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($t) $trajet_cite = trajet_resume($t);
        }
    }
}

// ============================================================
// LISTE DES CONVERSATIONS : dernier message, heure, non lus
// ============================================================
$stmt = $conn->prepare("
    SELECT u.id, u.nom, u.photo, u.username,
           m.contenu AS dernier, m.created_at AS date_dernier, m.expediteur_id AS dernier_de,
           (SELECT COUNT(*) FROM messages WHERE expediteur_id = u.id AND destinataire_id = ? AND lu = 0) AS non_lus
    FROM (
        SELECT IF(expediteur_id = ?, destinataire_id, expediteur_id) AS autre, MAX(id) AS dernier_id
        FROM messages
        WHERE expediteur_id = ? OR destinataire_id = ?
        GROUP BY autre
    ) c
    JOIN messages m ON m.id = c.dernier_id
    JOIN users u ON u.id = c.autre
    ORDER BY m.id DESC
");
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$convs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

// Rendu d'un avatar (photo ou initiale)
function avatar_conv(array $u): string
{
    if (!empty($u['photo'])) {
        return '<img src="uploads/' . e($u['photo']) . '" alt="">';
    }
    return e(strtoupper(mb_substr($u['nom'], 0, 1)));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Messagerie - SafeGP</title>
</head>
<body>
<?php include 'navbar.php'; ?>

<section class="messagerie">

    <!-- Sur mobile : la liste OU la conversation ouverte, pas les deux -->
    <div class="messagerie-container <?= $interlocuteur ? 'conv-ouverte' : '' ?>">

        <!-- LISTE DES CONVERSATIONS -->
        <div class="conv-liste">
            <h3><i class="fa-solid fa-message"></i> Messages</h3>
            <div class="conv-recherche">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="conv-filtre" placeholder="Rechercher une conversation" aria-label="Rechercher une conversation">
            </div>

            <?php if ($convs): ?>
                <?php foreach ($convs as $conv): ?>
                <a href="messagerie.php?with=<?= (int)$conv['id'] ?>"
                   class="conv-item <?= $with_id == $conv['id'] ? 'active' : '' ?> <?= $conv['non_lus'] > 0 ? 'non-lue' : '' ?>"
                   data-nom="<?= e(sans_accents($conv['nom'] . ' ' . ($conv['username'] ?? ''))) ?>">
                    <div class="conv-avatar"><?= avatar_conv($conv) ?></div>
                    <div class="conv-texte">
                        <div class="conv-ligne1">
                            <strong><?= e($conv['nom']) ?></strong>
                            <span class="conv-heure"><?= e(heure_conversation($conv['date_dernier'])) ?></span>
                        </div>
                        <div class="conv-ligne2">
                            <span class="conv-apercu"><?= (int)$conv['dernier_de'] === $user_id ? 'Toi : ' : '' ?><?= e(mb_strimwidth($conv['dernier'], 0, 60, '…')) ?></span>
                            <?php if ($conv['non_lus'] > 0): ?>
                                <span class="badge-nonlu"><?= (int)$conv['non_lus'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
                <p class="conv-aucune" hidden>Aucune conversation trouvée</p>
            <?php elseif (!$interlocuteur): ?>
                <p class="conv-aucune">Aucune conversation pour l'instant.<br>Contacte un GP depuis une annonce.</p>
            <?php endif; ?>
        </div>

        <!-- CONVERSATION ACTIVE -->
        <div class="conv-messages">
            <?php if ($interlocuteur): ?>

                <div class="conv-header">
                    <a href="messagerie.php" class="conv-retour" aria-label="Retour aux conversations"><i class="fa-solid fa-arrow-left"></i></a>
                    <div class="conv-avatar"><?= avatar_conv($interlocuteur) ?></div>
                    <div>
                        <a href="profil.php?id=<?= (int)$interlocuteur['id'] ?>" class="conv-nom"><?= e($interlocuteur['nom']) ?></a>
                        <p class="conv-sous-titre">@<?= e($interlocuteur['username'] ?? '') ?></p>
                    </div>
                </div>

                <!-- Messages : rendus ici, puis complétés en direct par script.js -->
                <div class="messages-liste" id="messages-liste"
                     data-with="<?= (int)$with_id ?>" data-vu="<?= (int)$vu_jusqua ?>">
                    <?php $jour_precedent = null; ?>
                    <?php foreach ($messages as $m): ?>
                        <?php if ($m['jour'] !== $jour_precedent): $jour_precedent = $m['jour']; ?>
                            <div class="msg-jour"><span><?= e($m['jour']) ?></span></div>
                        <?php endif; ?>
                        <div class="message <?= $m['moi'] ? 'envoye' : 'recu' ?>" data-id="<?= $m['id'] ?>" data-jour="<?= e($m['jour']) ?>">
                            <?php if ($m['trajet']): $tr = $m['trajet']; ?>
                                <a class="msg-trajet" href="<?= e($tr['lien']) ?>">
                                    <span class="fi fi-<?= e($tr['pays'][0]) ?>"></span> <?= e($tr['titre']) ?> <span class="fi fi-<?= e($tr['pays'][1]) ?>"></span>
                                    <small><?= e($tr['date']) ?> · <?= e($tr['prix']) ?></small>
                                </a>
                            <?php endif; ?>
                            <p><?= nl2br(e($m['contenu'])) ?></p>
                            <span class="msg-date"><?= e($m['heure']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$messages): ?>
                        <p class="conv-debut">Début de ta conversation avec <?= e($interlocuteur['nom']) ?>.</p>
                    <?php endif; ?>
                </div>

                <!-- Formulaire d'envoi (Entrée = envoyer, Maj+Entrée = nouvelle ligne) -->
                <form action="messagerie.php" method="POST" class="msg-form" id="msg-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="destinataire_id" value="<?= (int)$with_id ?>">
                    <?php if ($trajet_cite): ?>
                        <div class="msg-trajet-cite">
                            <span><i class="fa-solid fa-plane"></i> À propos du trajet <strong><?= e($trajet_cite['titre']) ?></strong> du <?= e($trajet_cite['date']) ?></span>
                            <button type="button" class="msg-trajet-retirer" aria-label="Ne plus citer ce trajet"><i class="fa-solid fa-xmark"></i></button>
                            <input type="hidden" name="trajet_id" value="<?= (int)$trajet_cite['id'] ?>">
                        </div>
                    <?php endif; ?>
                    <div class="msg-saisie">
                        <textarea name="contenu" rows="1" maxlength="2000"
                                  placeholder="<?= $trajet_cite ? 'Ex : Bonjour, il vous reste de la place pour 5 kg ?' : 'Écrire un message...' ?>"
                                  autocomplete="off" required></textarea>
                        <button type="submit" aria-label="Envoyer"><i class="fa-solid fa-paper-plane"></i></button>
                    </div>
                </form>

            <?php else: ?>
                <div class="conv-vide">
                    <i class="fa-solid fa-comments"></i>
                    <p>Sélectionne une conversation, ou contacte un GP depuis une annonce.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

</section>

<?php include 'footer.php'; ?>

</body>
</html>
