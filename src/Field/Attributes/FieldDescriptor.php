<?php namespace Atomino\Carbon\Field\Attributes;

use Atomino\Neutrons\Attr;

#[\Attribute(\Attribute::TARGET_CLASS)]
class FieldDescriptor extends Attr{
	public function __construct(public string $type, public mixed $default, public bool $hasOptions = false){ }
}