<?php namespace Atomino\Carbon\Database\Finder;

use Atomino\Carbon\Database\Connection;

class Filter {

	const AND = 1;
	const OR = 2;
	const NOT = 4;

	protected array $where = [];

	protected function __construct() { }

	static public function where(Comparison|Filter|string|false|null $sql = false, ...$sqlParams): static {
		$filter = new static();
		return $filter->addWhere(static::AND, $sql, $sqlParams);
	}

	public function and(Comparison|Filter|string|bool|null $sql, ...$args): static { return $this->addWhere(static:: AND, $sql, $args); }
	public function or(Comparison|Filter|string|bool|null $sql, ...$args): static { return $this->addWhere(static:: OR, $sql, $args); }
	public function andNot(Comparison|Filter|string|bool|null $sql, ...$args): static { return $this->addWhere(static:: AND + static::NOT, $sql, $args); }
	public function orNot(Comparison|Filter|string|bool|null $sql, ...$args): static { return $this->addWhere(static:: OR + static::NOT, $sql, $args); }

	protected function addWhere(int $type, Comparison|Filter|string|bool|null $sql, array $args): static {
		if ($sql !== false && $sql !== null) $this->where[] = ['type' => $type, 'sql' => $sql, 'args' => $args];
		return $this;
	}


	public function getSql(Connection $connection): ?string {
		if (!count($this->where)) return null;
		$sql = '';
		foreach ($this->where as $segment) {
			if ($segment['sql'] instanceof Comparison) {
				$where = $segment['sql']->getSql($connection);
			} elseif ($segment['sql'] instanceof Filter) {
				$where = $segment['sql']->getSql($connection);
			} elseif (is_array($segment['sql'])) {
				$where = $this->getSqlFromArray($segment['sql'], $connection);
			} else {
				$where = $connection->getSmart()->applySQLArguments($segment['sql'], $segment['args']);
			}

			if (trim($where)) {
				if (strlen($sql) > 0) {
					$sql .= $segment['type'] & self:: AND ? ' AND ' : ' OR ';
				}
				$sql .= ($segment['type'] & self::NOT ? ' NOT ' : '') . "(" . $where . ")";
			}
		}
		return $sql;
	}

	protected function getSqlFromArray(array $filter, Connection $connection): ?string {
		if (!$filter) return null;
		$sql = [];
		foreach ($filter as $key => $value) {
			$sql[] = $connection->escape($key) . (
				is_array($value) ?
					' IN (' . array_map(fn($arg) => $connection->quote($arg), $value) . ')' :
					' = ' . $connection->quote($value)
				);
		}
		return implode(' AND ', $sql);
	}


}
