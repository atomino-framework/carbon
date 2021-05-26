<?php namespace Atomino\Carbon\Attributes;

use Atomino\Neutrons\Attr;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS|Attribute::IS_REPEATABLE)]
class Immutable extends Attr{
	public function __construct(
		public string $field,
		public bool $allowInsert = false
	){}
}
