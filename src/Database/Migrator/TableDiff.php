<?php namespace Atomino\Carbon\Database\Migrator;

use Atomino\Carbon\Database\Migrator\Differ\Differ;
use Atomino\Carbon\Database\Migrator\Differ\Model\DatabaseDiff;
use Atomino\Carbon\Database\Migrator\Differ\Parser;

class TableDiff{

	public function __construct(public string $name, private string $from, public string $to){ }

	public function up(): ?string{
		$differ = new Differ();
		$parser = new Parser();
		$diff = $differ->diffDatabases($parser->parseDatabase($this->from), $parser->parseDatabase($this->to));
		return $this->createAlterScript($diff);
	}

	public function down(): ?string{
		$differ = new Differ();
		$parser = new Parser();
		$diff = $differ->diffDatabases($parser->parseDatabase($this->to), $parser->parseDatabase($this->from));
		return $this->createAlterScript($diff);
	}

	protected function createAlterScript(DatabaseDiff $diff): ?string{
		if (count($diff->getDeletedTables())) return "DROP TABLE IF EXISTS `" . $diff->getDeletedTables()[0]->getName() . "`;";
		if (count($diff->getNewTables())) return $diff->getNewTables()[0]->getCreationScript();
		if (count($diff->getChangedTables())) return $diff->getChangedTables()[0]->generateAlterScript();
		return null;
	}

}