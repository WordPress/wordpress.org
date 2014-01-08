-- --------------------------------------------------------
-- WordPress Trac notifications schema
-- --------------------------------------------------------

CREATE TABLE `_notifications` (
  `type` varchar(20) NOT NULL,
  `value` varchar(255) NOT NULL,
  `username` varchar(60) NOT NULL,
  UNIQUE KEY `username_type_value` (`username`,`type`,`value`),
  KEY `type_value` (`type`,`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `_ticket_subs` (
  `ticket` int(11) NOT NULL,
  `username` varchar(60) NOT NULL,
  `status` tinyint(4) NOT NULL,
  UNIQUE KEY `username_ticket_status` (`username`,`ticket`,`status`),
  KEY `ticket_status` (`ticket`,`status`),
  KEY `username_status` (`username`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
