<?php namespace Atomino\Carbon\Attributes;

use Atomino\Carbon\Database\Connection;
use Atomino\Neutrons\Attr;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Modelify extends Attr{
	public function __construct(
		public string|Connection $connection,
		public string $table,
		public bool $mutable = true
	){}


}
