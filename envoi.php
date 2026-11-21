<?php
// ============================================================
// DÉTAIL D'UN ENVOI (GP, expéditeur, admin) : suivi, actions,
// preuves photo au dépôt et à la remise, reçu de dépôt
// ============================================================
require_once 'init.php';
exiger_connexion();
$moi = (int)$_SESSION['user_id'];
$code = strtoupper(trim($_GET['code'] ?? ''));

// ---------- Action (changement de statut) ----------
$erreur = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $photo = null;
    if (!empty($_FILES['photo']['name'])) {
        $photo = enregistrer_fichier_prive($_FILES['photo'], $erreur);
    }
    if (!$erreur) {
        $erreur = envoi_changer_statut($conn, $code, $moi, (string)($_POST['statut'] ?? ''), $_POST['note'] ?? null, $photo);
    }
    if (!$erreur) {
        header("Location: envoi.php?code=" . urlencode($code) . "&maj=1");
        exit();
    }
}

$stmt = $conn->prepare("
    SELECT e.*, t.depart, t.arrivee, t.pays_depart, t.pays_arrivee, t.date_depart, t.date_arrivee, t.point_depot, t.point_reception,
           g.nom AS gp_nom, g.username AS gp_username, x.nom AS exp_nom, x.username AS exp_username
    FROM envois e
    LEFT JOIN trajets t ON t.id = e.trajet_id
    JOIN users g ON g.id = e.gp_id
    JOIN users x ON x.id = e.expediteur_id
    WHERE e.code = ?");
$stmt->bind_param("s", $code);
$stmt->execute();
$e = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$e || (!$nav_is_admin && !in_array($moi, [(int)$e['gp_id'], (int)$e['expediteur_id']], true))) {
    $titre_404 = 'Envoi introuvable';
    $texte_404 = "Cet envoi n'existe pas ou ne te concerne pas. Pour suivre un colis avec son code, utilise la page de suivi.";
    include '404.php';
}

$stmt = $conn->prepare("SELECT et.*, u.nom AS auteur FROM envois_etapes et LEFT JOIN users u ON u.id = et.auteur_id WHERE envoi_id = ? ORDER BY et.id");
$stmt->bind_param("i", $e['id']);
$stmt->execute();
$etapes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$est_gp = (int)$e['gp_id'] === $moi;
$est_exp = (int)$e['expediteur_id'] === $moi;
$actions = array_merge($est_gp ? (TRANSITIONS_GP[$e['statut']] ?? []) : [], $est_exp ? (TRANSITIONS_EXPEDITEUR[$e['statut']] ?? []) : []);
$frise = envoi_frise($etapes);
$termine = in_array($e['statut'], ['refuse', 'annule'], true);
$recu_dispo = in_array($e['statut'], ['depose', 'en_route', 'arrive', 'remis'], true);
$lien_suivi = url_site() . '/suivi.php?code=' . $e['code'];
$devise = DEVISES[$e['devise']][0] ?? '';
$kg = fn($x) => str_replace('.', ',', (string)((float)$x + 0));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include 'head.php'; ?>
    <title>Envoi <?= e($e['code']) ?> - SafeGP</title>
    <meta name="robots" content="noindex">
</head>
<body>
<?php include 'navbar.php'; ?>

