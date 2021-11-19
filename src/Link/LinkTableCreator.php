<?php

namespace Atomino\Carbon\Link;

use Application\Database\DefaultConnection;

class LinkTableCreator {

	public function __construct(private DefaultConnection $connection) { }

	public function create($table, $left, $right) {
		$this->connection->query("
		CREATE TABLE `$table` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `left` int unsigned NOT NULL,
  `right` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique` (`left`,`right`),
  KEY `left` (`left`),
  KEY `right` (`right`),
  CONSTRAINT `{$table}_left` FOREIGN KEY (`left`) REFERENCES `$left` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `{$table}_right` FOREIGN KEY (`right`) REFERENCES `$right` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
		");
	}
}