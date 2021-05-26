<?php namespace Atomino\Carbon\Field;

use Atomino\Carbon\Field\Attributes\FieldDescriptor;
use Symfony\Component\Validator\Constraints\Type;

#[FieldDescriptor('\\'.\DateTime::class, null)]
class DateField extends Field{
	public function build(mixed $value){ return is_null($value) ? null : new \DateTime($value); }
	/** @param \DateTime $value */
	public function store(mixed $value){ return is_null($value) ? null : $value->format('Y-m-d'); }
	public function import(mixed $value){ return is_null($value) ? null : \DateTime::createFromFormat(\DateTime::ISO8601, $value); }
	/** @param \DateTime $value */
	public function export(mixed $value){ return is_null($value) ? null : $value->format(\DateTime::ISO8601); }

}