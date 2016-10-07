CREATE TABLE `command` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `command` VARCHAR(500) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `server` VARCHAR(50) NOT NULL,
  `user` VARCHAR(20) NOT NULL,
  `hash` VARCHAR(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `command_stats` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `command_id` SMALLINT UNSIGNED NOT NULL,
  `pid` INT UNSIGNED NOT NULL,
  `started_at` DATETIME NOT NULL,
  `finished_at` DATETIME DEFAULT NULL,
  `hash` VARCHAR(32) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`command_id`) REFERENCES `command` (`id`),
  KEY `hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
