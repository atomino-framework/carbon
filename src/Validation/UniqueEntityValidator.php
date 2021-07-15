<?php namespace Atomino\Carbon\Validation;

use Atomino\Carbon\Database\Finder\Comparison;
use Atomino\Carbon\Database\Finder\Filter;
use Atomino\Carbon\Entity;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use function count;
use function get_class;

class UniqueEntityValidator extends ConstraintValidator {

	/**
	 * @param Entity $entity
	 * @param Constraint $constraint
	 */
	public function validate($entity, Constraint $constraint): void {
		if (!$constraint instanceof UniqueEntity) {
			throw new UnexpectedTypeException($constraint, UniqueEntity::class);
		}


		/** @var Entity $entityClass */
		$entityClass = get_class($entity);
		$fields = (array)$constraint->fields;
		if (0 === count($fields)) throw new ConstraintDefinitionException('At least one field has to be specified.');

		$criteria = [];

		foreach ($fields as $fieldName) {
			$fieldValue = $entity->$fieldName;
			if ($constraint->ignoreNull && null === $fieldValue) return;
			if (!is_null($fieldValue)) $criteria[$fieldName] = $fieldValue;
		}

		if (empty($criteria)) return;

		$filter = Filter::where(false);
		foreach ($criteria as $field => $value) $filter->and((new Comparison($field))->is($value));
		if($entity->id !== null) $filter->and((new Comparison('id'))->not($entity->id));

		if ($entityClass::search($filter)->count() === 0) return;

		$errorPath = (string)($constraint->errorPath ?? $fields[0]);
		$invalidValue = $criteria[$errorPath] ?? $criteria[$fields[0]];

		$this->context->buildViolation($constraint->message)
		              ->atPath($errorPath)
		              ->setInvalidValue($invalidValue)
		              ->setCode(UniqueEntity::IS_NOT_UNIQUE)
		              ->addViolation()
		;
	}
}