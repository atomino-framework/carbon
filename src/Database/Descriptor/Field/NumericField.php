<?php namespace Atomino\Carbon\Database\Descriptor\Field;

abstract class NumericField extends Field{
	protected ?int $numericPrecision;
	protected ?int $numericScale;
	protected bool $signed;

	protected function __construct($descriptor){
		parent::__construct($descriptor);
		$this->numericPrecision = $descriptor["NUMERIC_PRECISION"];
		$this->numericScale = $descriptor["NUMERIC_SCALE"];
		$this->signed = !str_contains($this->typeString, 'unsigned');
	}

	public function isSigned(): bool{ return $this->signed; }
}