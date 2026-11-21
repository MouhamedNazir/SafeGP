<?php
// ============================================================
// ENVOI D'E-MAILS (confirmation d'inscription, mot de passe oublié,
// notifications)
//
// Les hébergeurs gratuits (InfinityFree...) bloquent la fonction mail()
// de PHP : on passe donc par un serveur SMTP externe (Gmail, Brevo...),
// configuré dans config.php (voir config.example.php).
//
// En local sans SMTP configuré : rien n'est envoyé, chaque e-mail est
// enregistré dans logs/emails/ pour pouvoir cliquer sur les liens.
// ============================================================

function smtp_configure(): bool
{
    return defined('SMTP_HOTE') && SMTP_HOTE !== '';
}

// Peut-on « envoyer » des e-mails ? (SMTP configuré, ou mode local)
function email_actif(): bool
{
    return smtp_configure() || est_en_local();
}

// Adresse du site pour les liens dans les e-mails
function url_site(): string
{
    if (defined('SITE_URL') && SITE_URL !== '') {
        return rtrim(SITE_URL, '/');
    }
    return (est_en_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
         . rtrim(str_replace(DIRECTORY_SEPARATOR, '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
}

// Gabarit HTML commun à tous les e-mails
function email_gabarit(string $titre, string $contenu_html, ?string $bouton_texte = null, ?string $bouton_lien = null): string
{
    $bouton = '';
    if ($bouton_texte && $bouton_lien) {
        $bouton = '<p style="margin:30px 0;"><a href="' . htmlspecialchars($bouton_lien) . '" '
                . 'style="background:#f97316;color:#ffffff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;">'
                . htmlspecialchars($bouton_texte) . '</a></p>'
                . '<p style="font-size:12px;color:#64748b;">Si le bouton ne marche pas, copie ce lien dans ton navigateur :<br>'
                . htmlspecialchars($bouton_lien) . '</p>';
    }
    return '<!DOCTYPE html><html lang="fr"><body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;">'
         . '<div style="max-width:560px;margin:0 auto;padding:30px 20px;">'
         . '<h1 style="color:#0f172a;font-size:22px;">Safe<span style="color:#f97316;">GP</span></h1>'
         . '<div style="background:#ffffff;border-radius:10px;padding:25px;color:#0f172a;line-height:1.6;">'
         . '<h2 style="margin-top:0;font-size:18px;">' . htmlspecialchars($titre) . '</h2>'
         . $contenu_html . $bouton
         . '</div><p style="font-size:12px;color:#64748b;text-align:center;">SafeGP — colis entre le Maroc et le Sénégal</p>'
         . '</div></body></html>';
}

// Envoie un e-mail. Renvoie true si c'est parti (ou enregistré en local).
function email_envoyer(string $destinataire, string $sujet, string $html): bool
{
    if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Local sans SMTP : on enregistre l'e-mail dans un fichier
    if (!smtp_configure()) {
        if (!est_en_local()) {
            return false;
        }
        $dossier = __DIR__ . '/logs/emails';
        if (!is_dir($dossier)) @mkdir($dossier, 0775, true);
        $fichier = $dossier . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.html';
        $entete = '<!-- À : ' . htmlspecialchars($destinataire) . ' | Sujet : ' . htmlspecialchars($sujet) . " -->\n";
        return file_put_contents($fichier, $entete . $html) !== false;
    }

    try {
        smtp_envoyer($destinataire, $sujet, $html);
        return true;
    } catch (Throwable $ex) {
        error_log('[SafeGP] E-mail non envoyé à ' . $destinataire . ' : ' . $ex->getMessage());
        return false;
    }
}

// ------------------------------------------------------------
// Client SMTP minimal : SSL (port 465), STARTTLS (port 587) ou sans
// chiffrement, avec authentification AUTH LOGIN.
// ------------------------------------------------------------
function smtp_envoyer(string $destinataire, string $sujet, string $html): void
{
    $hote = SMTP_HOTE;
    $port = defined('SMTP_PORT') ? (int)SMTP_PORT : 465;
    $securite = defined('SMTP_SECURITE') ? SMTP_SECURITE : 'ssl'; // 'ssl', 'starttls' ou 'aucune'
    $expediteur = defined('EMAIL_EXPEDITEUR') && EMAIL_EXPEDITEUR !== '' ? EMAIL_EXPEDITEUR : (defined('SMTP_UTILISATEUR') ? SMTP_UTILISATEUR : '');
    $nom_expediteur = defined('EMAIL_NOM') ? EMAIL_NOM : 'SafeGP';

    $adresse = ($securite === 'ssl' ? 'ssl://' : 'tcp://') . $hote . ':' . $port;
    $contexte = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $sock = @stream_socket_client($adresse, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $contexte);
    if (!$sock) {
        throw new RuntimeException("Connexion SMTP impossible ($errstr)");
    }
    stream_set_timeout($sock, 15);

    // Lit une réponse (éventuellement sur plusieurs lignes) et vérifie le code
    $lire = function (array $codes_ok) use ($sock): string {
        $reponse = '';
        while (($ligne = fgets($sock, 515)) !== false) {
            $reponse .= $ligne;
            if (isset($ligne[3]) && $ligne[3] === ' ') break;
        }
        $code = (int)substr($reponse, 0, 3);
        if (!in_array($code, $codes_ok, true)) {
            throw new RuntimeException('Réponse SMTP inattendue : ' . trim($reponse));
        }
        return $reponse;
    };
    $ecrire = function (string $commande, array $codes_ok) use ($sock, $lire): string {
        fwrite($sock, $commande . "\r\n");
        return $lire($codes_ok);
    };

    try {
        $nom_machine = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost') ?: 'localhost';
        $lire([220]);
        $ecrire('EHLO ' . $nom_machine, [250]);

        if ($securite === 'starttls') {
            $ecrire('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS impossible');
            }
            $ecrire('EHLO ' . $nom_machine, [250]);
        }

        if (defined('SMTP_UTILISATEUR') && SMTP_UTILISATEUR !== '') {
            $ecrire('AUTH LOGIN', [334]);
            $ecrire(base64_encode(SMTP_UTILISATEUR), [334]);
            $ecrire(base64_encode(SMTP_MOT_DE_PASSE), [235]);
        }

        $ecrire('MAIL FROM:<' . $expediteur . '>', [250]);
        $ecrire('RCPT TO:<' . $destinataire . '>', [250, 251]);
        $ecrire('DATA', [354]);

        $encoder = fn($texte) => '=?UTF-8?B?' . base64_encode($texte) . '?=';
        $message = 'Date: ' . date('r') . "\r\n"
            . 'From: ' . $encoder($nom_expediteur) . ' <' . $expediteur . ">\r\n"
            . 'To: <' . $destinataire . ">\r\n"
            . 'Subject: ' . $encoder($sujet) . "\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $expediteur)[1] ?? 'safegp') . ">\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html), 76, "\r\n");

        fwrite($sock, $message . "\r\n.\r\n");
        $lire([250]);
        $ecrire('QUIT', [221]);
    } finally {
        fclose($sock);
    }
}

