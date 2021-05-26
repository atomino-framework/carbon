<?php namespace Atomino\Carbon\Field;

use JetBrains\PhpStorm\Pure;
use Symfony\Component\Validator\Constraints\NotNull;

abstract class Field{

	private string|null $getter = null;
	private string|null $setter = null;

	public function getDefault(): mixed{ return null; }

	#[Pure] public function __construct(
		private string $name,
		private null|bool $get,
		private null|bool $set,
		private bool $insert,
		private bool $update,
		private mixed $options = null,
	){
		if($get === true) $this->getter = 'get'.ucfirst($name);
		if($set === true) $this->setter = 'set'.ucfirst($name);
	}

	public function getName(): string{ return $this->name; }
	public function getGetter(): string|null{ return $this->getter; }
	public function getSetter(): string|null{ return $this->setter; }
	public function isInsert(): bool{ return $this->insert; }
	public function isUpdate(): bool{ return $this->update; }
	public function getOptions(){ return $this->options; }
	public function getOption($name){ return $this->options[$name]; }

	#[Pure] public function isProtected(){ return !is_null($this->get); }

	public function build(mixed $value){ return $value; }
	public function store(mixed $value){ return $value; }

	public function export(mixed $value){ return $value; }
	public function import(mixed $value){ return $value; }

	static function getValidators(\Atomino\Carbon\Database\Descriptor\Field\Field $field){
		$validators = [];
		if (!$field->isNullable()) $validators[] = [NotNull::class];
		return $validators;
	}
}