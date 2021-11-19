<?php

namespace Atomino\Carbon\Link;

use Atomino\Carbon\Database\Connection;
use Atomino\Carbon\Database\Finder\Comparison;
use Atomino\Carbon\Database\Finder\Filter;
use Atomino\Carbon\Entity;
use Atomino\Carbon\Finder;

class LinkHandler {

	protected string $table;
	protected Connection $connection;

	public function __construct(
		protected Side        $side,
		protected Side        $other,
		protected string      $linkEntityClass,
		protected Entity|null $item = null
	) {
		$this->table = $linkEntityClass::model()->getTable();
		$this->connection = $linkEntityClass::model()->getConnection();
	}

	public function link(Entity $otherItem): Entity|null {
		$this->validate($otherItem, false, false);

		if (($link = $this->has($otherItem)) !== null) return $link;

		if ($this->checkLimit($this->side, $this->item) && $this->checkLimit($this->other, $otherItem)) {
			$data = [$this->side->field => $this->item->id, $this->other->field => $otherItem->id];
			$this->connection->getSmart()->insert($this->table, $data, true);
		}
		return $this->has($otherItem);
	}

	public function unlink(Entity|null $otherItem = null): void {
		$this->validate($otherItem, false, true);

		$filter = Filter::where(Comparison::field($this->side->field, $this->item->id));
		if (!is_null($otherItem)) $filter->and(Comparison::field($this->other->field, $otherItem->id));
		$this->connection->getSmart()->delete($this->table, $filter);
	}

	public function sync(Entity ...$otherItems): void {
		if ($this->count($otherItems) === 0) $this->unlink();
		else {
			$ids = array_map(function (Entity $item) {
				$this->validate($item, false, false);
				return $item->id;
			}, $otherItems);
			$this->connection->getSmart()->delete(
				$this->table,
				Filter::where(Comparison::field($this->side->field, $this->item->id))
				      ->and(Comparison::field($this->other->field)->notIn($ids))
			);
		}

		foreach ($otherItems as $otherItem) $this->link($otherItem);
	}

	public function relink(Entity $from, Entity $to): void {
		$this->validate($from, true, false);
		$this->validate($to, true, false);
		$filter = Filter::where(Comparison::field($this->other->field, $from->id));
		if (!is_null($this->item)) $filter->and(Comparison::field($this->side->field, $this->item->id));
		$this->connection->getSmart()->update($this->table, $filter, [$this->other->field => $to->id]);
	}

	public function has(Entity $otherItem): Entity|null {
		$this->validate($otherItem, false, false);
		return $this->linkEntityClass::search(
			Filter::where(Comparison::field($this->side->field, $this->item->id))
			      ->and(Comparison::field($this->other->field, $otherItem->id))
		)->pick();
	}

	public function count(): int {
		$this->validate(null, false, true);
		return $this->getCount($this->side->field, $this->item->id);
	}

	public function search(Entity $otherItem, Entity ...$otherItems): Finder {
		$otherItems[] = $otherItem;

		$ids = array_map(function (Entity $item) {
			$this->validate($item, true, false);
			return $item->id;
		}, $otherItems);

		/** @var Finder $finder */
		$finder = $this->side->class::search();
		$finder->fields($this->side->table . ".*")
		       ->join($this->table, null, Filter::where(
			       Comparison::field($this->table . "." . $this->side->field, $this->side->table . ".id")->esc())
		       )
		;
		if (count($otherItems) === 1) {
			$finder->where(Filter::where(Comparison::field($this->table . "." . $this->other->field, $ids[0])));
		} else {
			$finder->where(Filter::where(Comparison::field($this->table . "." . $this->other->field, $ids)))
			       ->groupBy($this->table . "." . $this->side->field)
			       ->having(Filter::where(Comparison::exp("Count(*)", count($ids))))
			;
		}

		return $finder;
	}


	protected function getCount(string $field, int $id) {
		return $this->connection->getFinder()
		                        ->select("count(*)")
		                        ->table($this->table)
		                        ->where(Filter::where(Comparison::field($field, $id)))
		                        ->integer()
		;
	}

	protected function checkLimit(Side $side, Entity $item): bool {
		if ($side->limit === null) return true;
		return $side->limit < $this->getCount($side->field, $item->id);
	}

	protected function validate(Entity|null $otherItem, bool $allowNullSide, bool $allowNullOther) {
		if (is_null($this->item) && !$allowNullSide) throw new \Exception("LinkHandler: 'This side' item can not be null");
		if (is_null($otherItem) && !$allowNullOther) throw new \Exception("LinkHandler: 'Other side' item can not be null");
		if (!is_null($this->item) && !($this->item instanceof $this->side->class)) throw new \Exception("LinkHandler: 'This side' got wrong (" . get_class($item) . ") Entity instance (expected: " . $this->side->class . ")");
		if (!is_null($otherItem) && !($otherItem instanceof $this->other->class)) throw new \Exception("LinkHandler: 'Other side' got wrong (" . get_class($item) . ") Entity instance (expected: " . $this->other->class . ")");
		if (!is_null($otherItem) && !($otherItem->id)) throw new \Exception("LinkHandler: 'Other side' object hasn't got id");
		if (!is_null($this->item) && !($this->item->id)) throw new \Exception("LinkHandler: 'This side' object hasn't got id");
	}
}
