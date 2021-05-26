<?php namespace Atomino\Carbon\Attributes;

use Atomino\Neutrons\Attr;
use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute( Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE )]
class Validator extends Attr{
	public Constraint $validator;
	public function __construct(public string $field, string $validatorClass, ...$arguments){
		$this->validator = new $validatorClass(...$arguments);
	}
}
