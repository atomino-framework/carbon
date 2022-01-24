<?php namespace Atomino\Carbon\Database\Finder;

use Atomino\Carbon\Database\Connection;

class Comparison {

	const QUOTE = "quote";
	const QUOTE_WITHOUT_QM = "quote_without_qm";
	const RAW = "raw";
	const ESCAPE = "escape";


	/** @var string */
	protected mixed $value;
	protected ?string $operator = null;
	protected string $quote = self::QUOTE;
	protected Connection|null $connection = null;
	protected bool $escapeField = true;

	const OPERATOR_IS = 'is';
	const OPERATOR_IS_NULL = 'is_null';
	const OPERATOR_IS_NOT_NULL = 'is_not_null';
	const OPERATOR_NOT_EQUAL = 'not_equal';
	const OPERATOR_IN = 'in';
	const OPERATOR_NOT_IN = 'not_in';
	const OPERATOR_IN_STRING = 'instring';
	const OPERATOR_LIKE = 'like';
	const OPERATOR_REV_LIKE = 'revlike';
	const OPERATOR_GLOB = 'glob';
	const OPERATOR_REV_GLOB = 'revglob';
	const OPERATOR_STARTS = 'starts';
	const OPERATOR_ENDS = 'ends';
	const OPERATOR_BETWEEN = 'between';
	const OPERATOR_REGEX = 'regex';
	const OPERATOR_GT = 'gt';
	const OPERATOR_GTE = 'gte';
	const OPERATOR_LT = 'lt';
	const OPERATOR_LTE = 'lte';
	const OPERATOR_JSON_CONTAINS = 'json_contains';

	static public function field(string $field, $isIn = null) {
		$comp = new static($field);
		if (count(func_get_args()) > 1) $comp->isin($isIn);
		return $comp;
	}
	static public function exp(string $field, $isIn = null) {
		$comp = new static($field);
		$comp->fexp();
		if (count(func_get_args()) > 1) $comp->isin($isIn);
		return $comp;
	}

	public function __construct(protected string $field) { }

	public function __toString() { return $this->field; }

	public function getSql(Connection $connection): string {
		$this->connection = $connection;
		$field = $this->escapeField ? $connection->escape($this->field) : $this->field;

		return match ($this->operator) {
			static::OPERATOR_IS => "${field} = {$this->quote($this->value)}",
			static::OPERATOR_GT => "${field} > {$this->quote($this->value)}",
			static::OPERATOR_GTE => "${field} >= {$this->quote($this->value)}",
			static::OPERATOR_LT => "${field} < {$this->quote($this->value)}",
			static::OPERATOR_LTE => "${field} <= {$this->quote($this->value)}",
			static::OPERATOR_IS_NULL => "${field} IS NULL",
			static::OPERATOR_IS_NOT_NULL => "${field} IS NOT NULL",
			static::OPERATOR_NOT_EQUAL => "${field} != {$this->quote($this->value)}",
			static::OPERATOR_NOT_IN => (empty($this->value) ? "" : "${field} NOT IN (" . join(',', array_map(fn($value) => $this->quote($value), $this->value)) . ")"),
			static::OPERATOR_IN => (empty($this->value) ? "" : "${field} IN (" . join(',', array_map(fn($value) => $this->quote($value), $this->value)) . ")"),
			static::OPERATOR_LIKE => "${field} LIKE {$this->quote($this->value)}",
			static::OPERATOR_GLOB => "${field} LIKE {$this->quote(strtr($this->value, ['*'=>'%', '?'=>'_']))}",
			static::OPERATOR_REV_GLOB => "{$this->quote($this->value)} LIKE REPLACE(REPLACE(${field}, '*', '%'),'?','_')",
			static::OPERATOR_REV_LIKE => "{$this->quote($this->value)} LIKE ${field}",
			static::OPERATOR_IN_STRING => "${field} LIKE '%{$this->quote($this->value, self::QUOTE_WITHOUT_QM)}%'",
			static::OPERATOR_STARTS => "${field} LIKE '%{$this->quote($this->value, self::QUOTE_WITHOUT_QM)}''",
			static::OPERATOR_ENDS => "${field} LIKE '{$this->quote($this->value, self::QUOTE_WITHOUT_QM)}%'",
			static::OPERATOR_REGEX => "${field} REGEXP '{$this->value}'",
			static::OPERATOR_BETWEEN => "${field} BETWEEN {$this->quote($this->value[0])} AND {$this->quote($this->value[1])}",
			static::OPERATOR_JSON_CONTAINS => "JSON_CONTAINS(${field}, {$this->quote($this->value[0])}, '{$this->value[1]}')",
			default => ''
		};
	}

