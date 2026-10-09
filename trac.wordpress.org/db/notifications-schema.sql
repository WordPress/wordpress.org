-- --------------------------------------------------------
-- WordPress Trac notifications schema
-- --------------------------------------------------------

CREATE TABLE `_notifications` (
  `type` varchar(20) COLLATE utf8mb4_bin NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `username` varchar(60) COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`username`,`type`,`value`),
  KEY `type_value_username` (`type`,`value`,`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE `_ticket_subs` (
  `ticket` int(10) unsigned NOT NULL,
  `username` varchar(60) COLLATE utf8mb4_bin NOT NULL,
  `status` tinyint(4) NOT NULL,
  PRIMARY KEY (`ticket`,`username`),
  KEY `ticket_status` (`ticket`,`status`),
  KEY `username_status` (`username`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
