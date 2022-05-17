<?php namespace Atomino\Carbon\TagHandler;

use Atomino\Carbon\Database\Connection;
use Atomino\Carbon\Database\Finder\Comparison;
use Atomino\Carbon\Database\Finder\Filter;

class TagGroupManager implements I_TagManager {
	public function __construct(
		protected Connection $connection,
		protected string     $table,
		protected string     $source,
		protected string     $sourceTagField,
		protected string     $sourceGroupField,
		protected string     $group,
		protected I_TagHandler $handler
	) {
	}
	public function getTags(?string $search = null): array {
		$filter = Filter::where(Comparison::field("group")->is($this->group));
		if (is_null($search) || trim($search) === "") $filter->and(Comparison::field("tag")->instring($search));
		return $this->connection->getFinder()->fields("tag")->table($this->table)->where($filter)->asc("tag")->values();
	}
	public function renameTag(string $tag, string|null $to): void {
		$to = (is_null($to) || trim($to) === "") ? null : trim($to);
		if ($tag === $to) return;
		try {
			$this->connection->getSmart()->update(
				$this->source,
				Filter::where(Comparison::field($this->sourceGroupField)->is($this->group))
				      ->and(Comparison::field($this->sourceTagField)->is($tag)),
				[$this->sourceTagField => $to]
			);
		} finally {
			$this->handler->rebuild();
		}
	}
	public function removeTag(string $tag): void { $this->renameTag($tag, null); }
}