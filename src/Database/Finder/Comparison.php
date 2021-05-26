<?php namespace Atomino\Carbon\Database\Finder;

use Atomino\Carbon\Database\Connection;

class Comparison{

	/** @var string */
	protected mixed $value;
	protected ?string $operator = null;
	protected bool $quote = true;

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

	public function __construct(protected string $field){}

	public function __toString(){ return $this->field; }

	public function getSql(Connection $connection): string{
		$field = $connection->escape($this->field);
		return match ( $this->operator ) {
			static::OPERATOR_IS => "${field} = {$connection->quote($this->value)}",
			static::OPERATOR_GT => "${field} > {$connection->quote($this->value)}",
			static::OPERATOR_GTE => "${field} >= {$connection->quote($this->value)}",
			static::OPERATOR_LT => "${field} < {$connection->quote($this->value)}",
			static::OPERATOR_LTE => "${field} <= {$connection->quote($this->value)}",
			static::OPERATOR_IS_NULL => "${field} IS NULL",
			static::OPERATOR_IS_NOT_NULL => "${field} IS NOT NULL",
			static::OPERATOR_NOT_EQUAL => "${field} != {$connection->quote($this->value)}",
			static::OPERATOR_NOT_IN => ( empty($this->value) ? "" : "${field} NOT IN (" . join(',', array_map(fn($value)=>$connection->quote($value), $this->value)) . ")" ),
			static::OPERATOR_IN => ( empty($this->value) ? "" : "${field} IN (" . join(',', array_map(fn($value)=>$connection->quote($value), $this->value)) . ")" ),
			static::OPERATOR_LIKE => "${field} LIKE {$connection->quote($this->value)}",
			static::OPERATOR_GLOB => "${field} LIKE {$connection->quote(strtr($this->value, ['*'=>'%', '?'=>'_']))}",
			static::OPERATOR_REV_GLOB => "{$connection->quote($this->value)} LIKE REPLACE(REPLACE(${field}, '*', '%'),'?','_')",
			static::OPERATOR_REV_LIKE => "{$connection->quote($this->value)} LIKE ${field}",
			static::OPERATOR_IN_STRING => "${field} LIKE '%{$connection->quote($this->value, false)}%'",
			static::OPERATOR_STARTS => "${field} LIKE '%{$connection->quote($this->value, false)}''",
			static::OPERATOR_ENDS => "${field} LIKE '{$connection->quote($this->value, false)}%'",
			static::OPERATOR_REGEX => "${field} REGEXP '{$this->value}'",
			static::OPERATOR_BETWEEN => "${field} BETWEEN {$connection->quote($this->value[0])} AND {$connection->quote($this->value[1])}",
			default => ''
		};
	}

	public function is($value): static{
		$this->operator = self::OPERATOR_IS;
		$this->value = $value;
		return $this;
	}

	public function not($value): static{
		$this->operator = self::OPERATOR_NOT_EQUAL;
		$this->value = $value;
		return $this;
	}

	public function isin($value): static{
		return is_array($value) ? $this->in($value) : $this->is($value);
	}

	public function in(array $value): static{
		$this->operator = self::OPERATOR_IN;
		$this->value = $value;
		return $this;
	}
	public function notIn(array $value): static{
		$this->operator = self::OPERATOR_NOT_IN;
		$this->value = $value;
		return $this;
	}

	public function between($min, $max): static{
		$this->operator = self::OPERATOR_BETWEEN;
		$this->value = [$min, $max];
		return $this;
	}

	public function isNull(): static{
		$this->operator = self::OPERATOR_IS_NULL;
		return $this;
	}

	public function isNotNull(): static{
		$this->operator = self::OPERATOR_IS_NOT_NULL;
		return $this;
	}

	public function revLike($value): static{
		$this->operator = self::OPERATOR_REV_LIKE;
		$this->value = $value;
		return $this;
	}

	public function like($value): static{
		$this->operator = self::OPERATOR_LIKE;
		$this->value = $value;
		return $this;
	}
	public function revGlob($value): static{
		$this->operator = self::OPERATOR_REV_GLOB;
		$this->value = $value;
		return $this;
	}

	public function glob($value): static{
		$this->operator = self::OPERATOR_GLOB;
		$this->value = $value;
		return $this;
	}

	public function instring($value): static{
		$this->operator = self::OPERATOR_IN_STRING;
		$this->value = $value;
		return $this;
	}

	public function startsWith($value): static{
		$this->operator = self::OPERATOR_STARTS;
		$this->value = $value;
		return $this;
	}

	public function endsWith($value): static{
		$this->operator = self::OPERATOR_ENDS;
		$this->value = $value;
		return $this;
	}

	public function matches($value): static{
		$this->operator = self::OPERATOR_REGEX;
		$this->value = $value;
		return $this;
	}

	public function gt($value): static{
		$this->operator = self::OPERATOR_GT;
		$this->value = $value;
		return $this;
	}

	public function gte($value): static{
		$this->operator = self::OPERATOR_GTE;
		$this->value = $value;
		return $this;
	}

	public function lt($value): static{
		$this->operator = self::OPERATOR_LT;
		$this->value = $value;
		return $this;
	}

	public function lte($value): static{
		$this->operator = self::OPERATOR_LTE;
		$this->value = $value;
		return $this;
	}

	public function raw(): static{
		$this->quote = false;
		return $this;
	}
}