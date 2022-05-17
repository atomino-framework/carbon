<?php namespace Atomino\Carbon\TagHandler;

use Atomino\Carbon\Database\Connection;

class TagGroupHandler extends AbstractTagHandler implements I_TagGroupHandler {
	public function __construct(
		protected Connection $connection,
		protected string     $table,
		protected string     $source,
		protected string     $sourceTagField,
		protected string     $sourceGroupField
	) {
	}

	public function rebuild(): void { $this->rebuildWithSelect("SELECT DISTINCT `$this->sourceTagField` as `tag`, `$this->sourceGroupField` as `group` FROM `$this->source`"); }

	/** @return string[] */
	public function groups(): array { return $this->connection->getFinder()->select("DISTINCT `group`")->table($this->table)->asc("group")->values(); }
	public function group(string $group): I_TagManager { return new TagGroupManager($this->connection, $this->table, $this->source, $this->sourceTagField, $this->sourceGroupField, $group, $this); }
}
