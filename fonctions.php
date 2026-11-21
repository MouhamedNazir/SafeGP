<?php
// ============================================================
// FONCTIONS COMMUNES — chargées par init.php sur toutes les pages
// ============================================================


// Nombre maximum de numéros WhatsApp (profil ou trajet)
const CONTACTS_MAX = 5;

// Devises du prix au kilo : code en base => [symbole affiché, libellé du menu]
const DEVISES = [
    'MAD' => ['DH', 'Dirham marocain (DH)'],
    'EUR' => ['€', 'Euro (€)'],
    'USD' => ['$', 'Dollar américain ($)'],
    'XOF' => ['FCFA', 'Franc CFA - Afrique de l’Ouest'],
    'XAF' => ['FCFA', 'Franc CFA - Afrique centrale'],
    'GBP' => ['£', 'Livre sterling (£)'],
    'CAD' => ['$ CA', 'Dollar canadien ($ CA)'],
    'CHF' => ['CHF', 'Franc suisse (CHF)'],
];

// "50.00", "EUR" → "50 €/kg"
function prix_kilo_fr($prix, ?string $devise): string
{
    $symbole = DEVISES[$devise ?? 'MAD'][0] ?? 'DH';
    $nombre = rtrim(rtrim(number_format((float)$prix, 2, ',', ' '), '0'), ',');
    return $nombre . ' ' . $symbole . '/kg';
}

// Raccourci pour échapper un texte à afficher
function e($texte): string
{
    return htmlspecialchars((string)($texte ?? ''), ENT_QUOTES, 'UTF-8');
}

// "14:30:00" → "14h30"
function heure_fr(?string $heure): string
{
    if (empty($heure)) return '';
    [$h, $m] = explode(':', $heure);
    return (int)$h . 'h' . $m;
}

// Date (+ heure si renseignée) au format français
function date_heure_fr(?string $date, ?string $heure = null): string
{
    if (empty($date)) return '';
    $txt = date('d/m/Y', strtotime($date));
    if (!empty($heure)) $txt .= ' à ' . heure_fr($heure);
    return $txt;
}

// Chaîne vide → NULL (pour les champs facultatifs en base)
function vide_en_null(?string $valeur): ?string
{
    $valeur = trim((string)$valeur);
    return $valeur === '' ? null : $valeur;
}

function telephone_valide(string $numero): bool
{
    return (bool)preg_match('/^[\+0-9\s]{8,20}$/', $numero);
}

// Lien wa.me à partir d'un numéro ("+212 6 12..." → "2126...")
function lien_whatsapp(string $numero): string
{
    return 'https://wa.me/' . preg_replace('/[^0-9]/', '', $numero);
}

// ============================================================
// TRAJETS : lecture et validation du formulaire
// (partagé entre publier.php et modifier_trajet.php)
// ============================================================
function trajet_depuis_post(): array
{
    $kilos_total = vide_en_null($_POST['kilos_total'] ?? '');
    $kilos_restants = vide_en_null($_POST['kilos_restants'] ?? '');
    $pour_autrui = !empty($_POST['pour_autrui']) ? 1 : 0;

    return [
        'depart'             => trim($_POST['depart'] ?? ''),
        'pays_depart'        => strtoupper(trim($_POST['pays_depart'] ?? '')),
        'arrivee'            => trim($_POST['arrivee'] ?? ''),
        'pays_arrivee'       => strtoupper(trim($_POST['pays_arrivee'] ?? '')),
        'date_depart'        => $_POST['date_depart'] ?? '',
        'heure_depart'       => vide_en_null($_POST['heure_depart'] ?? ''),
        'date_arrivee'       => $_POST['date_arrivee'] ?? '',
        'heure_arrivee'      => vide_en_null($_POST['heure_arrivee'] ?? ''),
        'date_limite_depot'  => vide_en_null($_POST['date_limite_depot'] ?? ''),
        'prix_kilo'          => $_POST['prix_kilo'] ?? '',
        'devise'             => $_POST['devise'] ?? 'MAD',
        'kilos_total'        => $kilos_total,
        'kilos_restants'     => $kilos_restants,
        'telephone'          => vide_en_null($_POST['telephone'] ?? ''),
        'commentaire'        => vide_en_null($_POST['commentaire'] ?? ''),
        'point_depot'        => vide_en_null($_POST['point_depot'] ?? ''),
        'horaires_depot'     => vide_en_null($_POST['horaires_depot'] ?? ''),
        'point_reception'    => vide_en_null($_POST['point_reception'] ?? ''),
        'horaires_reception' => vide_en_null($_POST['horaires_reception'] ?? ''),
        'pour_autrui'        => $pour_autrui,
        'voyageur_nom'       => $pour_autrui ? vide_en_null($_POST['voyageur_nom'] ?? '') : null,
    ];
}

// Renvoie un message d'erreur, ou "" si tout est bon.
// $t est modifié : kilos restants ajustés, heures nettoyées.
function trajet_valider(array &$t, array $contacts): string
{
    $format_heure = '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';

    if ($t['depart'] === '' || $t['arrivee'] === '') {
        return "Les villes de départ et d'arrivée sont obligatoires.";
    }
    if (!isset(PAYS[$t['pays_depart']]) || !isset(PAYS[$t['pays_arrivee']])) {
        return "Choisis le pays de départ et le pays d'arrivée.";
    }
    if (!strtotime($t['date_depart']) || !strtotime($t['date_arrivee'])) {
        return "Les dates de départ et d'arrivée sont obligatoires.";
    }
    if ($t['date_depart'] < date('Y-m-d')) {
        return "La date de départ ne peut pas être dans le passé.";
    }
    if ($t['date_arrivee'] < $t['date_depart']) {
        return "La date d'arrivée doit être après la date de départ.";
    }
    foreach (['heure_depart', 'heure_arrivee'] as $champ) {
        if ($t[$champ] !== null && !preg_match($format_heure, $t[$champ])) {
            return "Heure invalide.";
        }
    }
    if ($t['date_arrivee'] === $t['date_depart'] && $t['heure_depart'] && $t['heure_arrivee']
        && $t['heure_arrivee'] < $t['heure_depart']) {
        return "Le même jour, l'heure d'arrivée doit être après l'heure de départ.";
    }
    if ($t['date_limite_depot'] !== null) {
        if (!strtotime($t['date_limite_depot'])) {
            return "Date limite de dépôt invalide.";
        }
        if ($t['date_limite_depot'] > $t['date_depart']) {
            return "La date limite de dépôt doit être au plus tard le jour du départ.";
        }
    }
    if (!is_numeric($t['prix_kilo']) || $t['prix_kilo'] < 0) {
        return "Prix par kilo invalide.";
    }
    if (!isset(DEVISES[$t['devise']])) {
        return "Devise invalide.";
    }
    if ($t['kilos_total'] !== null) {
        if (!is_numeric($t['kilos_total']) || $t['kilos_total'] <= 0 || $t['kilos_total'] > 9999) {
            return "La capacité totale (kg) doit être un nombre positif.";
        }
        // Kilos restants : par défaut = capacité totale, et jamais au-dessus
        if ($t['kilos_restants'] === null) {
            $t['kilos_restants'] = $t['kilos_total'];
        } elseif (!is_numeric($t['kilos_restants']) || $t['kilos_restants'] < 0) {
            return "Les kilos restants doivent être un nombre positif.";
        } elseif ($t['kilos_restants'] > $t['kilos_total']) {
            return "Les kilos restants ne peuvent pas dépasser la capacité totale.";
        }
    } else {
        $t['kilos_restants'] = null;
    }
    if ($t['telephone'] !== null && !telephone_valide($t['telephone'])) {
        return "Numéro de téléphone invalide.";
    }
    if ($t['pour_autrui']) {
        if ($t['voyageur_nom'] === null) {
            return "Indique le nom de la personne pour qui tu publies ce trajet.";
        }
        if (count($contacts) === 0) {
            return "Ajoute au moins un numéro WhatsApp pour joindre ce voyageur.";
        }
    }
    return "";
}

// ============================================================
// NUMÉROS WHATSAPP
// ============================================================

