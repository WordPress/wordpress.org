-- Stub tables for local development.
-- These tables exist outside WordPress on production but are needed locally.

CREATE TABLE IF NOT EXISTS `wp_svn_access` (
  `path` varchar(255) NOT NULL DEFAULT '',
  `user` varchar(200) NOT NULL DEFAULT '0',
  `access` tinytext NOT NULL,
  UNIQUE KEY `path_user` (`path`,`user`(20)),
  KEY `user` (`user`,`path`(50))
) DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `wp_update_source` (
  `plugin_id` bigint(20) unsigned NOT NULL,
  `plugin_slug` varchar(255) NOT NULL DEFAULT '',
  `available` tinyint(4) NOT NULL,
  `version` varchar(128) NOT NULL DEFAULT '0.0',
  `stable_tag` varchar(128) NOT NULL DEFAULT 'trunk',
  `plugin_name` varchar(255) NOT NULL DEFAULT '',
  `plugin_name_san` varchar(255) NOT NULL DEFAULT '',
  `plugin_author` varchar(255) NOT NULL DEFAULT '',
  `tested` varchar(128) NOT NULL DEFAULT '',
  `requires` varchar(128) NOT NULL DEFAULT '',
  `requires_php` varchar(128) NOT NULL DEFAULT '',
  `requires_plugins` text DEFAULT NULL,
  `upgrade_notice` text DEFAULT NULL,
  `assets` text DEFAULT NULL,
  `meta` text DEFAULT NULL,
  `last_updated` datetime NOT NULL,
  PRIMARY KEY (`plugin_id`),
  UNIQUE KEY `plugin_slug` (`plugin_slug`),
  KEY `plugin_name` (`plugin_name`),
  KEY `plugin_name_san` (`plugin_name_san`),
  KEY `plugin_author` (`plugin_author`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
