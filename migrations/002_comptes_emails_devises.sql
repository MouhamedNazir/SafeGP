-- ============================================================
-- Migration 002 : confirmation d'e-mail, mot de passe oublié,
-- notifications par e-mail, devise des trajets
--
-- À exécuter UNE SEULE FOIS, après 001_ameliorations.sql
-- (phpMyAdmin → base safegp → onglet SQL → coller → Exécuter).
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE users
    ADD COLUMN email_verifie TINYINT(1) NOT NULL DEFAULT 0 AFTER email,
    ADD COLUMN notif_email   TINYINT(1) NOT NULL DEFAULT 1 AFTER email_verifie; -- e-mail quand on reçoit un message

-- Les comptes déjà inscrits sont considérés comme confirmés
UPDATE users SET email_verifie = 1;

-- Devise du prix au kilo
ALTER TABLE trajets
    ADD COLUMN devise ENUM('MAD', 'EUR', 'USD', 'XOF') NOT NULL DEFAULT 'MAD' AFTER prix_kilo;

-- Liens à usage unique envoyés par e-mail (seule leur empreinte est stockée)
CREATE TABLE jetons (
    id         INT(11) NOT NULL AUTO_INCREMENT,
    user_id    INT(11) NOT NULL,
    type       ENUM('verification', 'reinitialisation') NOT NULL,
    empreinte  CHAR(64) NOT NULL,
    expire_le  DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY empreinte (empreinte),
    KEY user_id (user_id),
    CONSTRAINT jetons_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
