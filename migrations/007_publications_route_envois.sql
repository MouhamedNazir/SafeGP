-- ============================================================
-- Migration 007 : journal des publications (limite mensuelle non
-- contournable en supprimant un trajet) et copie de la route dans
-- chaque envoi (le suivi garde son itinéraire même si le trajet
-- est supprimé)
--
-- À exécuter UNE SEULE FOIS, après 006_envois_preuves_annulation.sql
-- (phpMyAdmin → base safegp → onglet SQL → coller → Exécuter).
-- ============================================================

SET NAMES utf8mb4;

-- Une ligne par trajet publié ; jamais supprimée quand le trajet l'est
CREATE TABLE publications (
    id         INT(11) NOT NULL AUTO_INCREMENT,
    user_id    INT(11) NOT NULL,
    trajet_id  INT(11) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_date (user_id, created_at),
    CONSTRAINT publications_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- On reprend les trajets déjà publiés (ceux qui ont une date de publication)
INSERT INTO publications (user_id, trajet_id, created_at)
SELECT user_id, id, created_at FROM trajets WHERE user_id IS NOT NULL AND created_at IS NOT NULL;

-- Route copiée dans l'envoi au moment de la réservation
ALTER TABLE envois
    ADD COLUMN depart       VARCHAR(100) DEFAULT NULL AFTER trajet_id,
    ADD COLUMN pays_depart  CHAR(2) DEFAULT NULL AFTER depart,
    ADD COLUMN arrivee      VARCHAR(100) DEFAULT NULL AFTER pays_depart,
    ADD COLUMN pays_arrivee CHAR(2) DEFAULT NULL AFTER arrivee,
    ADD COLUMN date_depart  DATE DEFAULT NULL AFTER pays_arrivee,
    ADD COLUMN date_arrivee DATE DEFAULT NULL AFTER date_depart;

UPDATE envois e JOIN trajets t ON t.id = e.trajet_id
SET e.depart = t.depart, e.pays_depart = t.pays_depart, e.arrivee = t.arrivee,
    e.pays_arrivee = t.pays_arrivee, e.date_depart = t.date_depart, e.date_arrivee = t.date_arrivee;