// Lit les lignes "nom + numéro" envoyées par le formulaire.
// Les lignes entièrement vides sont ignorées.
function contacts_depuis_post(string &$erreur): array
{
    $noms = (array)($_POST['contact_nom'] ?? []);
    $numeros = (array)($_POST['contact_numero'] ?? []);
    $contacts = [];

    foreach ($numeros as $i => $numero) {
        $numero = trim((string)$numero);
        $nom = trim((string)($noms[$i] ?? ''));
        if ($numero === '' && $nom === '') continue;

        if ($numero === '' || $nom === '') {
            $erreur = "Chaque numéro WhatsApp doit avoir un numéro et le nom de la personne à qui il appartient.";
            return $contacts;
        }
        if (!telephone_valide($numero)) {
            $erreur = "Numéro WhatsApp invalide : " . e($numero);
            return $contacts;
        }
        $contacts[] = ['nom' => mb_substr($nom, 0, 100), 'numero' => $numero];
    }

    if (count($contacts) > CONTACTS_MAX) {
        $erreur = "Maximum " . CONTACTS_MAX . " numéros WhatsApp.";
    }
    return $contacts;
}

// Remplace les numéros du profil ($trajet_id = null) ou d'un trajet
function contacts_enregistrer(mysqli $conn, int $user_id, ?int $trajet_id, array $contacts): void
{
    if ($trajet_id === null) {
        $del = $conn->prepare("DELETE FROM contacts_whatsapp WHERE user_id = ? AND trajet_id IS NULL");
        $del->bind_param("i", $user_id);
    } else {
        $del = $conn->prepare("DELETE FROM contacts_whatsapp WHERE user_id = ? AND trajet_id = ?");
        $del->bind_param("ii", $user_id, $trajet_id);
    }
    $del->execute();
    $del->close();

    $ins = $conn->prepare("INSERT INTO contacts_whatsapp (user_id, trajet_id, nom, numero) VALUES (?, ?, ?, ?)");
    foreach ($contacts as $c) {
        $ins->bind_param("iiss", $user_id, $trajet_id, $c['nom'], $c['numero']);
        $ins->execute();
    }
    $ins->close();
}

// Numéros du profil d'un utilisateur
function contacts_profil(mysqli $conn, int $user_id): array
{
    $stmt = $conn->prepare("SELECT nom, numero FROM contacts_whatsapp WHERE user_id = ? AND trajet_id IS NULL ORDER BY id");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $res;
}

