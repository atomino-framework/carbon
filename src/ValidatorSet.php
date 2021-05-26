<?php namespace Atomino\Carbon;

class ValidatorSet{

	private array $constraints = [];

	public function addValidator(string $field, \Symfony\Component\Validator\Constraint $constraint){
		if (!array_key_exists($field, $this->constraints)) $this->constraints[$field] = [];
		$this->constraints[$field][] = $constraint;
		return $this;
	}

	/** @return \Symfony\Component\Validator\Constraint[] */
	public function getConstraints(): array{
		return $this->constraints;
	}

}