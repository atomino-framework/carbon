<?php namespace Atomino\Carbon;

use Atomino\Carbon\Database\Connection;

class Store{

	protected mixed $data;
	protected bool $loaded = false;

	public function __construct(
		private readonly Connection $connection,
		private readonly string     $name
	) {

	}

	protected function load() { $this->data = json_decode($this->connection->getSmart()->getValue("SELECT `data` FROM `__store` WHERE `name`='$this->name'"), true); }

	public function get(bool $forceReload = false): mixed {
		if (!$this->loaded || $forceReload) $this->load();
		$this->loaded = true;
		return $this->data;
	}

	public function set($data) {
		if ($this->data !== $data) {
			$this->data = $data;
			$this->connection->query("REPLACE INTO __store (`name`, `data`) VALUES('$this->name', '" . json_encode($this->data) . "')");
		}
	}
}