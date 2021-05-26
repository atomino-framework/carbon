<?php namespace Atomino\Carbon;


use JetBrains\PhpStorm\Pure;

class ValidationError extends \Exception {

	#[Pure] public function __construct(private array $errors){
		parent::__construct("Validation error");
	}

	public function getErrors(){return $this->errors; }
	public function getMessages(){return array_map(function ($error){return ['field'=>$error['field'], 'message'=>$error['message']];}, $this->errors); }

}