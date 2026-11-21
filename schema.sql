-- ============================================================
-- SafeGP : structure complète de la base de données
-- Nouvelle installation :
--   1. créer une base "safegp" (utf8mb4)
--   2. importer ce fichier (phpMyAdmin → Importer, ou mysql -u root -p safegp < schema.sql)
-- Base existante : appliquer plutôt les fichiers de migrations/ dans l'ordre.
-- Pour devenir admin après inscription :
--   UPDATE users SET role = 'admin' WHERE email = 'ton@email.com';
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `premium` tinyint(1) NOT NULL DEFAULT 0,
  `formule` enum('gratuit','standard','illimite') NOT NULL DEFAULT 'gratuit',
  `abonnement_fin` date DEFAULT NULL,
  `rappel_abonnement` date DEFAULT NULL,
  `disponible` tinyint(1) NOT NULL DEFAULT 1,
  `joignable_debut` time DEFAULT NULL,
  `joignable_fin` time DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `email_nouveau` varchar(150) DEFAULT NULL,
  `email_verifie` tinyint(1) NOT NULL DEFAULT 0,
  `notif_email` tinyint(1) NOT NULL DEFAULT 1,
  `mot_de_passe` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `photo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `trajets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `depart` varchar(100) DEFAULT NULL,
  `pays_depart` char(2) DEFAULT NULL,
  `arrivee` varchar(100) DEFAULT NULL,
  `pays_arrivee` char(2) DEFAULT NULL,
  `date_depart` date DEFAULT NULL,
  `heure_depart` time DEFAULT NULL,
  `date_arrivee` date DEFAULT NULL,
  `heure_arrivee` time DEFAULT NULL,
  `date_limite_depot` date DEFAULT NULL,
  `prix_kilo` decimal(10,2) DEFAULT NULL,
  `devise` enum('MAD','EUR','USD','XOF','XAF','GBP','CAD','CHF') NOT NULL DEFAULT 'MAD',
  `kilos_total` decimal(6,1) DEFAULT NULL,
  `kilos_restants` decimal(6,1) DEFAULT NULL,
  `statut` enum('disponible','en_route','arrivé') DEFAULT 'disponible',
  `telephone` varchar(20) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `point_depot` varchar(255) DEFAULT NULL,
  `horaires_depot` varchar(100) DEFAULT NULL,
  `point_reception` varchar(255) DEFAULT NULL,
  `horaires_reception` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `pour_autrui` tinyint(1) NOT NULL DEFAULT 0,
  `voyageur_nom` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_pays` (`pays_depart`,`pays_arrivee`),
  KEY `idx_date_depart` (`date_depart`),
  CONSTRAINT `trajets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expediteur_id` int(11) NOT NULL,
  `destinataire_id` int(11) NOT NULL,
  `trajet_id` int(11) DEFAULT NULL,
  `contenu` text NOT NULL,
  `lu` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `expediteur_id` (`expediteur_id`),
  KEY `destinataire_id` (`destinataire_id`),
  KEY `trajet_id` (`trajet_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`expediteur_id`) REFERENCES `users` (`id`),
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`destinataire_id`) REFERENCES `users` (`id`),
  CONSTRAINT `messages_trajet_fk` FOREIGN KEY (`trajet_id`) REFERENCES `trajets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `contacts_whatsapp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `trajet_id` int(11) DEFAULT NULL,
  `nom` varchar(100) NOT NULL,
  `numero` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `trajet_id` (`trajet_id`),
  CONSTRAINT `contacts_trajet_fk` FOREIGN KEY (`trajet_id`) REFERENCES `trajets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contacts_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `retours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `type` enum('suggestion','probleme','autre') NOT NULL DEFAULT 'suggestion',
  `message` text NOT NULL,
  `lu` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `retours_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `jetons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('verification','reinitialisation','changement_email') NOT NULL,
  `empreinte` char(64) NOT NULL,
  `expire_le` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `empreinte` (`empreinte`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `jetons_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `abonnements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `formule` enum('standard','illimite') NOT NULL,
  `duree` enum('mois','an') NOT NULL,
  `montant` int(11) NOT NULL,
  `moyen` enum('wave','orange_money','autre') NOT NULL,
  `reference` varchar(100) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `statut` enum('en_attente','valide','refuse') NOT NULL DEFAULT 'en_attente',
  `motif_refus` varchar(255) DEFAULT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `traite_le` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `statut` (`statut`),
  CONSTRAINT `abonnements_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `connexions_echouees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `email` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ip_email` (`ip`,`email`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
