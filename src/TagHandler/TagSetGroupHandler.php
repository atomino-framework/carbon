<?php namespace Atomino\Carbon\TagHandler;

class TagSetGroupHandler extends TagGroupHandler {

	public function rebuild(): void {
		$this->rebuildWithSelect("SELECT DISTINCT `data`.`tag` AS `tag`, `$this->source`.`$this->sourceGroupField` AS `group` FROM `$this->source`, JSON_TABLE(`$this->source`.`$this->sourceTagField`,'$[*]' COLUMNS(`tag` Varchar(255) PATH '$')) `data`");
	}
	public function group(string $group): I_TagManager { return new TagSetGroupManager($this->connection, $this->table, $this->source, $this->sourceTagField, $this->sourceGroupField, $group, $this); }
}