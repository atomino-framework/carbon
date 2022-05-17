<?php namespace Atomino\Carbon\TagHandler;

use Atomino\Carbon\Database\Finder\Comparison;
use Atomino\Carbon\Database\Finder\Filter;
use function Atomino\debug;

class TagSetGroupManager extends TagGroupManager {
	public function renameTag(string $tag, string|null $to): void {
		$to = (is_null($to) || trim($to) === "") ? null : trim($to);
		if ($tag === $to) return;

		try {
			$records = $this->connection->getFinder()->table($this->source)->fields("id", $this->sourceTagField)->where(
				Filter::where(Comparison::field($this->sourceTagField)->json_contains($tag))
				      ->and(Comparison::field($this->sourceGroupField)->is($this->group))
			)->records();
			foreach ($records as $record) {
				$tags = $record[$this->sourceTagField];
				$id = $record["id"];
				$tags = is_null($to)
					? array_splice($tags, array_search($tag, $tags), 1)
					: $tags = array_unique(array_replace(json_decode($tags), [$tag => $to]));
				$this->connection->getSmart()->update($this->source, Filter::where(Comparison::field("id")->is($id)), [$this->sourceTagField => json_encode($tags)]);
			}
		} finally {
			$this->handler->rebuild();
		}
	}
}