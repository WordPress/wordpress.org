-- Stub tables for local development.
-- These tables exist outside WordPress on production but are needed locally.

-- Trac Watcher resolves a prop to an account by GitHub username as a last
-- resort. The table belongs to another part of dotorg, so nothing creates it
-- here, and every unresolved prop lookup errors without it.
CREATE TABLE IF NOT EXISTS `wporg_github_users` (
  `user_id` bigint(20) NOT NULL,
  `github_user` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`user_id`),
  KEY `github_user` (`github_user`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
