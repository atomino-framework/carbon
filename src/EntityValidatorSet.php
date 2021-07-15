<?php namespace Atomino\Carbon;

class EntityValidatorSet{

	private array $constraints = [];

	public function addValidator(\Symfony\Component\Validator\Constraint $constraint){
		$this->constraints[] = $constraint;
		return $this;
	}

	/** @return \Symfony\Component\Validator\Constraint[] */
	public function getConstraints(): array{
		return $this->constraints;
	}

}