<?php namespace Atomino\Carbon;

use Atomino\Carbon\Database\Finder\Comparison;
use Atomino\Carbon\Database\Finder\Filter;
use Atomino\Carbon\Validation\UniqueEntity;
use Symfony\Component\Validator\Constraints\Unique;
use Symfony\Component\Validator\Constraints\UniqueValidator;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilder;
use Symfony\Contracts\Cache\CacheInterface;

class_alias(CacheInterface::class, \Atomino\Carbon\Cache::class);

/**
 * @property-read int|null $id
 */
abstract class Entity implements \JsonSerializable, EntityInterface {

	const EVENT_BEFORE_UPDATE = "EVENT_BEFORE_UPDATE";
	const EVENT_ON_UPDATE = "EVENT_ON_UPDATE";
	const EVENT_BEFORE_INSERT = "EVENT_BEFORE_INSERT";
	const EVENT_ON_INSERT = "EVENT_ON_INSERT";
	const EVENT_BEFORE_DELETE = "EVENT_BEFORE_DELETE";
	const EVENT_ON_DELETE = "EVENT_ON_AFTER_DELETE";
	const EVENT_ON_LOAD = "EVENT_ON_LOAD";

	protected int|null $id = null;

	protected function handleEvent(string $event, mixed $data = null): bool {
		$result = true;
		foreach (static::model()->getEventHandlers($event) as $eventHandler) {
			$result = ($this->$eventHandler ($event, $data) !== false) && $result;
		}
		return $result;
	}

	static function model(): Model {
		if (is_null(static::$model)) {
			$model = new Model(get_called_class());
			static::$model = $model;
		}
		return static::$model;
	}

	public function __isset(string $name): bool {
		return
			static::model()->hasGetter($name) ||
			static::model()->hasRelation($name) ||
			method_exists($this, $method = '__get' . ucfirst($name));
	}

	public function __get($name) {
		if (static::model()->hasGetter($name)) return $this->{static::model()->getGetter($name)}();
		if (static::model()->hasRelation($name)) return static::model()->getRelation($name)->fetch($this);
		if (method_exists($this, $method = '__get' . ucfirst($name))) return $this->$method();
		return null;
	}

	public function __set($name, $value) {
		if (static::model()->hasSetter($name)) {
			$this->{static::model()->getSetter($name)}($value);
		}
	}

	public static function __callStatic($name, $arguments) {
		if (static::model()->hasField($name)) {
			$comparison = new Comparison($name);
			if (array_key_exists(0, $arguments) && !is_null($arguments[0])) {
				$comparison->isin($arguments[0]);
			}
			return $comparison;
		}
	}

	/**
	 * @return int|null
	 * @throws ValidationError
	 * @throws \PDOException
	 */
	public function save(): int|null {
		if (static::model()->isMutable()) {
			return is_null($this->id) ? $this->insert() : $this->update();
		}
		return null;
	}
	private function insert(): int|null {
		if ($this->handleEvent(self::EVENT_BEFORE_INSERT) === false) return null;
		if (count($errors = $this->validate())) throw new ValidationError($errors);
		$this->id = static::model()->getRepository()->insert($this);
		$this->handleEvent(self::EVENT_ON_INSERT);
		return $this->id;
	}
	private function update(): int|null {
		if ($this->handleEvent(self::EVENT_BEFORE_UPDATE) === false) return null;
		if (count($errors = $this->validate())) throw new ValidationError($errors);
		static::model()->getRepository()->update($this);
		$this->handleEvent(self::EVENT_ON_UPDATE);
		return $this->id;
	}
	public function delete() {
		if ($this->handleEvent(self::EVENT_BEFORE_DELETE) === false) return null;
		static::model()->getRepository()->delete($this);
		$this->handleEvent(self::EVENT_ON_DELETE);
		// TODO: delete attachments
		$this->id = -1;
	}
	public function reload() {
		static::model()->getRepository()->pick($this->id, $this);
	}

	/** @return \Symfony\Component\Validator\ConstraintViolationList[] */
	public function validate(): array {
		$errors = [];

		$constraints = static::model()->getValidators()->getConstraints();
		$entityConstraints = self::model()->getEntityValidators()->getConstraints();
		$validator = Validation::createValidator();

		$violations = $validator->validate($this, $entityConstraints);
		for ($i = 0; $i < $violations->count(); $i++) {
			$errors[] = [
				'field'     => null,
				'message'   => $violations->get($i)->getMessage(),
				'violation' => $violations->get($i),
			];
		}
		foreach ($constraints as $field => $constraint) {
			$violations = $validator->validate($this->$field, $constraint);
			for ($i = 0; $i < $violations->count(); $i++) {
				$errors[] = [
					'field'     => $field,
					'message'   => $violations->get($i)->getMessage(),
					'violation' => $violations->get($i),
				];
			}
		}
		return $errors;
	}

	static public function create(): static {
		return static::model()->getContainer()->make(static::class);
	}

	static public function build(array $record, Entity|null $into = null): static {
		$item = is_null($into) ? static::create() : $into;
		foreach ($record as $key => $value) {
			if (!is_null($field = static::model()->getField($key))) {
				$item->$key = $field->build($value);
			}
		}
		$item->handleEvent(self::EVENT_ON_LOAD);
		return $item;
	}

	public function getRecord(): array {
		$record = [];
		foreach (static::model()->getFields() as $field) {
			$record[$field->getName()] = $field->store($this->{$field->getName()});
		}
		return $record;
	}

	public function import(array $data):static {
		foreach (static::model()->getFields() as $field) {
			$fieldName = $field->getName();
			if (array_key_exists($fieldName, $data)) {
				if ($field->isProtected()) {
					if (!is_null($setter = $field->getSetter())) {
						$data[$fieldName] = $this->{$setter}($field->import($data[$fieldName]));
					}
				} else {
					$this->{$fieldName} = $field->import($data[$fieldName]);
				}
			}
		}
		return $this;
	}

	public function export(): array {
		$data = [];
		foreach (static::model()->getFields() as $field) {
			$fieldName = $field->getName();
			if ($field->isProtected()) {
				if (!is_null($getter = $field->getGetter())) {
					$data[$fieldName] = $field->export($this->{$getter}());
				}
			} else {
				$data[$fieldName] = $field->export($this->{$fieldName});
			}
		}
		return $data;
	}

	public function jsonSerialize() {
		return $this->export();
	}

	static public function search(null|Filter $filter = null): Finder {
		return static::model()->getRepository()->search($filter);
	}

	static public function pick(int|null $id): static|null {
		return is_null($id) ? null : static::model()->getRepository()->pick($id);
	}

	/** @return static[] */
	static public function collect(array $ids): array {
		return static::model()->getRepository()->collect($ids);
	}

}