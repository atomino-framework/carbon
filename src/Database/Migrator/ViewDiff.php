<?php namespace Atomino\Carbon\Database\Migrator;

class ViewDiff{

	public function __construct(public string $name, private string $from, public string $to){ }

	public function up(): ?string{
		if ($this->to === $this->from) return null;
		if ($this->to === '') return "DROP VIEW IF EXISTS `" . $this->name . "`;";
		return "CREATE OR REPLACE VIEW `" . $this->name . "` AS " . $this->to . ';';
	}

	public function down(): ?string{
		if ($this->to === $this->from) return null;
		if ($this->from === '') return "DROP VIEW IF EXISTS `" . $this->name . "`;";
		return "CREATE OR REPLACE VIEW `" . $this->name . "` AS " . $this->from . ';';
	}
}