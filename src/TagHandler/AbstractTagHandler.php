<?php namespace Atomino\Carbon\TagHandler;

use Atomino\Carbon\Database\Connection;
use function Atomino\debug;

abstract class AbstractTagHandler {
	protected Connection $connection;
	protected string $table;

	protected function rebuildWithSelect($select) {
		try {
			$this->connection->getPdo()->setAttribute(\PDO::ATTR_AUTOCOMMIT, 0);
			$this->connection->getPdo()->beginTransaction();
			$this->connection->query("TRUNCATE TABLE `$this->table`");
			$this->connection->query("INSERT INTO `$this->table` ( $select )");
			$this->connection->getPdo()->commit();
		} catch (\Throwable $exception) {
			if ($this->connection->getPdo()->inTransaction()) $this->connection->getPdo()->rollBack();
			throw $exception;
		} finally {
			if ($this->connection->getPdo()->inTransaction()) $this->connection->getPdo()->commit();
			$this->connection->getPdo()->setAttribute(\PDO::ATTR_AUTOCOMMIT, 1);
		}
	}
}
