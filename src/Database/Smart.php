<?php namespace Atomino\Carbon\Database;

use Atomino\Carbon\Database\Finder\Filter;
use JetBrains\PhpStorm\Pure;

class Smart{

	private \PDO $pdo;
	#[Pure] public function __construct(private Connection $connection){
		$this->pdo = $this->connection->getPdo();
	}
	
	private function query(string $sql, array $args = []): bool|\PDOStatement{return $this->connection->query($this->applySQLArguments($sql, $args));}
	private function quote($subject, $qm=true):string{return $this->connection->quote($subject, $qm);}
	#[Pure] private function escape($subject):string{return $this->connection->escape($subject);}

	public function getFoundRows(){ return $this->getValue('SELECT FOUND_ROWS()'); }

	public function getValue(string $sql, ...$args){
		$row = $this->getRow($sql, ...$args);
		return $row ? reset($row) : null;
	}
	public function getRow(string $sql, ...$args){ return $this->getFirstRow($sql . ( stripos($sql, ' LIMIT ') === false ? ' LIMIT 1' : '' ), ...$args); }
	protected function getFirstRow(string $sql, ...$args){ return $this->query($sql, $args)->fetch(\PDO::FETCH_ASSOC); }
	public function getRowById(string $table, int $id){ return $this->getRow("SELECT * FROM " . $this->escape($table) . " WHERE id=" . $this->quote($id)); }

	public function getRowsById(string $table, array $ids): array{
		return $this->getRows(
			'SELECT * FROM ' . $this->escape($table) .
			' WHERE  id IN (' . join(',', array_map(fn($id)=>$this->quote($id), $ids)) . ')');
	}
	public function getValues(string $sql, ...$args): array{ return $this->query($sql, ...$args)->fetchAll(\PDO::FETCH_COLUMN, 0); }
	public function getRows(string $sql, ...$args): array{ return $this->query($sql, ...$args)->fetchAll(\PDO::FETCH_ASSOC); }
	public function getValuesWithKey(string $sql, ...$args): array{ return $this->query($sql, ...$args)->fetchAll(\PDO::FETCH_KEY_PAIR); }
	public function getRowsWithKey(string $sql, ...$args): array{ return $this->query($sql, ...$args)->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC); }

	#region insert / update / delete
	public function insert(string $table, array $data, bool $ignore = false): int{
		foreach ($data as $key => $value){
			if ($key[0] === '!'){
				$key = substr($key, 1);
			}else{
				$value = $this->quote($value);
			}
			$data[$key] = [$this->escape($key), $value];
		}
		$this->query(($ignore === true ? 'INSERT IGNORE ' : 'INSERT ') .
							'INTO ' . $this->escape($table) . ' ' .
							'(' . implode(', ', array_column($data, 0)) . ') ' .
							'VALUE (' . implode(', ', array_column($data, 1)) . ')'
		);
		return $this->pdo->lastInsertId();
	}

	public function update(string $table, Filter $filter, array $data): int{
		foreach ($data as $key => $value){
			if ($key[0] === '!'){
				$key = substr($key, 1);
			}else{
				$value = $this->quote($value);
			}
			$data[$key] = $this->escape($key) . '=' . $value;
		}
		return $this->query("UPDATE " . $this->escape($table) . " SET " . implode(", ", $data) . ' WHERE ' . $filter->getSql($this->connection))->rowCount();
	}
	public function updateById(string $table, int $id, array $data): int{ return $this->update($table, Filter::where('id=$1', $id), $data); }

	public function delete(string $table, Filter $filter): int{ return $this->query("DELETE FROM " . $this->escape($table) . " WHERE " . $filter->getSql($this->connection))->rowCount(); }
	public function deleteById(string $table, int $id): int{ return $this->delete($table, Filter::where('id=$1', $id)); }
	#endregion

	#region transaction
	public function beginTransaction(): bool{ return $this->pdo->beginTransaction(); }
	public function commit(): bool{ return $this->pdo->commit(); }
	public function rollBack(): bool{ return $this->pdo->rollBack(); }
	public function inTransaction(): bool{ return $this->pdo->inTransaction(); }
	#endregion

	public function applySQLArguments(string $sql, array $args): string{
		if (count($args)){
			foreach ($args as $key => $arg){
				$value = is_array($arg) ? join(',', array_map(fn($arg) => $this->quote($arg), $arg)) : $this->quote($arg);
				$sql = str_replace('$' . ( $key + 1 ), $value, $sql);
				if (!is_array($arg)) $sql = str_replace('@' . ( $key + 1 ), $this->escape($arg), $sql);
			}
		}
		return $sql;
	}
}