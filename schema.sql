-- ============================================================
-- SafeGP : structure de la base de données
-- Utilisation : créer la base puis importer ce fichier
--   CREATE DATABASE safegp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   mysql -u root -p safegp < schema.sql   (ou "Importer" dans phpMyAdmin)
-- Pour devenir admin après inscription :
--   UPDATE users SET role = 'admin' WHERE email = 'ton@email.com';
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom           VARCHAR(100) NOT NULL,
    username      VARCHAR(50)  DEFAULT NULL,
    email         VARCHAR(190) NOT NULL,
    mot_de_passe  VARCHAR(255) NOT NULL,          -- hash password_hash()
    whatsapp      VARCHAR(30)  DEFAULT NULL,
    photo         VARCHAR(255) DEFAULT NULL,      -- nom du fichier dans uploads/
    role          ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_email (email),
    UNIQUE KEY uniq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trajets (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED NOT NULL,
    depart           VARCHAR(100) NOT NULL,
    arrivee          VARCHAR(100) NOT NULL,
    date_depart      DATE NOT NULL,
    date_arrivee     DATE NOT NULL,
    prix_kilo        DECIMAL(10,2) NOT NULL,      -- en MAD
    telephone        VARCHAR(30)  DEFAULT NULL,
    commentaire      TEXT         DEFAULT NULL,
    point_depot      VARCHAR(255) DEFAULT NULL,
    point_reception  VARCHAR(255) DEFAULT NULL,
    statut           ENUM('disponible', 'en_route', 'arrivé') NOT NULL DEFAULT 'disponible',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recherche (depart, arrivee),
    CONSTRAINT fk_trajets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    expediteur_id    INT UNSIGNED NOT NULL,
    destinataire_id  INT UNSIGNED NOT NULL,
    contenu          TEXT NOT NULL,
    lu               TINYINT(1) NOT NULL DEFAULT 0,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_conversation (expediteur_id, destinataire_id),
    CONSTRAINT fk_messages_expediteur   FOREIGN KEY (expediteur_id)   REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_destinataire FOREIGN KEY (destinataire_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
