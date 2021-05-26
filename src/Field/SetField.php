<?php namespace Atomino\Carbon\Field;

use Atomino\Carbon\Field\Attributes\FieldDescriptor;
use JetBrains\PhpStorm\Pure;
use Symfony\Component\Validator\Constraints\Choice;

#[FieldDescriptor('array', [], true)]
class SetField extends Field{
	#[Pure] public function build(mixed $value){ return is_null($value) || $value === '' ? [] : explode(',', $value); }
	#[Pure] public function store(mixed $value){ return join(',', $value); }
	#[Pure] public function import(mixed $value){ return is_null($value) ? [] : $value; }

	/** @param \Atomino\Carbon\Database\Descriptor\Field\EnumField $field */
	static function getValidators(\Atomino\Carbon\Database\Descriptor\Field\Field $field):array{
		$validators = parent::getValidators($field);
		$validators[] = [Choice::class, ['multiple' => true, 'choices' => $field->getOptions()]];
		return $validators;
	}
}