-- ============================================================
-- Migration 006 : suivi des colis (envois), preuves de paiement,
-- reçus, annulation d'abonnement sous 48 h
--
-- À exécuter UNE SEULE FOIS, après 005_avis_alertes_verification.sql
-- (phpMyAdmin → base safegp → onglet SQL → coller → Exécuter).
-- ============================================================

SET NAMES utf8mb4;

-- Abonnements : preuve de paiement (capture), annulation et remboursement
ALTER TABLE abonnements
    MODIFY COLUMN statut ENUM('en_attente', 'valide', 'refuse', 'annule') NOT NULL DEFAULT 'en_attente',
    ADD COLUMN preuve VARCHAR(100) DEFAULT NULL AFTER telephone,            -- capture du reçu (dossier privé preuves/)
    ADD COLUMN formule_avant ENUM('gratuit', 'standard', 'illimite') DEFAULT NULL,  -- pour revenir en arrière si annulation
    ADD COLUMN fin_avant DATE DEFAULT NULL,
    ADD COLUMN annule_le DATETIME DEFAULT NULL,
    ADD COLUMN rembourse_le DATETIME DEFAULT NULL;

-- Envois : un expéditeur réserve des kilos sur le trajet d'un GP
CREATE TABLE envois (
    id                INT(11) NOT NULL AUTO_INCREMENT,
    code              CHAR(9) NOT NULL,                 -- code de suivi, ex : SGP7K3M9X
    trajet_id         INT(11) DEFAULT NULL,
    gp_id             INT(11) NOT NULL,
    expediteur_id     INT(11) NOT NULL,
    kilos             DECIMAL(6,1) NOT NULL,
    prix_total        DECIMAL(10,2) NOT NULL,
    devise            ENUM('MAD', 'EUR', 'USD', 'XOF', 'XAF', 'GBP', 'CAD', 'CHF') NOT NULL DEFAULT 'MAD',
    contenu           VARCHAR(255) NOT NULL,            -- contenu déclaré par l'expéditeur
    destinataire_nom  VARCHAR(100) NOT NULL,
    destinataire_tel  VARCHAR(20) DEFAULT NULL,
    statut            ENUM('demande', 'accepte', 'refuse', 'annule', 'depose', 'en_route', 'arrive', 'remis') NOT NULL DEFAULT 'demande',
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY code (code),
    KEY trajet_id (trajet_id),
    KEY gp_id (gp_id),
    KEY expediteur_id (expediteur_id),
    CONSTRAINT envois_trajet_fk FOREIGN KEY (trajet_id) REFERENCES trajets (id) ON DELETE SET NULL,
    CONSTRAINT envois_gp_fk FOREIGN KEY (gp_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT envois_expediteur_fk FOREIGN KEY (expediteur_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Historique de chaque envoi (avec photo de preuve au dépôt / à la remise)
CREATE TABLE envois_etapes (
    id         INT(11) NOT NULL AUTO_INCREMENT,
    envoi_id   INT(11) NOT NULL,
    statut     ENUM('demande', 'accepte', 'refuse', 'annule', 'depose', 'en_route', 'arrive', 'remis') NOT NULL,
    note       VARCHAR(255) DEFAULT NULL,
    photo      VARCHAR(100) DEFAULT NULL,               -- preuve photo (dossier privé preuves/)
    auteur_id  INT(11) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY envoi_id (envoi_id),
    CONSTRAINT etapes_envoi_fk FOREIGN KEY (envoi_id) REFERENCES envois (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
