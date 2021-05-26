<?php namespace Atomino\Carbon\Field;
use Atomino\Carbon\Field\Attributes\FieldDescriptor;

#[FieldDescriptor('\\'.\DateTime::class, null)]
class DateTimeField extends Field{
	public function build(mixed $value){ return is_null($value) ? null : new \DateTime($value); }
	/** @param \DateTime $value */
	public function store(mixed $value){ return is_null($value) ? null : $value->format('Y-m-d H:i:s'); }
	public function import(mixed $value){ return is_null($value) ? null : \DateTime::createFromFormat(\DateTime::ISO8601, $value); }
	/** @param \DateTime $value */
	public function export(mixed $value){ return is_null($value) ? null : $value->format(\DateTime::ISO8601); }
}