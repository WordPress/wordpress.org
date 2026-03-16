-- Stub tables for theme directory local development.
-- These tables exist outside WordPress on production.

CREATE TABLE IF NOT EXISTS `bb_themes_stats` (
  `slug` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `downloads` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`slug`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `ratings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `object_type` varchar(20) NOT NULL DEFAULT '',
  `object_slug` varchar(200) NOT NULL DEFAULT '',
  `user_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `post_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `rating` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `object_type` (`object_type`, `object_slug`),
  KEY `user_id` (`user_id`),
  KEY `post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
