-- Stub tables for the support forums local environment.
-- These live outside WordPress on production, but the plugin and theme
-- directory dependencies on the `/plugins` and `/themes` sub-sites read from
-- them, Ratings_Compat joins `ratings` for the review filter views, and the
-- Profiles stub records badge associations in the bpmain_wporg_groups tables.
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

-- The UNIQUE KEY is what lets WPORG_Ratings::set_rating() upsert a review.
CREATE TABLE IF NOT EXISTS `ratings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `object_type` varchar(20) NOT NULL DEFAULT '',
  `object_slug` varchar(200) NOT NULL DEFAULT '',
  `user_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `post_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `rating` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_user` (`object_type`,`object_slug`,`user_id`),
  KEY `post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `bpmain_wporg_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(200) NOT NULL DEFAULT '',
  `name` varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bpmain_wporg_groups_members` (
  `group_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `is_confirmed` tinyint(1) NOT NULL DEFAULT 1,
  `is_banned` tinyint(1) NOT NULL DEFAULT 0,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`group_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
