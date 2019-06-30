DROP TABLE IF EXISTS `sqlcli`;
CREATE TABLE `sqlcli` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Test table.';
UNLOCK TABLES;
commit;
