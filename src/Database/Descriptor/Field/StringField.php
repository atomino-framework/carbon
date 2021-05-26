<?php namespace Atomino\Carbon\Database\Descriptor\Field;

class StringField extends Field{

	protected ?int $maxLength;
	protected ?string $characterSet;
	protected ?string $collation;

	protected function __construct($descriptor){
		parent::__construct($descriptor);
		$this->maxLength = $descriptor["CHARACTER_MAXIMUM_LENGTH"];
		$this->characterSet = $descriptor["CHARACTER_SET_NAME"];
		$this->collation = $descriptor["COLLATION_NAME"];
	}

	public function getMaxLength(): mixed{return $this->maxLength;}


}