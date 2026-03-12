-- Stub tables for local development.
-- These tables exist outside WordPress on production but are needed locally.

CREATE TABLE IF NOT EXISTS `wp_svn_access` (
  `path` varchar(255) NOT NULL DEFAULT '',
  `user` varchar(200) NOT NULL DEFAULT '0',
  `access` tinytext NOT NULL,
  UNIQUE KEY `path_user` (`path`,`user`(20)),
  KEY `user` (`user`,`path`(50))
) DEFAULT CHARSET=latin1;
