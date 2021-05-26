<?php namespace Atomino\Carbon\Field;

use Atomino\Carbon\Field\Attributes\FieldDescriptor;
use JetBrains\PhpStorm\Pure;
use Symfony\Component\Validator\Constraints\Type;

#[FieldDescriptor('bool', null)]
class BoolField extends Field{
	#[Pure] public function build(mixed $value){ return is_null($value) ? null : (bool)$value; }
	#[Pure] public function store(mixed $value){ return is_null($value) ? null : (int)$value; }
	#[Pure] public function import(mixed $value){ return is_null($value) ? null : (bool)$value; }

}