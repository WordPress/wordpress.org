-- Stub tables for theme directory local development.
-- These tables exist outside WordPress on production.

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
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_user` (`object_type`,`object_slug`,`user_id`),
  KEY `post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- `CREATE TABLE IF NOT EXISTS` skips environments started before `object_user`
-- existed, and MySQL has no `CREATE INDEX IF NOT EXISTS`, so migrate those in
-- place: collapse any duplicate rows onto the newest, then swap the indexes.
DELETE `r` FROM `ratings` AS `r`
  JOIN `ratings` AS `newer`
    ON `newer`.`object_type` = `r`.`object_type`
   AND `newer`.`object_slug` = `r`.`object_slug`
   AND `newer`.`user_id` = `r`.`user_id`
   AND `newer`.`id` > `r`.`id`;

SET @migrate := IF(
  EXISTS (
    SELECT 1 FROM `information_schema`.`STATISTICS`
     WHERE `TABLE_SCHEMA` = DATABASE()
       AND `TABLE_NAME` = 'ratings'
       AND `INDEX_NAME` = 'object_user'
  ),
  'DO 0',
  'ALTER TABLE `ratings` DROP INDEX `object_type`, DROP INDEX `user_id`, ADD UNIQUE KEY `object_user` (`object_type`,`object_slug`,`user_id`)'
);
PREPARE migrate FROM @migrate;
EXECUTE migrate;
DEALLOCATE PREPARE migrate;

-- Add `date` where an earlier start created the table without it. Without the
-- column, get_plugin_reviews() and sync_ratings() are an unknown-column error.
SET @add_date := IF(
  EXISTS (
    SELECT 1 FROM `information_schema`.`COLUMNS`
     WHERE `TABLE_SCHEMA` = DATABASE()
       AND `TABLE_NAME` = 'ratings'
       AND `COLUMN_NAME` = 'date'
  ),
  'DO 0',
  'ALTER TABLE `ratings` ADD COLUMN `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `rating`'
);
PREPARE add_date FROM @add_date;
EXECUTE add_date;
DEALLOCATE PREPARE add_date;
