-- ============================================================
-- Migration 001 : retours clients, WhatsApp multiples,
-- disponibilités, heures, kilos, freemium
--
-- À exécuter UNE SEULE FOIS sur une base existante
-- (phpMyAdmin → base safegp → onglet SQL → coller → Exécuter).
-- Pense à exporter ta base avant, par sécurité.
-- Une nouvelle installation utilise directement schema.sql.
-- ============================================================

SET NAMES utf8mb4;

-- ---------- UTILISATEURS ----------
ALTER TABLE users
    ADD COLUMN premium         TINYINT(1) NOT NULL DEFAULT 0 AFTER role,   -- 1 = pas de limite de publication
    ADD COLUMN disponible      TINYINT(1) NOT NULL DEFAULT 1 AFTER premium, -- le GP prend des colis en ce moment
    ADD COLUMN joignable_debut TIME DEFAULT NULL AFTER disponible,          -- plage horaire où on peut l'appeler
    ADD COLUMN joignable_fin   TIME DEFAULT NULL AFTER joignable_debut;

-- ---------- TRAJETS ----------
ALTER TABLE trajets
    ADD COLUMN heure_depart       TIME DEFAULT NULL AFTER date_depart,
    ADD COLUMN heure_arrivee      TIME DEFAULT NULL AFTER date_arrivee,
    ADD COLUMN date_limite_depot  DATE DEFAULT NULL AFTER heure_arrivee,    -- dernier jour pour déposer un colis
    ADD COLUMN kilos_total        DECIMAL(6,1) DEFAULT NULL AFTER prix_kilo,
    ADD COLUMN kilos_restants     DECIMAL(6,1) DEFAULT NULL AFTER kilos_total,
    ADD COLUMN horaires_depot     VARCHAR(100) DEFAULT NULL AFTER point_depot,     -- ex : "Lun–Sam 9h–18h"
    ADD COLUMN horaires_reception VARCHAR(100) DEFAULT NULL AFTER point_reception,
    ADD COLUMN pour_autrui        TINYINT(1) NOT NULL DEFAULT 0,             -- publié pour un autre voyageur
    ADD COLUMN voyageur_nom       VARCHAR(100) DEFAULT NULL,                 -- nom de ce voyageur
    ADD COLUMN created_at         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;  -- sert à la limite freemium

-- Les trajets déjà publiés n'ont pas de vraie date de publication :
-- on la laisse vide pour qu'ils ne comptent pas dans la limite du mois en cours
UPDATE trajets SET created_at = NULL;

-- ---------- NUMÉROS WHATSAPP ----------
-- trajet_id NULL  → numéro du profil (affiché sur toutes les annonces du GP)
-- trajet_id rempli → numéro propre à un trajet publié pour quelqu'un d'autre
CREATE TABLE contacts_whatsapp (
    id         INT(11) NOT NULL AUTO_INCREMENT,
    user_id    INT(11) NOT NULL,
    trajet_id  INT(11) DEFAULT NULL,
    nom        VARCHAR(100) NOT NULL,   -- à qui appartient ce numéro
    numero     VARCHAR(20)  NOT NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY trajet_id (trajet_id),
    CONSTRAINT contacts_user_fk   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE,
    CONSTRAINT contacts_trajet_fk FOREIGN KEY (trajet_id) REFERENCES trajets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- On reprend le numéro WhatsApp unique de chaque profil
-- (l'ancienne colonne users.whatsapp est conservée mais n'est plus utilisée)
INSERT INTO contacts_whatsapp (user_id, nom, numero)
SELECT id, nom, whatsapp FROM users WHERE whatsapp IS NOT NULL AND TRIM(whatsapp) <> '';

-- ---------- RETOURS CLIENTS ----------
CREATE TABLE retours (
    id          INT(11) NOT NULL AUTO_INCREMENT,
    user_id     INT(11) DEFAULT NULL,    -- NULL si envoyé sans être connecté
    nom         VARCHAR(100) DEFAULT NULL,
    email       VARCHAR(150) DEFAULT NULL,
    type        ENUM('suggestion', 'probleme', 'autre') NOT NULL DEFAULT 'suggestion',
    message     TEXT NOT NULL,
    lu          TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    CONSTRAINT retours_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
