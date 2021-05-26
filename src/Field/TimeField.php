<?php namespace Atomino\Carbon\Field;

use Atomino\Carbon\Field\Attributes\FieldDescriptor;

#[FieldDescriptor('\\'.\DateTime::class, null)]
class TimeField extends Field{
	public function build(mixed $value){ return is_null($value) ? null : new \DateTime($value); }
	/** @param \DateTime $value */
	public function store(mixed $value){ return is_null($value) ? null : $value->format('H:i:s'); }
	public function import(mixed $value){ return is_null($value) ? null : new \DateTime($value); }
	/** @param \DateTime $value */
	public function export(mixed $value){ return is_null($value) ? null : $value->format('H:i:s'); }
}