// Numéros propres à un trajet
function contacts_trajet(mysqli $conn, int $trajet_id): array
{
    $stmt = $conn->prepare("SELECT nom, numero FROM contacts_whatsapp WHERE trajet_id = ? ORDER BY id");
    $stmt->bind_param("i", $trajet_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $res;
}

// Pour une liste de trajets, renvoie [trajet_id => numéros à afficher] :
// les numéros du trajet s'il est publié pour quelqu'un d'autre,
// sinon les numéros du profil du GP.
function contacts_pour_trajets(mysqli $conn, array $trajets): array
{
    $user_ids = [];
    $trajet_ids = [];
    foreach ($trajets as $t) {
        if ($t['pour_autrui']) $trajet_ids[] = (int)$t['id'];
        elseif ($t['user_id']) $user_ids[] = (int)$t['user_id'];
    }

    $par_user = [];
    $par_trajet = [];
    // Les ids sont des entiers (cast ci-dessus) : pas d'injection possible
    if ($user_ids) {
        $liste = implode(',', array_unique($user_ids));
        $res = $conn->query("SELECT user_id, nom, numero FROM contacts_whatsapp WHERE trajet_id IS NULL AND user_id IN ($liste) ORDER BY id");
        while ($c = $res->fetch_assoc()) $par_user[$c['user_id']][] = $c;
    }
    if ($trajet_ids) {
        $liste = implode(',', array_unique($trajet_ids));
        $res = $conn->query("SELECT trajet_id, nom, numero FROM contacts_whatsapp WHERE trajet_id IN ($liste) ORDER BY id");
        while ($c = $res->fetch_assoc()) $par_trajet[$c['trajet_id']][] = $c;
    }

    $resultat = [];
    foreach ($trajets as $t) {
        $resultat[$t['id']] = $t['pour_autrui']
            ? ($par_trajet[$t['id']] ?? [])
            : ($par_user[$t['user_id']] ?? []);
    }
    return $resultat;
}

// ============================================================
// ABONNEMENTS
// Gratuit : 4 publications par mois calendaire
// Standard : 8 par mois — 500 FCFA/mois ou 5 000 FCFA/an (2 mois offerts)
// Illimité : sans limite — 750 FCFA/mois ou 7 500 FCFA/an (2 mois offerts)
// ============================================================
const FORMULES = [
    'gratuit'  => ['nom' => 'Gratuit',  'limite' => 4,    'prix_mois' => 0,   'prix_an' => 0],
    'standard' => ['nom' => 'Standard', 'limite' => 8,    'prix_mois' => 500, 'prix_an' => 5000],
    'illimite' => ['nom' => 'Illimité', 'limite' => null, 'prix_mois' => 750, 'prix_an' => 7500],
];

// Taux indicatifs pour afficher l'équivalent des prix en FCFA.
// L'euro est à parité fixe (1 € = 655,957 FCFA) ; dollar et dirham
// sont approximatifs : à ajuster de temps en temps.
const TAUX_FCFA = [
    'EUR' => 655.957,
    'USD' => 600,
    'MAD' => 60,
];

const MOYENS_PAIEMENT = ['wave' => 'Wave', 'orange_money' => 'Orange Money', 'virement' => 'Virement bancaire', 'autre' => 'Autre'];

function prix_fcfa(int $montant): string
{
    return number_format($montant, 0, ',', ' ') . ' FCFA';
}

// "≈ 0,76 € · 0,83 $ · 8 DH"
function equivalents_prix(int $montant_fcfa): string
{
    $fmt = fn($v) => rtrim(rtrim(number_format($v, 2, ',', ' '), '0'), ',');
    return '≈ ' . $fmt($montant_fcfa / TAUX_FCFA['EUR']) . ' € · '
         . $fmt($montant_fcfa / TAUX_FCFA['USD']) . ' $ · '
         . $fmt(round($montant_fcfa / TAUX_FCFA['MAD'])) . ' DH';
}

function prix_formule(string $formule, string $duree): int
{
    return (int)(FORMULES[$formule][$duree === 'an' ? 'prix_an' : 'prix_mois'] ?? 0);
}

// Formule réellement active : une formule payante expirée redevient « gratuit »
function formule_active(array $u): string
{
    $f = $u['formule'] ?? 'gratuit';
    if ($f === 'gratuit' || !isset(FORMULES[$f])) return 'gratuit';
    if (!empty($u['abonnement_fin']) && $u['abonnement_fin'] < date('Y-m-d')) return 'gratuit';
    return $f;
}

// Renvoie ['formule', 'nom', 'limite', 'illimite', 'utilisees', 'restantes', 'fin']
function quota_publication(mysqli $conn, int $user_id): array
{
    $stmt = $conn->prepare("SELECT role, formule, abonnement_fin FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $debut_mois = date('Y-m-01 00:00:00');
    // Journal des publications : supprimer un trajet ne rend pas la publication
    $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM publications WHERE user_id = ? AND created_at >= ?");
    $stmt->bind_param("is", $user_id, $debut_mois);
    $stmt->execute();
    $utilisees = (int)$stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    $formule = formule_active($u);
    $limite = FORMULES[$formule]['limite'];
    $illimite = $limite === null || ($u['role'] ?? '') === 'admin';
    return [
        'formule'   => $formule,
        'nom'       => FORMULES[$formule]['nom'],
        'limite'    => $limite,
        'illimite'  => $illimite,
        'utilisees' => $utilisees,
        'restantes' => $illimite ? null : max(0, $limite - $utilisees),
        'fin'       => $formule !== 'gratuit' ? ($u['abonnement_fin'] ?? null) : null,
    ];
}

// Active une demande d'abonnement validée par l'admin.
// Même formule encore active → on prolonge à partir de la fin actuelle ;
// sinon on démarre aujourd'hui.
function abonnement_valider(mysqli $conn, int $demande_id): ?array
{
    $stmt = $conn->prepare("SELECT a.*, u.formule AS formule_actuelle, u.abonnement_fin, u.email, u.nom
        FROM abonnements a JOIN users u ON u.id = a.user_id
        WHERE a.id = ? AND a.statut = 'en_attente'");
    $stmt->bind_param("i", $demande_id);
    $stmt->execute();
    $d = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$d) return null;

    $aujourdhui = date('Y-m-d');
    $prolonge = $d['formule_actuelle'] === $d['formule'] && !empty($d['abonnement_fin']) && $d['abonnement_fin'] >= $aujourdhui;
    $debut = $prolonge ? date('Y-m-d', strtotime($d['abonnement_fin'] . ' +1 day')) : $aujourdhui;
    $fin = date('Y-m-d', strtotime($debut . ($d['duree'] === 'an' ? ' +1 year' : ' +1 month') . ' -1 day'));

    // On garde la formule d'avant : elle sera remise si l'abonnement est annulé sous 48 h
    $formule_avant = formule_active(['formule' => $d['formule_actuelle'], 'abonnement_fin' => $d['abonnement_fin']]);
    $fin_avant = $formule_avant === 'gratuit' ? null : $d['abonnement_fin'];

    $conn->begin_transaction();
    $stmt = $conn->prepare("UPDATE abonnements SET statut = 'valide', date_debut = ?, date_fin = ?, traite_le = NOW(), formule_avant = ?, fin_avant = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $debut, $fin, $formule_avant, $fin_avant, $demande_id);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare("UPDATE users SET formule = ?, abonnement_fin = ?, rappel_abonnement = NULL WHERE id = ?");
    $stmt->bind_param("ssi", $d['formule'], $fin, $d['user_id']);
    $stmt->execute();
    $stmt->close();
    $conn->commit();

    email_envoyer($d['email'], 'Ton abonnement ' . FORMULES[$d['formule']]['nom'] . ' est actif - SafeGP', email_gabarit(
        'Abonnement activé',
        '<p>Bonjour ' . htmlspecialchars($d['nom']) . ',</p><p>Ton paiement a été vérifié : ta formule <strong>'
        . htmlspecialchars(FORMULES[$d['formule']]['nom']) . '</strong> est active jusqu\'au <strong>'
        . date('d/m/Y', strtotime($fin)) . '</strong>. Merci pour ta confiance !</p>'
        . '<p>Ton reçu est disponible dans « Mon profil ». Tu peux annuler sous 48 heures si tu ne publies aucun trajet d’ici là.</p>',
        'Publier un trajet',
        url_site() . '/publier.php'
    ));
    return ['debut' => $debut, 'fin' => $fin];
}

function abonnement_refuser(mysqli $conn, int $demande_id, string $motif): bool
{
    $stmt = $conn->prepare("SELECT a.*, u.email, u.nom FROM abonnements a JOIN users u ON u.id = a.user_id WHERE a.id = ? AND a.statut = 'en_attente'");
    $stmt->bind_param("i", $demande_id);
    $stmt->execute();
    $d = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$d) return false;

    $motif = mb_substr(trim($motif), 0, 255) ?: 'Paiement introuvable';
    $stmt = $conn->prepare("UPDATE abonnements SET statut = 'refuse', motif_refus = ?, traite_le = NOW() WHERE id = ?");
    $stmt->bind_param("si", $motif, $demande_id);
    $stmt->execute();
    $stmt->close();

    email_envoyer($d['email'], 'Ta demande d\'abonnement - SafeGP', email_gabarit(
        'Demande d\'abonnement non validée',
        '<p>Bonjour ' . htmlspecialchars($d['nom']) . ',</p><p>Nous n\'avons pas pu valider ton paiement (référence '
        . htmlspecialchars($d['reference']) . ').</p><p><strong>Motif :</strong> ' . htmlspecialchars($motif)
        . '</p><p>Vérifie la référence et refais une demande, ou contacte-nous depuis « Donner mon avis ».</p>',
        'Voir les formules',
        url_site() . '/tarifs.php'
    ));
    return true;
}

// ============================================================
// AFFICHAGE
// ============================================================

// Boutons WhatsApp, chacun avec le nom de son propriétaire
function afficher_boutons_whatsapp(array $contacts): void
{
    foreach ($contacts as $c) {
        echo '<a href="' . e(lien_whatsapp($c['numero'])) . '" target="_blank" rel="noopener" class="btn-contact btn-whatsapp">'
           . '<i class="fa-brands fa-whatsapp"></i> ' . e($c['nom'])
           . '</a>';
    }
}

// Badge "disponible / indisponible" d'un GP
function badge_dispo_gp($disponible): string
{
    return $disponible
        ? '<span class="badge-dispo dispo-oui"><i class="fa-solid fa-circle"></i> Prend des colis</span>'
        : '<span class="badge-dispo dispo-non"><i class="fa-solid fa-circle"></i> Indisponible pour le moment</span>';
}

// ============================================================
// SUPPRESSION D'UN COMPTE (par l'utilisateur ou par l'admin)
// Les anciennes tables (messages, trajets) n'ont pas de suppression
// en cascade : on efface d'abord ce qui dépend du compte, tout ou
// rien (transaction). Numéros WhatsApp et jetons partent en cascade,
// les retours clients sont conservés sans auteur.
// ============================================================
function supprimer_utilisateur(mysqli $conn, int $id): bool
{
    $stmt = $conn->prepare("SELECT photo FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$u) return false;
    // Protection : jamais de suppression pendant qu'un colis est en cours
    if (envois_en_cours_utilisateur($conn, $id) > 0) return false;

    // Fichiers privés liés au compte (preuves de paiement, photos des colis,
    // pièce d'identité en attente) : supprimés avec lui
    $fichiers = [];
    foreach ([
        "SELECT preuve FROM abonnements WHERE user_id = $id AND preuve IS NOT NULL",
        "SELECT et.photo FROM envois_etapes et JOIN envois e ON e.id = et.envoi_id WHERE (e.gp_id = $id OR e.expediteur_id = $id) AND et.photo IS NOT NULL",
    ] as $sql) {
        $res = $conn->query($sql);
        while ($f = $res->fetch_row()) $fichiers[] = __DIR__ . '/preuves/' . $f[0];
    }
    $res = $conn->query("SELECT fichier FROM verifications WHERE user_id = $id AND fichier IS NOT NULL");
    while ($f = $res->fetch_row()) $fichiers[] = __DIR__ . '/verifications/' . $f[0];

    $conn->begin_transaction();
    try {
        foreach ([
            "DELETE FROM messages WHERE expediteur_id = ? OR destinataire_id = ?",
            "DELETE FROM trajets WHERE user_id = ? AND user_id = ?",
            "DELETE FROM users WHERE id = ? AND id = ?",
        ] as $sql) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id, $id);
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();
    } catch (mysqli_sql_exception $ex) {
        $conn->rollback();
        error_log('[SafeGP] Suppression du compte ' . $id . ' impossible : ' . $ex->getMessage());
        return false;
    }

    foreach ($fichiers as $f) {
        if (preg_match('/^[a-f0-9]{32}\.(img|jpg)$/', basename($f))) @unlink($f);
    }

    // Photo de profil (nom de fichier contrôlé : user_<id>.<ext>)
    if (!empty($u['photo']) && preg_match('/^user_\d+\.(jpe?g|png|webp)$/', $u['photo'])) {
        @unlink(__DIR__ . '/uploads/' . $u['photo']);
    }
    return true;
}

// ============================================================
// MESSAGERIE
// ============================================================
const MOIS_FR = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

// "Aujourd'hui", "Hier", "12 déc." ou "12 déc. 2025"
function libelle_jour(string $date_heure): string
{
    $jour = date('Y-m-d', strtotime($date_heure));
    if ($jour === date('Y-m-d')) return "Aujourd'hui";
    if ($jour === date('Y-m-d', strtotime('-1 day'))) return 'Hier';
    $ts = strtotime($jour);
    $txt = (int)date('j', $ts) . ' ' . MOIS_FR[(int)date('n', $ts) - 1];
    return date('Y', $ts) === date('Y') ? $txt : $txt . ' ' . date('Y', $ts);
}

// Heure courte pour la liste des conversations : "14:05", "Hier", "12 déc."
function heure_conversation(string $date_heure): string
{
    return date('Y-m-d', strtotime($date_heure)) === date('Y-m-d')
        ? date('H:i', strtotime($date_heure))
        : libelle_jour($date_heure);
}

// Résumé d'un trajet pour l'afficher dans la messagerie
function trajet_resume(array $t): array
{
    return [
        'id'    => (int)$t['id'],
        'titre' => $t['depart'] . ' → ' . $t['arrivee'],
        'pays'  => [strtolower((string)$t['pays_depart']), strtolower((string)$t['pays_arrivee'])],
        'date'  => date('d/m/Y', strtotime($t['date_depart'])),
        'prix'  => prix_kilo_fr($t['prix_kilo'], $t['devise'] ?? 'MAD'),
        'lien'  => 'trajet.php?id=' . (int)$t['id'],
    ];
}

// Message (ligne de la base + trajet joint) → données d'affichage,
// utilisées à la fois par le rendu PHP et par le JavaScript (JSON)
function message_affichage(array $m, int $moi): array
{
    return [
        'id'      => (int)$m['id'],
        'moi'     => (int)$m['expediteur_id'] === $moi,
        'contenu' => $m['contenu'],
        'heure'   => date('H:i', strtotime($m['created_at'])),
        'jour'    => t(libelle_jour($m['created_at'])),
        'lu'      => (bool)$m['lu'],
        'trajet'  => !empty($m['t_id']) ? trajet_resume([
            'id' => $m['t_id'], 'depart' => $m['t_depart'], 'arrivee' => $m['t_arrivee'],
            'pays_depart' => $m['t_pays_depart'], 'pays_arrivee' => $m['t_pays_arrivee'],
            'date_depart' => $m['t_date_depart'], 'prix_kilo' => $m['t_prix_kilo'],
            'devise' => $m['t_devise'], 'user_id' => $m['t_user_id'],
        ]) : null,
    ];
}

// Messages d'une conversation (tous, ou seulement ceux après $apres_id)
function messages_conversation(mysqli $conn, int $moi, int $autre, int $apres_id = 0): array
{
    $stmt = $conn->prepare("
        SELECT m.*, t.id AS t_id, t.depart AS t_depart, t.arrivee AS t_arrivee,
               t.pays_depart AS t_pays_depart, t.pays_arrivee AS t_pays_arrivee,
               t.date_depart AS t_date_depart, t.prix_kilo AS t_prix_kilo,
               t.devise AS t_devise, t.user_id AS t_user_id
        FROM messages m
        LEFT JOIN trajets t ON t.id = m.trajet_id
        WHERE ((m.expediteur_id = ? AND m.destinataire_id = ?)
            OR (m.expediteur_id = ? AND m.destinataire_id = ?))
          AND m.id > ?
        ORDER BY m.id ASC");
    $stmt->bind_param("iiiii", $moi, $autre, $autre, $moi, $apres_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_map(fn($m) => message_affichage($m, $moi), $res);
}

// Marque comme lus les messages reçus de $autre
function messages_marquer_lus(mysqli $conn, int $moi, int $autre): void
{
    $stmt = $conn->prepare("UPDATE messages SET lu = 1 WHERE expediteur_id = ? AND destinataire_id = ? AND lu = 0");
    $stmt->bind_param("ii", $autre, $moi);
    $stmt->execute();
    $stmt->close();
}

// Id du dernier de MES messages que l'autre a lu (pour l'indicateur « Vu »)
function message_vu_jusqua(mysqli $conn, int $moi, int $autre): int
{
    $stmt = $conn->prepare("SELECT COALESCE(MAX(id), 0) FROM messages WHERE expediteur_id = ? AND destinataire_id = ? AND lu = 1");
    $stmt->bind_param("ii", $moi, $autre);
    $stmt->execute();
    $id = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return $id;
}

// Envoie un message (+ e-mail de notification au premier non lu).
// Renvoie l'id du message, ou un texte d'erreur.
function message_envoyer(mysqli $conn, int $moi, string $moi_nom, int $destinataire_id, string $contenu, ?int $trajet_id)
{
    $contenu = trim($contenu);
    if ($contenu === '') return "Message vide.";
    if ($destinataire_id === $moi) return "Tu ne peux pas t'écrire à toi-même.";
    $contenu = mb_substr($contenu, 0, 2000);

    $stmt = $conn->prepare("SELECT id, nom, email, email_verifie, notif_email,
            (SELECT COUNT(*) FROM messages WHERE expediteur_id = ? AND destinataire_id = users.id AND lu = 0) AS deja_non_lus
        FROM users WHERE id = ?");
    $stmt->bind_param("ii", $moi, $destinataire_id);
    $stmt->execute();
    $destinataire = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$destinataire) return "Ce membre n'existe plus.";

    // Le trajet cité doit appartenir à l'un des deux interlocuteurs
    if ($trajet_id) {
        $stmt = $conn->prepare("SELECT id FROM trajets WHERE id = ? AND user_id IN (?, ?)");
        $stmt->bind_param("iii", $trajet_id, $moi, $destinataire_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_row()) $trajet_id = null;
        $stmt->close();
    }

    $stmt = $conn->prepare("INSERT INTO messages (expediteur_id, destinataire_id, trajet_id, contenu) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $moi, $destinataire_id, $trajet_id, $contenu);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    // Notification par e-mail : seulement pour le premier message non lu
    // de cet expéditeur, pour ne pas inonder la boîte du destinataire
    if ($destinataire['notif_email'] && $destinataire['email_verifie'] && (int)$destinataire['deja_non_lus'] === 0) {
        $html = email_gabarit(
            'Nouveau message de ' . $moi_nom,
            '<p>Bonjour ' . htmlspecialchars($destinataire['nom']) . ',</p>'
            . '<p><strong>' . htmlspecialchars($moi_nom) . '</strong> t’a envoyé un message sur SafeGP :</p>'
            . '<blockquote style="border-left:3px solid #f97316;margin:0;padding:5px 15px;color:#334155;">'
            . nl2br(htmlspecialchars(mb_strimwidth($contenu, 0, 300, '…'))) . '</blockquote>'
            . '<p style="font-size:12px;color:#64748b;">Tu peux désactiver ces e-mails dans « Mon profil ».</p>',
            'Répondre',
            url_site() . '/messagerie.php?with=' . $moi
        );
        email_envoyer($destinataire['email'], 'Nouveau message de ' . $moi_nom . ' - SafeGP', $html);
    }
    return $id;
}

// Page réservée aux membres connectés : sinon, connexion puis retour ici
function exiger_connexion(): void
{
    if (isset($_SESSION['user_id'])) return;
    $page = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    $suite = $page . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    header("Location: connexion.php?suite=" . urlencode($suite));
    exit();
}

// Photo de profil si le fichier existe vraiment, sinon l'initiale du nom
// (évite les images cassées quand un fichier a disparu du dossier uploads)
function avatar(?string $nom, ?string $photo, string $classe = ''): string
{
    if (!empty($photo) && preg_match('/^[\w.-]+$/', $photo) && is_file(__DIR__ . '/uploads/' . $photo)) {
        return '<img src="uploads/' . e($photo) . '?v=' . filemtime(__DIR__ . '/uploads/' . $photo) . '" alt="" class="' . e($classe) . '">';
    }
    return e(strtoupper(mb_substr((string)$nom, 0, 1)) ?: '?');
}

// ============================================================
// AVIS SUR LES GP
// Pour éviter les faux avis, on ne peut noter qu'un GP avec qui
// on a vraiment échangé (messages dans les deux sens).
// ============================================================
function peut_noter(mysqli $conn, int $auteur, int $gp): bool
{
    if ($auteur === $gp || !$auteur) return false;
    $stmt = $conn->prepare("SELECT
        EXISTS(SELECT 1 FROM messages WHERE expediteur_id = ? AND destinataire_id = ?) AND
        EXISTS(SELECT 1 FROM messages WHERE expediteur_id = ? AND destinataire_id = ?)");
    $stmt->bind_param("iiii", $auteur, $gp, $gp, $auteur);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return $ok;
}

// [user_id => ['moyenne' => 4.5, 'nombre' => 12]] pour une liste de membres
function avis_stats(mysqli $conn, array $user_ids): array
{
    $ids = array_unique(array_filter(array_map('intval', $user_ids)));
    if (!$ids) return [];
    $res = $conn->query("SELECT gp_id, AVG(note) AS moyenne, COUNT(*) AS nombre FROM avis WHERE gp_id IN (" . implode(',', $ids) . ") GROUP BY gp_id");
    $stats = [];
    while ($l = $res->fetch_assoc()) {
        $stats[$l['gp_id']] = ['moyenne' => round((float)$l['moyenne'], 1), 'nombre' => (int)$l['nombre']];
    }
    return $stats;
}

// Étoiles pleines / demi / vides pour une note de 0 à 5
function etoiles(float $note): string
{
    $html = '<span class="etoiles" aria-label="' . str_replace('.', ',', (string)$note) . ' sur 5">';
    for ($i = 1; $i <= 5; $i++) {
        if ($note >= $i) $html .= '<i class="fa-solid fa-star"></i>';
        elseif ($note >= $i - 0.5) $html .= '<i class="fa-solid fa-star-half-stroke"></i>';
        else $html .= '<i class="fa-regular fa-star"></i>';
    }
    return $html . '</span>';
}

// Petit résumé « ★★★★☆ 4,3 (12 avis) » ; vide s'il n'y a pas d'avis
function resume_avis(?array $stat): string
{
    if (!$stat || !$stat['nombre']) return '';
    return '<span class="resume-avis">' . etoiles($stat['moyenne']) . ' <strong>'
        . str_replace('.', ',', (string)$stat['moyenne']) . '</strong> <small>(' . $stat['nombre'] . ' avis)</small></span>';
}

// Badge « GP vérifié » (identité contrôlée par l'équipe)
function badge_verifie($verifie): string
{
    return $verifie ? '<span class="badge-verifie" title="Identité vérifiée par l\'équipe SafeGP"><i class="fa-solid fa-circle-check"></i> Vérifié</span>' : '';
}

// ============================================================
// ALERTES DE TRAJETS
// ============================================================
const ALERTES_MAX = 10;

// Libellé lisible d'une alerte : « Paris (France) → Sénégal »
function alerte_libelle(array $a): string
{
    $lieu = function ($ville, $pays) {
        if ($ville && $pays) return $ville . ' (' . nom_pays($pays) . ')';
        return $ville ?: ($pays ? nom_pays($pays) : 'Partout');
    };
    return $lieu($a['depart'], $a['pays_depart']) . ' → ' . $lieu($a['arrivee'], $a['pays_arrivee']);
}

// Après la publication d'un trajet : e-mail aux membres dont une alerte correspond
// (un seul e-mail par membre, jamais à l'auteur du trajet)
function alertes_notifier(mysqli $conn, int $trajet_id): int
{
    $stmt = $conn->prepare("
        SELECT DISTINCT u.id, u.nom, u.email, t.depart, t.arrivee, t.pays_depart, t.pays_arrivee,
               t.date_depart, t.prix_kilo, t.devise
        FROM trajets t
        JOIN alertes a ON (a.pays_depart IS NULL OR a.pays_depart = t.pays_depart)
                      AND (a.pays_arrivee IS NULL OR a.pays_arrivee = t.pays_arrivee)
                      AND (a.depart IS NULL OR t.depart LIKE CONCAT('%', a.depart, '%'))
                      AND (a.arrivee IS NULL OR t.arrivee LIKE CONCAT('%', a.arrivee, '%'))
        JOIN users u ON u.id = a.user_id
        WHERE t.id = ? AND a.user_id <> t.user_id AND u.email_verifie = 1");
    $stmt->bind_param("i", $trajet_id);
    $stmt->execute();
    $destinataires = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $vus = [];
    foreach ($destinataires as $d) {
        if (isset($vus[$d['id']])) continue;
        $vus[$d['id']] = true;
        $titre = $d['depart'] . ' → ' . $d['arrivee'];
        email_envoyer($d['email'], 'Nouveau trajet : ' . $titre . ' - SafeGP', email_gabarit(
            'Un trajet correspond à ton alerte',
            '<p>Bonjour ' . htmlspecialchars($d['nom']) . ',</p><p>Un GP vient de publier <strong>' . htmlspecialchars($titre)
            . '</strong> (' . htmlspecialchars(nom_pays($d['pays_depart']) . ' → ' . nom_pays($d['pays_arrivee'])) . '), départ le <strong>'
            . date('d/m/Y', strtotime($d['date_depart'])) . '</strong>, ' . htmlspecialchars(prix_kilo_fr($d['prix_kilo'], $d['devise'])) . '.</p>'
            . '<p style="font-size:12px;color:#64748b;">Tu peux gérer tes alertes dans « Mon profil ».</p>',
            'Voir le trajet',
            url_site() . '/trajet.php?id=' . $trajet_id
        ));
    }
    return count($vus);
}

// ============================================================
// IMAGES : redimensionnement + compression (si l'extension GD existe)
// Sans GD, l'image est gardée telle quelle.
// Renvoie le chemin final (l'extension peut devenir .jpg).
// ============================================================
function compresser_image(string $chemin, int $cote_max, int $qualite = 82): string
{
    if (!function_exists('imagecreatefromstring') || !is_file($chemin)) return $chemin;
    $infos = @getimagesize($chemin);
    $source = $infos ? @imagecreatefromstring(file_get_contents($chemin)) : false;
    if (!$source) return $chemin;

    [$l, $h] = $infos;
    $ratio = min(1, $cote_max / max($l, $h));
    $nl = max(1, (int)round($l * $ratio));
    $nh = max(1, (int)round($h * $ratio));

    // Fond blanc (les PNG transparents deviennent des JPEG)
    $image = imagecreatetruecolor($nl, $nh);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
    imagecopyresampled($image, $source, 0, 0, 0, 0, $nl, $nh, $l, $h);

    // Photos de téléphone : respecter l'orientation enregistrée (EXIF)
    if (function_exists('exif_read_data') && ($infos[2] ?? 0) === IMAGETYPE_JPEG) {
        $exif = @exif_read_data($chemin);
        $rotation = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($rotation) $image = imagerotate($image, $rotation, 0);
    }

    $destination = preg_replace('/\.[a-z0-9]+$/i', '', $chemin) . '.jpg';
    $ok = imagejpeg($image, $destination, $qualite);
    imagedestroy($image);
    imagedestroy($source);
    if (!$ok) return $chemin;
    if ($destination !== $chemin) @unlink($chemin);
    return $destination;
}

// Vérifie qu'un fichier envoyé est bien une image (JPEG, PNG, WEBP) de taille raisonnable
function image_envoyee_valide(array $fichier, int $taille_max): string
{
    if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return "Le fichier n'a pas pu être envoyé.";
    if ($fichier['size'] > $taille_max) return "Le fichier dépasse " . round($taille_max / 1048576) . " Mo.";
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($fichier['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || @getimagesize($fichier['tmp_name']) === false) {
        return "Le fichier doit être une photo (JPG, PNG ou WEBP).";
    }
    return "";
}

// ============================================================
// PAGINATION (panel admin)
// ============================================================
const PAR_PAGE = 25;

// Liens « ‹ 1 2 3 › » ; $param = nom du paramètre de page dans l'URL
function pagination(int $total, int $page, string $param, string $ancre = ''): string
{
    $nb_pages = (int)ceil($total / PAR_PAGE);
    if ($nb_pages <= 1) return '';
    $lien = function ($p) use ($param, $ancre) {
        $q = $_GET;
        $q[$param] = $p;
        return '?' . htmlspecialchars(http_build_query($q)) . $ancre;
    };
    $html = '<nav class="pagination" aria-label="Pages">';
    if ($page > 1) $html .= '<a href="' . $lien($page - 1) . '" aria-label="Page précédente">‹</a>';
    for ($p = 1; $p <= $nb_pages; $p++) {
        // On affiche la première, la dernière et les pages proches de la page courante
        if ($p === 1 || $p === $nb_pages || abs($p - $page) <= 2) {
            $html .= $p === $page ? '<span class="actif">' . $p . '</span>' : '<a href="' . $lien($p) . '">' . $p . '</a>';
        } elseif (abs($p - $page) === 3) {
            $html .= '<span class="points">…</span>';
        }
    }
    if ($page < $nb_pages) $html .= '<a href="' . $lien($page + 1) . '" aria-label="Page suivante">›</a>';
    return $html . '</nav>';
}

// ============================================================
// MOTS DE PASSE ROBUSTES
// Au moins 8 caractères, une lettre et un chiffre, pas un mot de
// passe courant, et pas le nom / l'e-mail / le nom d'utilisateur.
// ============================================================
const MOTS_DE_PASSE_COURANTS = [
    '12345678', '123456789', '1234567890', '87654321', '11111111', '00000000', '12341234', '11223344',
    'password', 'password1', 'password123', 'passw0rd', 'motdepasse', 'motdepasse1', 'motdepasse123',
    'azerty', 'azerty12', 'azerty123', 'azertyuiop', 'qwerty12', 'qwerty123', 'qwertyuiop', 'abc12345',
    'abcd1234', 'aaaaaaaa', 'iloveyou', 'iloveyou1', 'bonjour1', 'bonjour123', 'soleil123', 'marseille',
    'senegal1', 'senegal123', 'maroc123', 'dakar123', 'casablanca', 'safegp123', 'admin123', 'welcome1',
    'football', 'football1', 'sunshine1', 'princess1', 'dragon123', 'monkey123', 'letmein1', 'test1234',
];

function mot_de_passe_probleme(string $mdp, array $infos_perso = []): string
{
    if (strlen($mdp) < 8) {
        return "Le mot de passe doit faire au moins 8 caractères.";
    }
    if (!preg_match('/\p{L}/u', $mdp) || !preg_match('/\d/', $mdp)) {
        return "Le mot de passe doit contenir au moins une lettre et un chiffre.";
    }
    if (in_array(mb_strtolower($mdp), MOTS_DE_PASSE_COURANTS, true) || preg_match('/^(.)\1+$/', $mdp)) {
        return "Ce mot de passe est trop courant, choisis-en un autre.";
    }
    // Mots du nom, du nom d'utilisateur et de l'e-mail (avant le @)
    $mots = [];
    foreach ($infos_perso as $info) {
        $info = mb_strtolower(explode('@', (string)$info)[0]);
        $mots = array_merge($mots, preg_split('/[\s._\-]+/u', $info));
    }
    foreach ($mots as $mot) {
        if (mb_strlen($mot) >= 4 && str_contains(mb_strtolower($mdp), $mot)) {
            return "Le mot de passe ne doit pas contenir ton nom ou ton adresse e-mail.";
        }
    }
    return "";
}

// ============================================================
// ADRESSES E-MAIL : format, domaine qui reçoit vraiment des
// e-mails, adresses jetables refusées, fautes de frappe repérées
// ============================================================
const DOMAINES_JETABLES = [
    'yopmail.com', 'yopmail.fr', 'yopmail.net', 'mailinator.com', 'guerrillamail.com', 'guerrillamail.net',
    '10minutemail.com', '10minutemail.net', 'tempmail.com', 'temp-mail.org', 'trashmail.com', 'trashmail.fr',
    'getnada.com', 'dispostable.com', 'maildrop.cc', 'sharklasers.com', 'throwawaymail.com', 'fakeinbox.com',
    'jetable.org', 'mailnesia.com', 'mintemail.com', 'emailondeck.com', 'mohmal.com', 'tempmailo.com',
    'moakt.com', 'spamgourmet.com', 'mytemp.email', 'tempr.email', 'discard.email', 'burnermail.io',
];
const DOMAINES_COURANTS = [
    'gmail.com', 'yahoo.fr', 'yahoo.com', 'hotmail.com', 'hotmail.fr', 'outlook.com', 'outlook.fr',
    'live.fr', 'live.com', 'icloud.com', 'orange.fr', 'orange.sn', 'menara.ma', 'aol.com', 'protonmail.com',
];

function email_probleme(string $email): string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Adresse e-mail invalide.";
    }
    $domaine = strtolower(substr(strrchr($email, '@'), 1));
    if (in_array($domaine, DOMAINES_JETABLES, true)) {
        return "Les adresses e-mail jetables ne sont pas acceptées.";
    }
    if (in_array($domaine, DOMAINES_COURANTS, true) || preg_match('/\.test$/', $domaine)) {
        return ""; // domaines connus (et domaines de test) : pas besoin d'interroger le DNS
    }
    // Le domaine doit pouvoir recevoir des e-mails (serveur MX, ou à défaut une adresse)
    if (function_exists('checkdnsrr') && !checkdnsrr($domaine . '.', 'MX') && !checkdnsrr($domaine . '.', 'A')) {
        return "Ce domaine d'e-mail n'existe pas : vérifie ton adresse.";
    }
    return "";
}

// « tonnom@gmial.com » → « tonnom@gmail.com » (null si rien à suggérer)
function email_suggestion(string $email): ?string
{
    if (!str_contains($email, '@')) return null;
    [$local, $domaine] = explode('@', strtolower($email), 2);
    if (in_array($domaine, DOMAINES_COURANTS, true)) return null;
    foreach (DOMAINES_COURANTS as $connu) {
        if (levenshtein($domaine, $connu) <= 2) return $local . '@' . $connu;
    }
    return null;
}

// ============================================================
// TÂCHES QUOTIDIENNES (lancées par la première visite de la journée,
// l'hébergeur gratuit n'ayant pas de tâches planifiées)
// ============================================================
function taches_quotidiennes(mysqli $conn): void
{
    // 1. Trajets dont la date d'arrivée est passée → « arrivé »
    $conn->query("UPDATE trajets SET statut = 'arrivé' WHERE date_arrivee < CURDATE() AND statut <> 'arrivé'");

    // 2. Comptes jamais confirmés depuis 7 jours → supprimés
    //    (seulement quand les e-mails partent vraiment, sinon personne ne pourrait confirmer)
    if (function_exists('smtp_configure') && smtp_configure()) {
        // Seulement les comptes qui ont VRAIMENT reçu un lien de confirmation
        // il y a plus de 7 jours (pas ceux inscrits avant la mise en place du SMTP)
        $res = $conn->query("SELECT u.id FROM users u WHERE u.email_verifie = 0 AND u.role = 'user'
            AND EXISTS (SELECT 1 FROM jetons j WHERE j.user_id = u.id AND j.type = 'verification' AND j.created_at < NOW() - INTERVAL 7 DAY)");
        while ($u = $res->fetch_row()) supprimer_utilisateur($conn, (int)$u[0]);
    }

    // 3. Preuves de paiement de plus de 12 mois → fichier supprimé (la ligne reste pour l'historique)
    $res = $conn->query("SELECT id, preuve FROM abonnements WHERE preuve IS NOT NULL AND created_at < NOW() - INTERVAL 12 MONTH");
    while ($a = $res->fetch_assoc()) {
        if (preg_match('/^[a-f0-9]{32}\.(jpg|img)$/', $a['preuve'])) @unlink(__DIR__ . '/preuves/' . $a['preuve']);
        $conn->query("UPDATE abonnements SET preuve = NULL WHERE id = " . (int)$a['id']);
    }
}

// ============================================================
// FICHIERS PRIVÉS (preuves de paiement, photos des colis)
// Enregistrés dans preuves/ (fermé au web) sous un nom aléatoire,
// affichés seulement via fichier_prive.php qui vérifie les droits.
// ============================================================
function enregistrer_fichier_prive(array $fichier, string &$erreur): ?string
{
    $erreur = image_envoyee_valide($fichier, 8 * 1024 * 1024);
    if ($erreur) return null;
    $chemin = __DIR__ . '/preuves/' . bin2hex(random_bytes(16)) . '.img';
    if (!move_uploaded_file($fichier['tmp_name'], $chemin)) {
        $erreur = "L'envoi a échoué, réessaie.";
        return null;
    }
    return basename(compresser_image($chemin, 1600, 85));
}

// ============================================================
// ANNULATION D'ABONNEMENT : possible pendant 48 h après l'activation,
// si aucun trajet n'a été publié depuis (le service n'a pas servi)
// ============================================================
const ANNULATION_HEURES = 48;

function numero_recu(array $abonnement): string
{
    $annee = date('Y', strtotime($abonnement['traite_le'] ?? $abonnement['created_at']));
    return 'SGP-' . $annee . '-' . str_pad((string)$abonnement['id'], 4, '0', STR_PAD_LEFT);
}

// Renvoie "" si l'abonnement peut être annulé, sinon la raison
function abonnement_annulation_impossible(mysqli $conn, array $a): string
{
    if ($a['statut'] !== 'valide' || empty($a['traite_le'])) {
        return "Seul un abonnement validé peut être annulé.";
    }
    if (strtotime($a['traite_le']) < time() - ANNULATION_HEURES * 3600) {
        return "Le délai d'annulation de 48 heures est dépassé.";
    }
    $stmt = $conn->prepare("SELECT COUNT(*) FROM publications WHERE user_id = ? AND created_at >= ?");
    $stmt->bind_param("is", $a['user_id'], $a['traite_le']);
    $stmt->execute();
    $publies = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
    if ($publies > 0) {
        return "Tu as déjà publié un trajet avec cette formule : elle ne peut plus être annulée.";
    }
    // Seul le dernier abonnement validé peut être annulé
    $stmt = $conn->prepare("SELECT COUNT(*) FROM abonnements WHERE user_id = ? AND statut = 'valide' AND id > ?");
    $stmt->bind_param("ii", $a['user_id'], $a['id']);
    $stmt->execute();
    $plus_recent = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return $plus_recent ? "Un abonnement plus récent existe déjà." : "";
}

// Annule : on remet la formule d'avant et on prévient l'admin (remboursement à faire)
function abonnement_annuler(mysqli $conn, int $abonnement_id, int $user_id): string
{
    $stmt = $conn->prepare("SELECT a.*, u.nom, u.email FROM abonnements a JOIN users u ON u.id = a.user_id WHERE a.id = ? AND a.user_id = ?");
    $stmt->bind_param("ii", $abonnement_id, $user_id);
    $stmt->execute();
    $a = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$a) return "Abonnement introuvable.";
    if ($raison = abonnement_annulation_impossible($conn, $a)) return $raison;

    $formule_avant = $a['formule_avant'] ?: 'gratuit';
    $fin_avant = $formule_avant === 'gratuit' ? null : $a['fin_avant'];
    $conn->begin_transaction();
    $stmt = $conn->prepare("UPDATE abonnements SET statut = 'annule', annule_le = NOW() WHERE id = ?");
    $stmt->bind_param("i", $abonnement_id);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare("UPDATE users SET formule = ?, abonnement_fin = ?, rappel_abonnement = NULL WHERE id = ?");
    $stmt->bind_param("ssi", $formule_avant, $fin_avant, $user_id);
    $stmt->execute();
    $stmt->close();
    $conn->commit();

    $admins = $conn->query("SELECT email FROM users WHERE role = 'admin'");
    while ($admin = $admins->fetch_assoc()) {
        email_envoyer($admin['email'], 'Remboursement à faire : ' . prix_fcfa((int)$a['montant']), email_gabarit(
            'Abonnement annulé',
            '<p><strong>' . htmlspecialchars($a['nom']) . '</strong> a annulé son abonnement '
            . htmlspecialchars(FORMULES[$a['formule']]['nom']) . ' (reçu ' . numero_recu($a) . ').</p>'
            . '<p>À rembourser : <strong>' . prix_fcfa((int)$a['montant']) . '</strong> par '
            . htmlspecialchars(MOYENS_PAIEMENT[$a['moyen']]) . ($a['telephone'] ? ' au ' . htmlspecialchars($a['telephone']) : '') . '.</p>',
            'Voir dans le panel admin',
            url_site() . '/admin.php#abonnements'
        ));
    }
    email_envoyer($a['email'], 'Annulation de ton abonnement - SafeGP', email_gabarit(
        'Abonnement annulé',
        '<p>Bonjour ' . htmlspecialchars($a['nom']) . ',</p><p>Ton abonnement a bien été annulé. Tu seras remboursé de <strong>'
        . prix_fcfa((int)$a['montant']) . '</strong> sous quelques jours, par le moyen utilisé pour payer.</p>',
        'Mon abonnement',
        url_site() . '/mon_profil.php#abonnement'
    ));
    return "";
}

// ============================================================
// ENVOIS DE COLIS (réservation de kilos + suivi)
// demande → accepte → depose → en_route → arrive → remis
// (ou refuse / annule avant le dépôt)
// ============================================================
const STATUTS_ENVOI = [
    'demande'  => ['En attente du GP', 'fa-hourglass-half'],
    'accepte'  => ['Accepté par le GP', 'fa-handshake'],
    'refuse'   => ['Refusé par le GP', 'fa-circle-xmark'],
    'annule'   => ['Annulé', 'fa-ban'],
    'depose'   => ['Colis déposé', 'fa-box'],
    'en_route' => ['En route', 'fa-plane'],
    'arrive'   => ['Arrivé à destination', 'fa-location-dot'],
    'remis'    => ['Remis au destinataire', 'fa-circle-check'],
];
// Étapes normales, dans l'ordre (pour la frise de suivi)
const ETAPES_ENVOI = ['demande', 'accepte', 'depose', 'en_route', 'arrive', 'remis'];

// Qui peut faire passer un envoi à quel statut
const TRANSITIONS_GP = [
    'demande'  => ['accepte', 'refuse'],
    'accepte'  => ['depose', 'annule'],
    'depose'   => ['en_route'],
    'en_route' => ['arrive'],
    'arrive'   => ['remis'],
];
const TRANSITIONS_EXPEDITEUR = [
    'demande' => ['annule'],
    'accepte' => ['annule'],
];

// Libellés des boutons d'action
const ACTIONS_ENVOI = [
    'accepte'  => 'Accepter',
    'refuse'   => 'Refuser',
    'annule'   => 'Annuler l\'envoi',
    'depose'   => 'Colis reçu (déposé)',
    'en_route' => 'Parti : en route',
    'arrive'   => 'Arrivé à destination',
    'remis'    => 'Remis au destinataire',
];

function libelle_statut_envoi(string $statut): string
{
    return STATUTS_ENVOI[$statut][0] ?? $statut;
}

// Code de suivi lisible : SGP + 6 caractères sans ambiguïté (pas de 0/O, 1/I/L)
function envoi_nouveau_code(mysqli $conn): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    do {
        $code = 'SGP';
        for ($i = 0; $i < 6; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $stmt = $conn->prepare("SELECT 1 FROM envois WHERE code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $existe = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
    } while ($existe);
    return $code;
}

// Réserver des kilos sur un trajet. Renvoie le code de suivi, ou un texte d'erreur (préfixé "!")
function envoi_reserver(mysqli $conn, int $expediteur_id, int $trajet_id, array $d): string
{
    $stmt = $conn->prepare("SELECT t.*, u.nom AS gp_nom, u.email AS gp_email, u.disponible FROM trajets t JOIN users u ON u.id = t.user_id WHERE t.id = ?");
    $stmt->bind_param("i", $trajet_id);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $kilos = is_numeric($d['kilos'] ?? null) ? round((float)$d['kilos'], 1) : 0;
    $contenu = trim($d['contenu'] ?? '');
    $dest_nom = trim($d['destinataire_nom'] ?? '');
    $dest_tel = vide_en_null($d['destinataire_tel'] ?? '');

    if (!$t || $t['statut'] !== 'disponible' || $t['date_depart'] < date('Y-m-d')) return "!Ce trajet n'accepte plus de réservations.";
    if ((int)$t['user_id'] === $expediteur_id) return "!Tu ne peux pas réserver sur ton propre trajet.";
    if (!$t['disponible']) return "!Ce GP ne prend pas de colis pour le moment.";
    if (!empty($t['date_limite_depot']) && $t['date_limite_depot'] < date('Y-m-d')) return "!La date limite de dépôt de ce trajet est passée.";
    if ($kilos <= 0 || $kilos > 500) return "!Indique un poids valide (en kg).";
    if ($t['kilos_total'] !== null && $kilos > (float)$t['kilos_restants']) return "!Il ne reste que " . ((float)$t['kilos_restants'] + 0) . " kg sur ce trajet.";
    if (mb_strlen($contenu) < 3) return "!Décris le contenu du colis.";
    if ($dest_nom === '') return "!Indique le nom du destinataire.";
    if ($dest_tel !== null && !telephone_valide($dest_tel)) return "!Numéro du destinataire invalide.";

    $code = envoi_nouveau_code($conn);
    $prix = round($kilos * (float)$t['prix_kilo'], 2);
    $contenu = mb_substr($contenu, 0, 255);
    $dest_nom = mb_substr($dest_nom, 0, 100);
    // La route est copiée dans l'envoi : le suivi la garde même si le trajet est supprimé
    $stmt = $conn->prepare("INSERT INTO envois (code, trajet_id, depart, pays_depart, arrivee, pays_arrivee, date_depart, date_arrivee,
            gp_id, expediteur_id, kilos, prix_total, devise, contenu, destinataire_nom, destinataire_tel)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sissssssiiddssss", $code, $trajet_id, $t['depart'], $t['pays_depart'], $t['arrivee'], $t['pays_arrivee'],
        $t['date_depart'], $t['date_arrivee'], $t['user_id'], $expediteur_id, $kilos, $prix, $t['devise'], $contenu, $dest_nom, $dest_tel);
    $stmt->execute();
    $envoi_id = $stmt->insert_id;
    $stmt->close();
    envoi_ajouter_etape($conn, $envoi_id, 'demande', null, null, $expediteur_id);

    email_envoyer($t['gp_email'], 'Nouvelle réservation : ' . ($kilos + 0) . ' kg - SafeGP', email_gabarit(
        'Nouvelle demande de réservation',
        '<p>Bonjour ' . htmlspecialchars($t['gp_nom']) . ',</p><p>Un expéditeur souhaite envoyer <strong>' . ($kilos + 0) . ' kg</strong> ('
        . htmlspecialchars($contenu) . ') sur ton trajet <strong>' . htmlspecialchars($t['depart'] . ' → ' . $t['arrivee']) . '</strong>.</p>'
        . '<p>Accepte ou refuse la demande depuis « Mes envois ».</p>',
        'Voir la demande',
        url_site() . '/envoi.php?code=' . $code
    ));
    return $code;
}

function envoi_ajouter_etape(mysqli $conn, int $envoi_id, string $statut, ?string $note, ?string $photo, ?int $auteur): void
{
    $stmt = $conn->prepare("INSERT INTO envois_etapes (envoi_id, statut, note, photo, auteur_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $envoi_id, $statut, $note, $photo, $auteur);
    $stmt->execute();
    $stmt->close();
}

// L'utilisateur peut-il faire passer cet envoi à ce statut ? (sans rien modifier)
function envoi_action_permise(mysqli $conn, string $code, int $moi, string $nouveau): bool
{
    $stmt = $conn->prepare("SELECT statut, gp_id, expediteur_id FROM envois WHERE code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $e = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$e) return false;
    $permis = array_merge((int)$e['gp_id'] === $moi ? (TRANSITIONS_GP[$e['statut']] ?? []) : [],
        (int)$e['expediteur_id'] === $moi ? (TRANSITIONS_EXPEDITEUR[$e['statut']] ?? []) : []);
    return in_array($nouveau, $permis, true);
}

// Faire avancer un envoi. Renvoie "" si c'est fait, sinon le message d'erreur.
function envoi_changer_statut(mysqli $conn, string $code, int $moi, string $nouveau, ?string $note, ?string $photo): string
{
    $stmt = $conn->prepare("SELECT e.*, t.kilos_total, t.kilos_restants,
            g.nom AS gp_nom, g.email AS gp_email, x.nom AS exp_nom, x.email AS exp_email
        FROM envois e
        LEFT JOIN trajets t ON t.id = e.trajet_id
        JOIN users g ON g.id = e.gp_id
        JOIN users x ON x.id = e.expediteur_id
        WHERE e.code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $e = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$e) return "Envoi introuvable.";

    $est_gp = (int)$e['gp_id'] === $moi;
    $est_exp = (int)$e['expediteur_id'] === $moi;
    $permis = array_merge($est_gp ? (TRANSITIONS_GP[$e['statut']] ?? []) : [], $est_exp ? (TRANSITIONS_EXPEDITEUR[$e['statut']] ?? []) : []);
    if (!in_array($nouveau, $permis, true)) return "Cette action n'est pas possible pour cet envoi.";

    $conn->begin_transaction();
    // Kilos du trajet : réservés à l'acceptation, rendus en cas de refus / annulation après acceptation
    if ($e['trajet_id'] && $e['kilos_total'] !== null) {
        if ($nouveau === 'accepte') {
            if ((float)$e['kilos'] > (float)$e['kilos_restants']) {
                $conn->rollback();
                return "Il ne te reste que " . ((float)$e['kilos_restants'] + 0) . " kg sur ce trajet.";
            }
            $conn->query("UPDATE trajets SET kilos_restants = kilos_restants - " . (float)$e['kilos'] . " WHERE id = " . (int)$e['trajet_id']);
        } elseif ($nouveau === 'annule' && $e['statut'] === 'accepte') {
            $conn->query("UPDATE trajets SET kilos_restants = LEAST(kilos_total, kilos_restants + " . (float)$e['kilos'] . ") WHERE id = " . (int)$e['trajet_id']);
        }
    }
    $stmt = $conn->prepare("UPDATE envois SET statut = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $nouveau, $e['id']);
    $stmt->execute();
    $stmt->close();
    $note = $note !== null ? mb_substr(trim($note), 0, 255) : null;
    envoi_ajouter_etape($conn, (int)$e['id'], $nouveau, $note ?: null, $photo, $moi);
    $conn->commit();

    // On prévient l'autre personne
    [$dest_email, $dest_nom] = $est_gp ? [$e['exp_email'], $e['exp_nom']] : [$e['gp_email'], $e['gp_nom']];
    email_envoyer($dest_email, 'Colis ' . $code . ' : ' . libelle_statut_envoi($nouveau) . ' - SafeGP', email_gabarit(
        libelle_statut_envoi($nouveau),
        '<p>Bonjour ' . htmlspecialchars($dest_nom) . ',</p><p>L\'envoi <strong>' . htmlspecialchars($code) . '</strong> ('
        . htmlspecialchars(($e['depart'] ?? '') . ' → ' . ($e['arrivee'] ?? '')) . ') a un nouveau statut : <strong>'
        . htmlspecialchars(libelle_statut_envoi($nouveau)) . '</strong>.</p>' . ($note ? '<p>Note : ' . htmlspecialchars($note) . '</p>' : ''),
        'Voir l\'envoi',
        url_site() . '/envoi.php?code=' . $code
    ));
    return "";
}

// Frise des étapes d'un envoi : [statut => date ou null]
function envoi_frise(array $etapes): array
{
    $dates = [];
    foreach ($etapes as $et) $dates[$et['statut']] = $et['created_at'];
    $frise = [];
    foreach (ETAPES_ENVOI as $s) $frise[$s] = $dates[$s] ?? null;
    return $frise;
}

// Envois en cours (réservation acceptée, colis déposé, en route, arrivé) :
// tant qu'il y en a, ni le compte ni le trajet ne peuvent être supprimés,
// sinon l'autre personne perdrait le suivi et le reçu de son colis.
const STATUTS_ENVOI_EN_COURS = "'accepte', 'depose', 'en_route', 'arrive'";

function envois_en_cours_utilisateur(mysqli $conn, int $user_id): int
{
    return (int)$conn->query("SELECT COUNT(*) FROM envois WHERE (gp_id = $user_id OR expediteur_id = $user_id)
        AND statut IN (" . STATUTS_ENVOI_EN_COURS . ")")->fetch_row()[0];
}

function envois_en_cours_trajet(mysqli $conn, int $trajet_id): int
{
    return (int)$conn->query("SELECT COUNT(*) FROM envois WHERE trajet_id = $trajet_id
        AND statut IN ('demande', " . STATUTS_ENVOI_EN_COURS . ")")->fetch_row()[0];
}
