-- Stub tables for the support forums local environment.
-- These tables exist outside WordPress on production, but the plugin and theme
-- directory dependencies on the `/plugins` and `/themes` sub-sites read from
-- them, and Ratings_Compat joins `ratings` for the review filter views.
--
-- The `wp_` prefixed tables match PLUGINS_TABLE_PREFIX in .wp-env.json.

CREATE TABLE IF NOT EXISTS `wp_helpscout_meta` (
  `helpscout_id` bigint(20) unsigned NOT NULL,
  `meta_key` varchar(255) NOT NULL DEFAULT '',
  `meta_value` varchar(255) NOT NULL DEFAULT '',
  KEY `helpscout_id` (`helpscout_id`),
  KEY `meta_key_value` (`meta_key`(191),`meta_value`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wp_svn_access` (
  `path` varchar(255) NOT NULL DEFAULT '',
  `user` varchar(200) NOT NULL DEFAULT '0',
  `access` tinytext NOT NULL,
  UNIQUE KEY `path_user` (`path`,`user`(20)),
  KEY `user` (`user`,`path`(50))
) DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `wp_stats` (
  `plugin_slug` varchar(255) NOT NULL DEFAULT '',
  `date` date NOT NULL,
  `downloads` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`plugin_slug`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `bb_themes_stats` (
  `slug` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `downloads` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`slug`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `ratings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `object_type` varchar(20) NOT NULL DEFAULT '',
  `object_slug` varchar(200) NOT NULL DEFAULT '',
  `user_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `post_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `rating` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `object_type` (`object_type`,`object_slug`),
  KEY `user_id` (`user_id`),
  KEY `post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