	#region operands

	public function json_contains($value, $path="$"): static {
		$this->operator = self::OPERATOR_JSON_CONTAINS;
		$this->value = [json_encode($value), $path];
		return $this;
	}

	public function is($value): static {
		$this->operator = self::OPERATOR_IS;
		$this->value = $value;
		return $this;
	}

	public function not($value): static {
		$this->operator = self::OPERATOR_NOT_EQUAL;
		$this->value = $value;
		return $this;
	}

	public function isin($value): static {
		return is_array($value) ? $this->in($value) : $this->is($value);
	}

	public function in(array $value): static {
		$this->operator = self::OPERATOR_IN;
		$this->value = $value;
		return $this;
	}
	public function notIn(array $value): static {
		$this->operator = self::OPERATOR_NOT_IN;
		$this->value = $value;
		return $this;
	}

	public function between($min, $max): static {
		$this->operator = self::OPERATOR_BETWEEN;
		$this->value = [$min, $max];
		return $this;
	}

	public function isNull(): static {
		$this->operator = self::OPERATOR_IS_NULL;
		return $this;
	}

	public function isNotNull(): static {
		$this->operator = self::OPERATOR_IS_NOT_NULL;
		return $this;
	}

	public function revLike($value): static {
		$this->operator = self::OPERATOR_REV_LIKE;
		$this->value = $value;
		return $this;
	}

	public function like($value): static {
		$this->operator = self::OPERATOR_LIKE;
		$this->value = $value;
		return $this;
	}
	public function revGlob($value): static {
		$this->operator = self::OPERATOR_REV_GLOB;
		$this->value = $value;
		return $this;
	}

	public function glob($value): static {
		$this->operator = self::OPERATOR_GLOB;
		$this->value = $value;
		return $this;
	}

	public function instring($value): static {
		$this->operator = self::OPERATOR_IN_STRING;
		$this->value = $value;
		return $this;
	}

	public function startsWith($value): static {
		$this->operator = self::OPERATOR_STARTS;
		$this->value = $value;
		return $this;
	}

	public function endsWith($value): static {
		$this->operator = self::OPERATOR_ENDS;
		$this->value = $value;
		return $this;
	}

	public function matches($value): static {
		$this->operator = self::OPERATOR_REGEX;
		$this->value = $value;
		return $this;
	}

	public function gt($value): static {
		$this->operator = self::OPERATOR_GT;
		$this->value = $value;
		return $this;
	}

	public function gte($value): static {
		$this->operator = self::OPERATOR_GTE;
		$this->value = $value;
		return $this;
	}

	public function lt($value): static {
		$this->operator = self::OPERATOR_LT;
		$this->value = $value;
		return $this;
	}

	public function lte($value): static {
		$this->operator = self::OPERATOR_LTE;
		$this->value = $value;
		return $this;
	}

	#endregion

	protected function quote(string $string, string|null $mode = null) {
		if (is_null($mode)) $mode = $this->quote;
		return match ($mode) {
			self::RAW => $string,
			self::QUOTE => $this->connection->quote($string),
			self::QUOTE_WITHOUT_QM => $this->connection->quote($string, false),
			self::ESCAPE => $this->connection->escape($string)
		};
	}

	public function fexp(): static {
		$this->escapeField = false;
		return $this;
	}

	public function esc(): static {
		$this->quote = self::ESCAPE;
		return $this;
	}

	public function raw(): static {
		$this->quote = self::RAW;
		return $this;
	}
}
