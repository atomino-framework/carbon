<?php namespace Atomino\Carbon\Database;

use Atomino\Carbon\Database\Finder\Filter;
use Symfony\Component\Cache\CacheItem;

class Finder {

	private int $cacheInterval = 0;
	private array $select = [];
	/** @var Join[] */
	private array $joins = [];
	private ?Filter $filter = null;
	private string $from;
	private array $order = [];
	private array $groupBy = [];
	private ?Filter $having = null;

	public function __construct(private Connection $connection) { }
	public function cache(int $sec): static {
		$this->cacheInterval = $sec;
		return $this;
	}
	public function fields(string ...$fields): static {
		$fields = array_map(function ($field): string { return $this->connection->escape($field); }, $fields);
		return $this->select(join(',', $fields));
	}
	public function select(string|null $select = null): static {
		if (is_null($select)) $this->select = [];
		else $this->select[] = $select;
		return $this;
	}

	public function groupBy(string ...$fields): static {
		$this->groupBy = array_map(function ($field): string { return $this->connection->escape($field); }, $fields);
		return $this;
	}

	public function table(string $from): static {
		return $this->from($this->connection->escape($from));
	}

	public function from(string $from): static {
		$this->from = $from;
		return $this;
	}

	public function join(string $table, string|null $alias, Filter|null $on, string $mod = "INNER"): static {
		$this->joins[] = new Join($table, $alias, $on, $mod);
		return $this;
	}

	public function having(null|Filter $filter): static {
		if (is_null($this->having)) {
			$this->having = $filter;
		} else {
			$this->having->and($filter);
		}
		return $this;
	}
	public function where(null|Filter $filter): static {
		if (is_null($this->filter)) {
			$this->filter = $filter;
		} else {
			$this->filter->and($filter);
		}
		return $this;
	}

	public function asc(?string $field): static {
		$this->order[] = $this->connection->escape($field) . ' ASC';
		return $this;
	}
	public function desc(?string $field): static {
		$this->order[] = $this->connection->escape($field) . ' DESC';
		return $this;
	}

	public function random():static{$this->order = ["rand()"]; return $this;}

	public function order(array ...$orders):static {
		foreach ($orders as $order) {
			$dir = (strtolower($order[1]) === 'desc' || $order[1] === -1) ? 'DESC' : 'ASC';
			$this->order[] = $this->connection->escape($order[0]) . ' ' . $dir;
		}
		return $this;
	}

	public function value(): mixed { return is_null($record = $this->record()) ? null : reset($record); }
	public function values(string|null $field = null): mixed {
		$records = $this->records();
		return array_map(function($record) use ($field){
			return is_null($field) ? reset($record) : $record[$field];
		}, $records);
	}
	public function integer(): int|null { return is_null($record = $this->record()) ? null : intval(reset($record)); }

	public function record(): ?array {
		$items = $this->records(1);
		return array_pop($items);
	}



	public function records(?int $limit = null, ?int $offset = null, int|null|bool &$count = false): array {
		$sql = $this->buildSQL($limit, $offset, $count);

		if ($this->connection->getCache() && $this->cacheInterval) {
			$cached = $this->connection->getCache()->get(md5($sql), function (CacheItem $item) use ($sql, $count) {
				$records = $this->connection->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				if ($count !== false) $count = $this->connection->query('SELECT FOUND_ROWS()')->fetch(\PDO::FETCH_COLUMN);
				$item->expiresAfter($this->cacheInterval);
				return ['records' => $records, 'count' => $count];
			});
			$records = $cached['records'];
			$count = $cached['count'];
		} else {
			$records = $this->connection->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
			if ($count !== false) $count = $this->connection->query('SELECT FOUND_ROWS()')->fetch(\PDO::FETCH_COLUMN);
		}

		return $records;
	}


	protected function buildSQL(?int $limit, ?int $offset, null|bool|int $count): string {
		return
			'SELECT ' .
			($count !== false ? 'SQL_CALC_FOUND_ROWS ' : '') .
			(count($this->select) ? join(',', $this->select) : '*') . ' ' .
			' FROM ' . $this->from . ' ' .
			(join(" ", array_map(fn(Join $join) => $join->buildSql($this->connection), $this->joins))) . ' ' .
			($this->filter != null && !is_null($filter = $this->filter->getSql($this->connection)) ? ' WHERE ' . $filter . ' ' : '') .
			(count($this->groupBy) ? 'GROUP BY ' . join(',', $this->groupBy) . ' ' : '') .
			($this->having != null && !is_null($having = $this->having->getSql($this->connection)) ? ' HAVING ' . $having . ' ' : '') .
			(count($this->order) ? ' ORDER BY ' . join(', ', $this->order) : '') .
			($limit ? ' LIMIT ' . $limit : '') .
			($offset ? ' OFFSET ' . $offset : '');
	}

}

class Join {
	public function __construct(public string $table, public string|null $alias, public Filter|null $on, public string $mod = "INNER") { }
	public function buildSql(Connection $connection): string {
		return " " . $this->mod . " JOIN "
		. $connection->escape($this->table)
		. ($this->alias ? " as " . $connection->escape($this->alias) : " ")
		. ($this->on ? " ON (" . $this->on->getSql($connection) . ")" : " ");
	}
}
