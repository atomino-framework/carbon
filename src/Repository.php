<?php

namespace Atomino\Carbon;

use Atomino\Carbon\Database\Connection;
use Atomino\Carbon\Database\Finder\Comparison;
use Atomino\Carbon\Database\Finder\Filter;
use JetBrains\PhpStorm\Pure;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class Repository{

	private Connection $connection;
	private string $table;
	private string $entity;
	private CacheInterface $cache;

	#[Pure] public function __construct(private Model $model){
		$this->connection = $model->getConnection();
		$this->table = $model->getTable();
		$this->entity = $model->getEntity();
		$this->cache = $model->getCache();
	}

	public function insert(Entity $item): int{
		$data = $item->getRecord();
		$record = [];
		foreach ($item::model()->getFields() as $field){
			if ($field->isInsert()) $record[$field->getName()] = $data[$field->getName()];
		}
		return $this->connection->getSmart()->insert($this->table, $record);
	}

	public function update(Entity $item): int{
		$data = $item->getRecord();
		$record = [];
		foreach ($item::model()->getFields() as $field){
			if ($field->isUpdate()) $record[$field->getName()] = $data[$field->getName()];
		}
		$this->cache->delete($this->model->generateCacheKey($item->id));
		return $this->connection->getSmart()->updateById($this->table, $item->id, $record);
	}

	public function delete(Entity $item){
		$this->connection->query("DELETE FROM " . $this->connection->escape($this->table) . " WHERE id=" . $this->connection->quote($item->id));
		$this->cache->delete($this->model->generateCacheKey($item->id));
	}

	public function pick(int|null $id, Entity|null $into = null): Entity|null{
		if (is_null($id)) return null;
		/** @var \Atomino\Carbon\Entity $entity */
		$entity = $this->entity;

		$record = $this->cache->get(
			$this->model->generateCacheKey($id),
			function (ItemInterface $item) use ($id){
				return $this->connection
					->query("SELECT * FROM " . $this->connection->escape($this->table) . " WHERE id=" . $this->connection->quote($id))
					->fetch(\PDO::FETCH_ASSOC);
			});

		return $record ? $entity::build($record, $into) : null;
	}

	/** @return \Atomino\Carbon\Entity[] */
	public function collect(array $ids): array{
		$objects = [];
		$request = [];
		foreach ($ids as $id){
			if ($this->cache->hasItem($this->model->generateCacheKey($id))) $objects[] = $this->pick($id);
			else $request[] = $id;
		}
		if (count($request)){
			$objects = array_merge($objects, $this->search(Filter::where(( new Comparison('id') )->in($request)))->collect());
		}
		return $objects;
	}

	public function search(null|Filter $filter): Finder{ return ( new Finder($this->connection, $this->model) )->where($filter); }

}