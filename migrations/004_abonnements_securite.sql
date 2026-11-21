-- ============================================================
-- Migration 004 : abonnements (Gratuit / Standard / Illimité),
-- changement d'e-mail, limitation des tentatives de connexion
--
-- À exécuter UNE SEULE FOIS, après 003_pays_messagerie.sql
-- (phpMyAdmin → base safegp → onglet SQL → coller → Exécuter).
-- ============================================================

SET NAMES utf8mb4;

-- Formule d'abonnement de chaque membre
-- abonnement_fin NULL avec une formule payante = offert sans date de fin
ALTER TABLE users
    ADD COLUMN formule ENUM('gratuit', 'standard', 'illimite') NOT NULL DEFAULT 'gratuit' AFTER premium,
    ADD COLUMN abonnement_fin DATE DEFAULT NULL AFTER formule,
    ADD COLUMN rappel_abonnement DATE DEFAULT NULL AFTER abonnement_fin,  -- dernière fin pour laquelle le rappel a été envoyé
    ADD COLUMN email_nouveau VARCHAR(150) DEFAULT NULL AFTER email;       -- nouvelle adresse en attente de confirmation

-- Les comptes « premium » offerts jusqu'ici deviennent « Illimité » sans date de fin
-- (l'ancienne colonne premium est conservée mais n'est plus utilisée)
UPDATE users SET formule = 'illimite' WHERE premium = 1;

-- Demandes d'abonnement et paiements (mobile money validé par l'admin)
CREATE TABLE abonnements (
    id          INT(11) NOT NULL AUTO_INCREMENT,
    user_id     INT(11) NOT NULL,
    formule     ENUM('standard', 'illimite') NOT NULL,
    duree       ENUM('mois', 'an') NOT NULL,
    montant     INT(11) NOT NULL,                          -- en FCFA
    moyen       ENUM('wave', 'orange_money', 'autre') NOT NULL,
    reference   VARCHAR(100) NOT NULL,                     -- référence de la transaction
    telephone   VARCHAR(20) DEFAULT NULL,                  -- numéro qui a payé
    statut      ENUM('en_attente', 'valide', 'refuse') NOT NULL DEFAULT 'en_attente',
    motif_refus VARCHAR(255) DEFAULT NULL,
    date_debut  DATE DEFAULT NULL,
    date_fin    DATE DEFAULT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    traite_le   DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY statut (statut),
    CONSTRAINT abonnements_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Jetons : ajout du type « changement d'e-mail »
ALTER TABLE jetons
    MODIFY COLUMN type ENUM('verification', 'reinitialisation', 'changement_email') NOT NULL;

-- Tentatives de connexion échouées (anti force brute)
CREATE TABLE connexions_echouees (
    id         INT(11) NOT NULL AUTO_INCREMENT,
    ip         VARCHAR(45) NOT NULL,
    email      VARCHAR(150) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ip_email (ip, email),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
