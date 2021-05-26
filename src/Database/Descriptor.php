<?php namespace Atomino\Carbon\Database;

use Atomino\Carbon\Database\Descriptor\Table;
use JetBrains\PhpStorm\Pure;

class Descriptor{

	private string $database;
	/** @var Table[] $tables */
	private array $tables = [];

	public function __construct(private Connection $connection){
		$this->database = $this->connection->query("select database()")->fetchColumn();
		$tables = $this->connection->query("SHOW FULL TABLES")->fetchAll(\PDO::FETCH_KEY_PAIR);
		foreach ($tables as $table => $type){
			$this->tables[$table] = new Table($this->connection, $table, $type, $this->database);
		}
	}

	public function getDatabase(): string{ return $this->database; }
	/** @return Table[] */
	public function getTables(): array{ return $this->tables; }
	#[Pure] public function getTable($table): ?Table{ return array_key_exists($table, $this->tables) ? $this->tables[$table] : null; }
}