<?php namespace Atomino\Carbon\Database\Descriptor\Field;

class DateField extends Field{
	protected ?int $datetimePrecision;

	protected function __construct($descriptor){
		parent::__construct($descriptor);
		$this->datetimePrecision = $descriptor["DATETIME_PRECISION"];
		$this->autoInsert = strtoupper($this->default) === 'CURRENT_TIMESTAMP';
		$this->autoUpdate = str_contains(strtoupper($this->extra), 'CURRENT_TIMESTAMP');
	}



}