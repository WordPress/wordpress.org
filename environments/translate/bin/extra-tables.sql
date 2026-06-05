-- Extra stats tables maintained by wporg-gp-custom-stats. Production assumes
-- they already exist; the plugin doesn't create them, so we do it here.

CREATE TABLE IF NOT EXISTS `wp_gp_user_translations_count` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `locale` varchar(255) NOT NULL DEFAULT '',
  `locale_slug` varchar(255) NOT NULL DEFAULT '',
  `suggested` int(10) unsigned NOT NULL DEFAULT '0',
  `accepted` int(10) unsigned NOT NULL DEFAULT '0',
  `date_added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`,`locale`,`locale_slug`),
  KEY `locale` (`locale`,`locale_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wp_gp_user_projects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `project_id` int(11) unsigned NOT NULL,
  `locale` varchar(255) NOT NULL,
  `locale_slug` varchar(255) NOT NULL DEFAULT 'default',
  `last_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_project_locale` (`user_id`,`project_id`,`locale`,`locale_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wp_gp_project_translation_status` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `locale` varchar(10) NOT NULL,
  `locale_slug` varchar(255) NOT NULL,
  `all` int(10) unsigned NOT NULL DEFAULT '0',
  `current` int(10) unsigned NOT NULL DEFAULT '0',
  `waiting` int(10) unsigned NOT NULL DEFAULT '0',
  `fuzzy` int(10) unsigned NOT NULL DEFAULT '0',
  `warnings` int(10) unsigned NOT NULL DEFAULT '0',
  `untranslated` int(10) unsigned NOT NULL DEFAULT '0',
  `has_pending` tinyint(1) NOT NULL DEFAULT 0,
  `date_added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_locale` (`project_id`,`locale`,`locale_slug`),
  KEY `locale` (`locale`,`locale_slug`,`has_pending`),
  KEY `date_added` (`date_added`),
  KEY `date_modified` (`date_modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
