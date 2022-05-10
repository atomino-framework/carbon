<?php namespace Atomino\Carbon\TagHandler;

use Atomino\Carbon\Database\Finder\Comparison;
use Atomino\Carbon\Database\Finder\Filter;
use function Atomino\debug;

class MultiTagHandler extends TagHandler {

	public function update(bool $truncate = false): void {
		$truncate && $this->truncate();
		$this->connection->query("CALL getTags('$this->source', '$this->field', '$this->table')");
	}

	public function rename(string $tag, string $to): void {
		$records = $this->connection->getFinder()->fields("id", $this->field)->table($this->source)->where(Filter::where((new Comparison($this->field))->json_contains($tag)))->records();
		foreach ($records as $record) {
			$id = $record['id'];
			$tags = json_decode($record[$this->field]);
			$tags = array_filter($tags, fn($_) => $_ !== $tag);
			if (trim($to) !== "") $tags[] = $to;
			$tags = array_values(array_unique($tags));
			$this->connection->getSmart()->update($this->source, Filter::where("id=$1", $id), [$this->field => json_encode($tags)]);
		}
		$this->update(true);
	}

	public function remove(string $tag): void { $this->rename($tag, ""); }
}
