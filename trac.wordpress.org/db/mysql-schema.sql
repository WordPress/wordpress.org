--
-- Trac database schema for a MySQL backend
--
-- This Trac MySQL DB schema is modified for WordPress. It may work elsewhere
-- but note that some assumptions are made, such as WP core's username length
-- of 60 characters. Revisions are given 40 characters as that is a sha1 hash
-- and IP address fields receive 45 characters to hypothetically handle IPv6.
--
-- Why does WordPress need a modified schema?
--
-- While Trac properly specifies BIGINT when needed, all non-integer fields
-- are declared to be TEXT, when many need to be VARCHAR and some should be
-- MEDIUMTEXT or LONGTEXT. (core.trac.wordpress.org required more than 64KB
-- a few ticket descriptions.) Using TEXT even when only some words or even
-- a few letters needed to be stored is overkill and has also been reported
-- Trac for being terrible for query and general performance.
--
-- InnoDB and utf8_bin are both recommended specifically by Trac.
--
-- Some links:
-- http://trac.edgewall.org/wiki/MySqlDb - includes list of known/old issues
-- http://trac.edgewall.org/ticket/6986 - Suggested schema changes
-- http://trac.edgewall.org/ticket/4378 - Why utf8_bin is used
-- http://trac.edgewall.org/ticket/3673 - Length of primary keys
-- http://trac.edgewall.org/ticket/6823 - `sid` field length and PK issues
-- http://trac-hacks.org/wiki/SqliteToMySqlScript - deprecated
--

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `attachment`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `attachment` (
  `type` varchar(20) COLLATE utf8_bin NOT NULL,
  `id` varchar(11) COLLATE utf8_bin NOT NULL,
  `filename` varchar(400) COLLATE utf8_bin NOT NULL,
  `size` int(11) DEFAULT NULL,
  `time` bigint(20) DEFAULT NULL,
  `description` text COLLATE utf8_bin,
  `author` varchar(60) COLLATE utf8_bin,
  `ipnr` varchar(45) COLLATE utf8_bin,
  PRIMARY KEY (`type`,`id`,`filename`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_cookie`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `auth_cookie` (
  `cookie` varchar(32) COLLATE utf8_bin NOT NULL,
  `name` varchar(60) COLLATE utf8_bin NOT NULL,
  `ipnr` varchar(45) COLLATE utf8_bin NOT NULL,
  `time` int(11) DEFAULT NULL,
  PRIMARY KEY (`cookie`,`ipnr`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `cache` (
  `id` varchar(255) COLLATE utf8_bin NOT NULL,
  `generation` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `component`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `component` (
  `name` varchar(255) COLLATE utf8_bin NOT NULL,
  `owner` varchar(60) COLLATE utf8_bin,
  `description` text COLLATE utf8_bin,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `enum`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `enum` (
  `type` varchar(20) COLLATE utf8_bin NOT NULL,
  `name` varchar(50) COLLATE utf8_bin NOT NULL,
  `value` varchar(20) COLLATE utf8_bin,
  PRIMARY KEY (`type`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `milestone`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `milestone` (
  `name` varchar(255) COLLATE utf8_bin NOT NULL,
  `due` bigint(20) DEFAULT NULL,
  `completed` bigint(20) DEFAULT NULL,
  `description` text COLLATE utf8_bin,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `node_change`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `node_change` (
  `repos` int(11) NOT NULL DEFAULT '0',
  `rev` varchar(40) COLLATE utf8_bin NOT NULL,
  `path` varchar(255) COLLATE utf8_bin NOT NULL,
  `node_type` varchar(10) COLLATE utf8_bin,
  `change_type` varchar(10) COLLATE utf8_bin NOT NULL,
  `base_path` varchar(255) COLLATE utf8_bin,
  `base_rev` varchar(40) COLLATE utf8_bin,
  PRIMARY KEY (`repos`,`rev`,`path`,`change_type`),
  KEY `node_change_repos_rev_idx` (`repos`,`rev`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permission`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `permission` (
  `username` varchar(60) COLLATE utf8_bin NOT NULL,
  `action` varchar(60) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`username`,`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `report`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `report` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `author` varchar(60) COLLATE utf8_bin,
  `title` varchar(255) COLLATE utf8_bin,
  `query` text COLLATE utf8_bin,
  `description` text COLLATE utf8_bin,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `repository`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `repository` (
  `id` int(11) NOT NULL DEFAULT '0',
  `name` varchar(100) COLLATE utf8_bin NOT NULL,
  `value` text COLLATE utf8_bin,
  PRIMARY KEY (`id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `revision`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `revision` (
  `repos` int(11) NOT NULL DEFAULT '0',
  `rev` varchar(40) COLLATE utf8_bin NOT NULL,
  `time` bigint(20) DEFAULT NULL,
  `author` varchar(60) COLLATE utf8_bin,
  `message` mediumtext COLLATE utf8_bin,
  PRIMARY KEY (`repos`,`rev`),
  KEY `revision_repos_time_idx` (`repos`,`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `session`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `session` (
  `sid` varchar(80) COLLATE utf8_bin NOT NULL,
  `authenticated` int(11) NOT NULL DEFAULT '0',
  `last_visit` int(11) DEFAULT NULL,
  PRIMARY KEY (`sid`,`authenticated`),
  KEY `session_last_visit_idx` (`last_visit`),
  KEY `session_authenticated_idx` (`authenticated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `session_attribute`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `session_attribute` (
  `sid` varchar(80) COLLATE utf8_bin NOT NULL,
  `authenticated` int(11) NOT NULL DEFAULT '0',
  `name` varchar(100) COLLATE utf8_bin NOT NULL,
  `value` text COLLATE utf8_bin,
  PRIMARY KEY (`sid`,`authenticated`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `system` (
  `name` varchar(100) COLLATE utf8_bin NOT NULL,
  `value` text COLLATE utf8_bin,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ticket` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(50) COLLATE utf8_bin,
  `time` bigint(20) DEFAULT NULL,
  `changetime` bigint(20) DEFAULT NULL,
  `component` varchar(255) COLLATE utf8_bin,
  `severity` varchar(50) COLLATE utf8_bin,
  `priority` varchar(50) COLLATE utf8_bin,
  `owner` varchar(100) COLLATE utf8_bin,
  `reporter` varchar(100) COLLATE utf8_bin,
  `cc` text COLLATE utf8_bin,
  `version` varchar(50) COLLATE utf8_bin,
  `milestone` varchar(255) COLLATE utf8_bin,
  `status` varchar(50) COLLATE utf8_bin,
  `resolution` varchar(50) COLLATE utf8_bin,
  `summary` text COLLATE utf8_bin,
  `description` longtext COLLATE utf8_bin,
  `keywords` varchar(400) COLLATE utf8_bin,
  PRIMARY KEY (`id`),
  KEY `ticket_time_idx` (`time`),
  KEY `ticket_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_change`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ticket_change` (
  `ticket` int(11) NOT NULL DEFAULT '0',
  `time` bigint(20) NOT NULL DEFAULT '0',
  `author` varchar(60) COLLATE utf8_bin,
  `field` varchar(50) COLLATE utf8_bin NOT NULL,
  `oldvalue` text COLLATE utf8_bin,
  `newvalue` longtext COLLATE utf8_bin,
  PRIMARY KEY (`ticket`,`time`,`field`),
  KEY `ticket_change_ticket_idx` (`ticket`),
  KEY `ticket_change_time_idx` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_custom`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ticket_custom` (
  `ticket` int(11) NOT NULL DEFAULT '0',
  `name` varchar(255) COLLATE utf8_bin NOT NULL,
  `value` text COLLATE utf8_bin,
  PRIMARY KEY (`ticket`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `version`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `version` (
  `name` varchar(50) COLLATE utf8_bin NOT NULL,
  `time` bigint(20) DEFAULT NULL,
  `description` text COLLATE utf8_bin,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `wiki` (
  `name` varchar(255) COLLATE utf8_bin NOT NULL,
  `version` int(11) NOT NULL DEFAULT '0',
  `time` bigint(20) DEFAULT NULL,
  `author` varchar(60) COLLATE utf8_bin,
  `ipnr` varchar(45) COLLATE utf8_bin,
  `text` text COLLATE utf8_bin,
  `comment` text COLLATE utf8_bin,
  `readonly` int(11) DEFAULT NULL,
  PRIMARY KEY (`name`,`version`),
  KEY `wiki_time_idx` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
