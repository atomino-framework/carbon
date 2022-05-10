<?php namespace Atomino\Carbon\TagHandler;

use Atomino\Carbon\Database\Finder\Comparison;
use Atomino\Carbon\Database\Finder\Filter;
use function Atomino\debug;

class SingleTagHandler extends TagHandler {
	public function update(bool $truncate = false): void {
		$truncate && $this->truncate();
		$this->connection->query("INSERT INTO `$this->table` select distinct `$this->field` as tag from `$this->source` where `$this->field` is not null order by `$this->field`");
	}
	public function rename(string $tag, string $to): void {
		$this->connection->getSmart()->update($this->source, Filter::where($this->field . "=$1", $tag), [$this->field => $to]);
		$this->update(true);
	}
	public function remove(string $tag): void { $this->rename($tag, ""); }
}
