-- ============================================================
-- Migration 003 : pays des trajets, nouvelles devises,
-- messages liés à un trajet
--
-- À exécuter UNE SEULE FOIS, après 002_comptes_emails_devises.sql
-- (phpMyAdmin → base safegp → onglet SQL → coller → Exécuter).
-- ============================================================

SET NAMES utf8mb4;

-- Pays de départ et d'arrivée (code ISO à 2 lettres : MA, SN, FR...)
ALTER TABLE trajets
    ADD COLUMN pays_depart  CHAR(2) DEFAULT NULL AFTER depart,
    ADD COLUMN pays_arrivee CHAR(2) DEFAULT NULL AFTER arrivee,
    ADD KEY idx_pays (pays_depart, pays_arrivee),
    ADD KEY idx_date_depart (date_depart);

-- Trajets déjà publiés : on devine le pays à partir des villes connues
UPDATE trajets SET pays_depart = 'MA' WHERE pays_depart IS NULL AND LOWER(depart) IN
    ('casa', 'casablanca', 'rabat', 'fes', 'fès', 'fez', 'marrakech', 'tanger', 'agadir', 'meknes', 'meknès', 'oujda', 'kenitra', 'kénitra', 'tetouan', 'tétouan', 'ifrane', 'el jadida', 'safi', 'nador', 'laayoune', 'laâyoune', 'dakhla');
UPDATE trajets SET pays_arrivee = 'MA' WHERE pays_arrivee IS NULL AND LOWER(arrivee) IN
    ('casa', 'casablanca', 'rabat', 'fes', 'fès', 'fez', 'marrakech', 'tanger', 'agadir', 'meknes', 'meknès', 'oujda', 'kenitra', 'kénitra', 'tetouan', 'tétouan', 'ifrane', 'el jadida', 'safi', 'nador', 'laayoune', 'laâyoune', 'dakhla');
UPDATE trajets SET pays_depart = 'SN' WHERE pays_depart IS NULL AND LOWER(depart) IN
    ('dakar', 'thies', 'thiès', 'kaolack', 'saint-louis', 'ziguinchor', 'touba', 'mbour', 'kedougou', 'kédougou', 'tambacounda', 'diourbel', 'louga', 'kolda', 'fatick', 'rufisque');
UPDATE trajets SET pays_arrivee = 'SN' WHERE pays_arrivee IS NULL AND LOWER(arrivee) IN
    ('dakar', 'thies', 'thiès', 'kaolack', 'saint-louis', 'ziguinchor', 'touba', 'mbour', 'kedougou', 'kédougou', 'tambacounda', 'diourbel', 'louga', 'kolda', 'fatick', 'rufisque');
UPDATE trajets SET pays_depart = 'FR' WHERE pays_depart IS NULL AND LOWER(depart) IN ('paris', 'marseille', 'lyon', 'toulouse', 'bordeaux', 'lille', 'nice', 'nantes');
UPDATE trajets SET pays_arrivee = 'FR' WHERE pays_arrivee IS NULL AND LOWER(arrivee) IN ('paris', 'marseille', 'lyon', 'toulouse', 'bordeaux', 'lille', 'nice', 'nantes');
UPDATE trajets SET pays_depart = 'US' WHERE pays_depart IS NULL AND LOWER(depart) IN ('washington', 'new york', 'new-york');
UPDATE trajets SET pays_arrivee = 'US' WHERE pays_arrivee IS NULL AND LOWER(arrivee) IN ('washington', 'new york', 'new-york');

-- Devises supplémentaires
ALTER TABLE trajets
    MODIFY COLUMN devise ENUM('MAD', 'EUR', 'USD', 'XOF', 'XAF', 'GBP', 'CAD', 'CHF') NOT NULL DEFAULT 'MAD';

-- Message envoyé à propos d'un trajet précis (« Contacter pour ce trajet »)
ALTER TABLE messages
    ADD COLUMN trajet_id INT(11) DEFAULT NULL AFTER destinataire_id,
    ADD KEY trajet_id (trajet_id),
    ADD CONSTRAINT messages_trajet_fk FOREIGN KEY (trajet_id) REFERENCES trajets (id) ON DELETE SET NULL;
