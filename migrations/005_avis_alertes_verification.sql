-- ============================================================
-- Migration 005 : virement bancaire, avis sur les GP, alertes de
-- trajets, badge « GP vérifié », langue des membres
--
-- À exécuter UNE SEULE FOIS, après 004_abonnements_securite.sql
-- (phpMyAdmin → base safegp → onglet SQL → coller → Exécuter).
-- ============================================================

SET NAMES utf8mb4;

-- Paiement des abonnements par virement bancaire
ALTER TABLE abonnements
    MODIFY COLUMN moyen ENUM('wave', 'orange_money', 'virement', 'autre') NOT NULL;

-- Badge « GP vérifié » et langue préférée (fr / en) pour les e-mails
ALTER TABLE users
    ADD COLUMN verifie TINYINT(1) NOT NULL DEFAULT 0 AFTER disponible,
    ADD COLUMN langue CHAR(2) NOT NULL DEFAULT 'fr' AFTER verifie;

-- Avis laissés sur un GP (un seul par auteur et par GP, modifiable)
CREATE TABLE avis (
    id          INT(11) NOT NULL AUTO_INCREMENT,
    gp_id       INT(11) NOT NULL,
    auteur_id   INT(11) NOT NULL,
    note        TINYINT(1) NOT NULL,          -- 1 à 5 étoiles
    commentaire TEXT DEFAULT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY un_avis_par_auteur (gp_id, auteur_id),
    KEY auteur_id (auteur_id),
    CONSTRAINT avis_gp_fk FOREIGN KEY (gp_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT avis_auteur_fk FOREIGN KEY (auteur_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Alertes : « préviens-moi quand un trajet France → Sénégal est publié »
CREATE TABLE alertes (
    id           INT(11) NOT NULL AUTO_INCREMENT,
    user_id      INT(11) NOT NULL,
    pays_depart  CHAR(2) DEFAULT NULL,
    depart       VARCHAR(100) DEFAULT NULL,
    pays_arrivee CHAR(2) DEFAULT NULL,
    arrivee      VARCHAR(100) DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    CONSTRAINT alertes_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Demandes de vérification d'identité (la photo est supprimée après traitement)
CREATE TABLE verifications (
    id          INT(11) NOT NULL AUTO_INCREMENT,
    user_id     INT(11) NOT NULL,
    fichier     VARCHAR(100) DEFAULT NULL,    -- nom aléatoire dans le dossier privé verifications/
    statut      ENUM('en_attente', 'valide', 'refuse') NOT NULL DEFAULT 'en_attente',
    motif_refus VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    traite_le   DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY statut (statut),
    CONSTRAINT verifications_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
