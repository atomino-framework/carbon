<?php namespace Atomino\Carbon;

use Atomino\Carbon\Database\Connection;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\CacheInterface;

class Finder extends \Atomino\Carbon\Database\Finder{

	private string $entity;
	private ?CacheInterface $cache;

	public function __construct(Connection $connection, private Model $model){
		parent::__construct($connection);
		$this->table($model->getTable());
		$this->entity = $model->getEntity();
		$this->cache = $model->getCache();
	}

	public function pick(): ?Entity{
		$items = $this->collect(1);
		return array_pop($items);
	}

	public function count():int{
		$this->select("count(*) as `count`");
		return parent::record()['count'];
	}

	/** @return Entity[] */
	public function page(int $size, int $page = 1, int|bool|null &$count = false): array{
		return $this->collect($size, $size * ( $page - 1 ), $count);
	}

	/** @return Entity[] */
	public function collect(?int $limit = null, ?int $offset = null, int|bool|null &$count = false): array{
		$items = [];

		$records = parent::records($limit, $offset, $count);

		/** @var \Atomino\Carbon\Entity $entity */
		$entity = $this->entity;
		foreach ($records as $record){
			$items[] = $entity::build($record);
			$this->cache->get($this->model->generateCacheKey($record['id']), function (CacheItem $item) use ($record){ return $record; });
		}

		return $items;
	}

}