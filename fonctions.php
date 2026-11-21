<?php
// ============================================================
// FONCTIONS COMMUNES — chargées par init.php sur toutes les pages
// ============================================================

// Un compte gratuit peut publier au plus 5 trajets par mois calendaire
const FREEMIUM_LIMITE_MOIS = 5;

// Nombre maximum de numéros WhatsApp (profil ou trajet)
const CONTACTS_MAX = 5;

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
        'arrivee'            => trim($_POST['arrivee'] ?? ''),
        'date_depart'        => $_POST['date_depart'] ?? '',
        'heure_depart'       => vide_en_null($_POST['heure_depart'] ?? ''),
        'date_arrivee'       => $_POST['date_arrivee'] ?? '',
        'heure_arrivee'      => vide_en_null($_POST['heure_arrivee'] ?? ''),
        'date_limite_depot'  => vide_en_null($_POST['date_limite_depot'] ?? ''),
        'prix_kilo'          => $_POST['prix_kilo'] ?? '',
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
