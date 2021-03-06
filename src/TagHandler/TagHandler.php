<?php namespace Atomino\Carbon\TagHandler;

use Atomino\Carbon\Database\Connection;
use Atomino\Carbon\Database\Finder\Comparison;
use Atomino\Carbon\Database\Finder\Filter;

class TagHandler extends AbstractTagHandler implements I_TagHandler, I_TagManager {
	public function __construct(
		protected Connection $connection,
		protected string     $table,
		protected string     $source,
		protected string     $sourceTagField
	) {
	}

	public function rebuild(): void {
		$this->rebuildWithSelect("SELECT DISTINCT `$this->sourceTagField` as `tag` FROM `$this->source`");
	}

	public function getTags(?string $search = null): array {
		$filter = (!is_null($search) && trim($search) !== "")
			? Filter::where(Comparison::field("tag")->instring($search))
			: null;
		return $this->connection->getFinder()->fields("tag")->table($this->table)->where($filter)->asc("tag")->values();
	}

	public function renameTag(string $tag, string|null $to): void {
		$to = (is_null($to) || trim($to) === "") ? null : trim($to);
		if ($tag === $to) return;

		try {
			$this->connection->getSmart()->update($this->source, Filter::where(Comparison::field($this->sourceTagField)->is($tag)),  [$this->sourceTagField => $to]);
		} finally {
			$this->rebuild();
		}
	}
	public function removeTag(string $tag): void { $this->renameTag($tag, null); }
}