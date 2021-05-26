<?php
namespace Atomino\Carbon;


use Atomino\Carbon\Database\Finder\Filter;

/**
 * @property-read int|null $id
 */
interface EntityInterface {
	static function model(): Model;
	public function save(): int|null;
	public function delete();
	public function reload();
	/** @return \Symfony\Component\Validator\ConstraintViolationList[] */
	public function validate(): array;
	public static function build(array $record, Entity|null $into = null): static;
	public function getRecord(): array;
	public function import(array $data);
	public function export(): array;
	public function jsonSerialize();
	static public function search(null|Filter $filter = null): Finder;
	public static function pick(int|null $id): static|null;
	/** @return static[] */
	public static function collect(array $ids): array;
}