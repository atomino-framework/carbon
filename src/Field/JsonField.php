<?php namespace Atomino\Carbon\Field;

use Atomino\Carbon\Field\Attributes\FieldDescriptor;

#[FieldDescriptor('array', [])]
class JsonField extends Field{
	public function build(mixed $value){ return is_null($value) ? [] : json_decode($value, true) ; }
	public function store(mixed $value){ return json_encode($value); }
}