<?php

namespace Atomino\Carbon\Link;


/**
 * @property-read string $table
 * @property-read string $field
 */
class Side {
	public function __construct(public bool $side, public string $name, public string $class, public int|null $limit) { }
	public function __get($name) {
		return match ($name) {
			"table" => $this->class::model()->getTable(),
			"field" => $this->side === \Atomino\Carbon\Attributes\Link::LEFT ? "left" : "right"
		};
	}
}