-- ---------------------------------------------------------------------------
-- Trongate Events Module and Calendar - database schema
--
-- Paste this into phpMyAdmin (SQL tab) after selecting the database that
-- belongs to your Trongate application, then click Go.
--
-- If your application's database does not exist yet, create it first with a
-- utf8mb4 character set, for example:
--
--   CREATE DATABASE my_app
--       DEFAULT CHARACTER SET utf8mb4
--       COLLATE utf8mb4_unicode_ci;
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_title` varchar(255) NOT NULL,
  `event_description` text DEFAULT NULL,
  `date_and_time` datetime NOT NULL,
  `important` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