<section class="resultats page-envoi">
    <p class="fil-ariane"><a href="envois.php">Mes envois</a> › <?= e($e['code']) ?></p>

    <?php if (isset($_GET['nouveau'])): ?>
        <p class="success"><i class="fa-solid fa-circle-check"></i> Demande envoyée au GP ! Tu seras prévenu par e-mail dès qu'il répond.</p>
    <?php elseif (isset($_GET['maj'])): ?>
        <p class="success"><i class="fa-solid fa-circle-check"></i> Envoi mis à jour.</p>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <p class="erreur"><i class="fa-solid fa-circle-xmark"></i> <?= e($erreur) ?></p>
    <?php endif; ?>

    <div class="envoi-entete">
        <div>
            <span class="envoi-code-label">Code de suivi</span>
            <h2 class="envoi-code"><?= e($e['code']) ?></h2>
            <p class="carte-trajet-route">
                <span><?= drapeau($e['pays_depart']) ?> <?= e($e['depart'] ?? '—') ?></span>
                <i class="fa-solid fa-plane"></i>
                <span><?= drapeau($e['pays_arrivee']) ?> <?= e($e['arrivee'] ?? '—') ?></span>
            </p>
        </div>
        <span class="badge-envoi statut-<?= e($e['statut']) ?>"><i class="fa-solid <?= STATUTS_ENVOI[$e['statut']][1] ?>"></i> <?= e(libelle_statut_envoi($e['statut'])) ?></span>
    </div>

    <!-- Frise de suivi -->
    <?php if (!$termine): ?>
        <ol class="frise">
            <?php foreach ($frise as $statut => $date): ?>
                <li class="<?= $date ? 'fait' : '' ?> <?= $statut === $e['statut'] ? 'actuel' : '' ?>">
                    <span class="frise-point"><i class="fa-solid <?= STATUTS_ENVOI[$statut][1] ?>"></i></span>
                    <span class="frise-texte"><?= e(libelle_statut_envoi($statut)) ?></span>
                    <?php if ($date): ?><small><?= date('d/m H:i', strtotime($date)) ?></small><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <div class="envoi-grille">
        <div class="annonce envoi-details">
            <div class="annonce-body">
                <div class="info-ligne"><span><i class="fa-solid fa-user"></i> GP :</span><span><a href="profil.php?id=<?= (int)$e['gp_id'] ?>">@<?= e($e['gp_username'] ?? $e['gp_nom']) ?></a></span></div>
                <div class="info-ligne"><span><i class="fa-solid fa-user"></i> Expéditeur :</span><span><a href="profil.php?id=<?= (int)$e['expediteur_id'] ?>">@<?= e($e['exp_username'] ?? $e['exp_nom']) ?></a></span></div>
                <div class="info-ligne"><span><i class="fa-solid fa-calendar"></i> Départ :</span><span><?= $e['date_depart'] ? date('d/m/Y', strtotime($e['date_depart'])) : '—' ?></span></div>
                <div class="info-ligne"><span><i class="fa-solid fa-weight-hanging"></i> Poids :</span><span><?= $kg($e['kilos']) ?> kg</span></div>
                <div class="info-ligne"><span><i class="fa-solid fa-coins"></i> Prix :</span><span><strong><?= e(rtrim(rtrim(number_format((float)$e['prix_total'], 2, ',', ' '), '0'), ',') . ' ' . $devise) ?></strong></span></div>
                <div class="info-ligne"><span><i class="fa-solid fa-box-open"></i> Contenu déclaré :</span><span><?= e($e['contenu']) ?></span></div>
                <div class="info-ligne"><span><i class="fa-solid fa-user-check"></i> Destinataire :</span><span><?= e($e['destinataire_nom']) ?><?= $e['destinataire_tel'] ? ' · ' . e($e['destinataire_tel']) : '' ?></span></div>
                <?php if ($e['point_depot']): ?><div class="info-ligne"><span><i class="fa-solid fa-box"></i> Dépôt :</span><span><?= e($e['point_depot']) ?></span></div><?php endif; ?>
                <?php if ($e['point_reception']): ?><div class="info-ligne"><span><i class="fa-solid fa-location-dot"></i> Réception :</span><span><?= e($e['point_reception']) ?></span></div><?php endif; ?>
            </div>
            <div class="annonce-footer">
                <a href="messagerie.php?with=<?= $est_gp ? (int)$e['expediteur_id'] : (int)$e['gp_id'] ?><?= $e['trajet_id'] ? '&amp;trajet=' . (int)$e['trajet_id'] : '' ?>" class="btn-contact btn-secondaire"><i class="fa-solid fa-message"></i> <?= $est_gp ? 'Écrire à l\'expéditeur' : 'Écrire au GP' ?></a>
                <?php if ($recu_dispo): ?>
                    <a href="recu_envoi.php?code=<?= e($e['code']) ?>" target="_blank" class="btn-contact btn-secondaire"><i class="fa-solid fa-receipt"></i> Reçu de dépôt</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="envoi-cote">
            <!-- Actions possibles -->
            <?php if ($actions): ?>
                <div class="bloc-actions-envoi">
                    <h3>Que veux-tu faire ?</h3>
                    <?php foreach ($actions as $action): ?>
                        <form action="envoi.php?code=<?= e($e['code']) ?>" method="POST" enctype="multipart/form-data" class="form-action-envoi"
                              <?= in_array($action, ['refuse', 'annule'], true) ? 'onsubmit="return confirm(\'Confirmer ?\')"' : '' ?>>
                            <?= csrf_field() ?>
                            <input type="hidden" name="statut" value="<?= e($action) ?>">
                            <?php if (in_array($action, ['depose', 'remis'], true)): ?>
                                <label class="petit-label"><?= $action === 'depose' ? 'Photo du colis reçu' : 'Photo de la remise' ?> <span class="facultatif">(preuve, conseillé)</span></label>
                                <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">
                                <input type="text" name="note" maxlength="255" class="select-petit" placeholder="<?= $action === 'depose' ? 'Note (ex : 4,8 kg pesés, bien emballé)' : 'Note (ex : remis à Moussa en main propre)' ?>">
                            <?php elseif (in_array($action, ['refuse', 'annule'], true)): ?>
                                <input type="text" name="note" maxlength="255" class="select-petit" placeholder="Raison (facultatif)">
                            <?php endif; ?>
                            <button type="submit" class="btn-contact <?= in_array($action, ['refuse', 'annule'], true) ? 'btn-supprimer' : '' ?>">
                                <i class="fa-solid <?= STATUTS_ENVOI[$action][1] ?>"></i> <?= e(ACTIONS_ENVOI[$action]) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Partager le suivi avec le destinataire -->
            <?php if (!$termine): ?>
                <div class="bloc-actions-envoi">
                    <h3>Suivi pour le destinataire</h3>
                    <p class="aide-contact">Le destinataire peut suivre le colis avec ce lien, sans compte.</p>
                    <div class="partage-boutons">
                        <a class="btn-contact btn-whatsapp" target="_blank" rel="noopener"
                           href="https://wa.me/<?= e(preg_replace('/\D/', '', (string)$e['destinataire_tel'])) ?>?text=<?= rawurlencode('📦 Suivi de ton colis SafeGP ' . $e['code'] . ' : ' . $lien_suivi) ?>"><i class="fa-brands fa-whatsapp"></i> Envoyer</a>
                        <button type="button" class="btn-contact btn-secondaire copier" data-copier="<?= e($lien_suivi) ?>"><i class="fa-regular fa-copy"></i> Copier le lien</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Historique et preuves -->
    <h3 class="titre-section">Historique</h3>
    <ul class="historique-envoi">
        <?php foreach (array_reverse($etapes) as $et): ?>
            <li>
                <span class="badge-envoi statut-<?= e($et['statut']) ?>"><i class="fa-solid <?= STATUTS_ENVOI[$et['statut']][1] ?>"></i> <?= e(libelle_statut_envoi($et['statut'])) ?></span>
                <span class="msg-date"><?= date('d/m/Y H:i', strtotime($et['created_at'])) ?><?= $et['auteur'] ? ' · ' . e($et['auteur']) : '' ?></span>
                <?php if ($et['note']): ?><p><?= e($et['note']) ?></p><?php endif; ?>
                <?php if ($et['photo']): ?>
                    <a href="fichier_prive.php?type=etape&amp;id=<?= (int)$et['id'] ?>" target="_blank" rel="noopener" class="preuve-photo">
                        <img src="fichier_prive.php?type=etape&amp;id=<?= (int)$et['id'] ?>" alt="Photo de preuve" loading="lazy">
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php include 'footer.php'; ?>
</body>
</html>