// ============================================================
// JETONS À USAGE UNIQUE (confirmation d'e-mail, mot de passe oublié)
// Seule l'empreinte SHA-256 du jeton est stockée en base :
// une fuite de la base ne permet pas d'utiliser les liens.
// ============================================================
function jeton_creer(mysqli $conn, int $user_id, string $type, int $duree_minutes): string
{
    // Un seul jeton valide à la fois par type
    $stmt = $conn->prepare("DELETE FROM jetons WHERE user_id = ? AND type = ?");
    $stmt->bind_param("is", $user_id, $type);
    $stmt->execute();
    $stmt->close();

    $jeton = bin2hex(random_bytes(32));
    $empreinte = hash('sha256', $jeton);
    $expire = date('Y-m-d H:i:s', time() + $duree_minutes * 60);
    $stmt = $conn->prepare("INSERT INTO jetons (user_id, type, empreinte, expire_le) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $type, $empreinte, $expire);
    $stmt->execute();
    $stmt->close();
    return $jeton;
}

// Renvoie l'id de l'utilisateur si le jeton est valide (sans le consommer)
function jeton_verifier(mysqli $conn, string $jeton, string $type): ?int
{
    if (!preg_match('/^[a-f0-9]{64}$/', $jeton)) return null;
    $empreinte = hash('sha256', $jeton);
    $maintenant = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("SELECT user_id FROM jetons WHERE empreinte = ? AND type = ? AND expire_le > ?");
    $stmt->bind_param("sss", $empreinte, $type, $maintenant);
    $stmt->execute();
    $ligne = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ligne ? (int)$ligne['user_id'] : null;
}

function jeton_supprimer(mysqli $conn, int $user_id, string $type): void
{
    $stmt = $conn->prepare("DELETE FROM jetons WHERE user_id = ? AND type = ?");
    $stmt->bind_param("is", $user_id, $type);
    $stmt->execute();
    $stmt->close();
}

// ============================================================
// E-MAILS DU SITE
// ============================================================
function email_envoyer_confirmation(mysqli $conn, int $user_id, string $email, string $nom): bool
{
    $jeton = jeton_creer($conn, $user_id, 'verification', 60 * 48);
    $lien = url_site() . '/verifier_email.php?jeton=' . $jeton;
    $html = email_gabarit(
        'Confirme ton adresse e-mail',
        '<p>Bonjour ' . htmlspecialchars($nom) . ',</p><p>Bienvenue sur SafeGP ! Clique sur le bouton ci-dessous pour confirmer ton adresse e-mail. Le lien est valable 48 heures.</p>',
        'Confirmer mon e-mail',
        $lien
    );
    return email_envoyer($email, 'Confirme ton adresse e-mail - SafeGP', $html);
}

function email_envoyer_reinitialisation(mysqli $conn, int $user_id, string $email, string $nom): bool
{
    $jeton = jeton_creer($conn, $user_id, 'reinitialisation', 60);
    $lien = url_site() . '/reinitialiser.php?jeton=' . $jeton;
    $html = email_gabarit(
        'Réinitialiser ton mot de passe',
        '<p>Bonjour ' . htmlspecialchars($nom) . ',</p><p>Tu as demandé à changer ton mot de passe. Clique sur le bouton ci-dessous pour en choisir un nouveau. Le lien est valable 1 heure.</p>'
        . '<p>Si tu n\'as rien demandé, ignore simplement cet e-mail : ton mot de passe ne change pas.</p>',
        'Choisir un nouveau mot de passe',
        $lien
    );
    return email_envoyer($email, 'Réinitialisation de ton mot de passe - SafeGP', $html);
}
