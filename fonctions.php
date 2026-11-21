<?php
// ============================================================
// FONCTIONS COMMUNES — chargées par init.php sur toutes les pages
// ============================================================

// Un compte gratuit peut publier au plus 5 trajets par mois calendaire
const FREEMIUM_LIMITE_MOIS = 5;

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
// FREEMIUM
// ============================================================

// Renvoie ['illimite' => bool, 'utilisees' => int, 'restantes' => int|null]
function quota_publication(mysqli $conn, int $user_id): array
{
    $stmt = $conn->prepare("SELECT role, premium FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $debut_mois = date('Y-m-01 00:00:00');
    $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM trajets WHERE user_id = ? AND created_at >= ?");
    $stmt->bind_param("is", $user_id, $debut_mois);
    $stmt->execute();
    $utilisees = (int)$stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    $illimite = $u && ($u['premium'] || $u['role'] === 'admin');
    return [
        'illimite'  => $illimite,
        'utilisees' => $utilisees,
        'restantes' => $illimite ? null : max(0, FREEMIUM_LIMITE_MOIS - $utilisees),
    ];
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
        'lien'  => 'profil.php?id=' . (int)$t['user_id'] . '#trajet-' . (int)$t['id'],
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
        'jour'    => libelle_jour($m['created_at']),
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
