<?php namespace Atomino\Carbon\Field;

use Atomino\Carbon\Field\Attributes\FieldDescriptor;
use JetBrains\PhpStorm\Pure;
use Symfony\Component\Validator\Constraints\Length;

#[FieldDescriptor('string', null)]
class StringField extends Field{
	#[Pure] public function build(mixed $value){ return is_null($value) ? null : strval($value); }
	#[Pure] public function import(mixed $value){ return is_null($value) ? null : strval($value); }
	/** @param \Atomino\Carbon\Database\Descriptor\Field\StringField $field */
	static function getValidators(\Atomino\Carbon\Database\Descriptor\Field\Field $field):array{
		$validators = parent::getValidators( $field);
		$validators[] = [Length::class, ['max' => $field->getMaxLength()]];
		return $validators;
	}
}