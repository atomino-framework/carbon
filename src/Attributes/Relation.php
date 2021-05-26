<?php namespace Atomino\Carbon\Attributes;

use Atomino\Carbon\Entity;
use Atomino\Neutrons\Attr;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS|Attribute::IS_REPEATABLE)]
abstract class Relation extends Attr{
	public function __construct(
		public string $target,
		public string $entity,
		public string $field
	){
	}
	abstract public function fetch(Entity $item);
}
