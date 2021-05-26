<?php namespace Atomino\Carbon\Database\Migrator;

use Atomino\Carbon\Database\Connection;
use Atomino\Carbon\Database\Migrator\Differ\Parser;

class Diff{

	protected $diff;

	public function getDiff(){ return $this->diff; }

	public function __construct($from, Connection $connection, ...$except){
		$parser = new Parser();
		$fromDb = $parser->parseDatabase($from);
		$smart = $connection->getSmart();
		$descriptor = $connection->getDescriptor();

		$diffViews = [];

		$matches = preg_match_all('/^CREATE\s+(.*?)VIEW\s+`(.*?)`\s+AS\s+(.*?);$/m', $from, $result);
		for ($i = 0; $i < $matches; $i++) $diffViews[$result[2][$i]] = new ViewDiff($result[2][$i], $result[3][$i], '');

		$diffTables = [];
		$views = [];
		$tables = [];
		$dirty = false;

		foreach ($fromDb->getTables() as $table) if (!in_array($table->getName(), $except)) $diffTables[$table->getName()] = new TableDiff($table->getName(), $table->getCreationScript(), '');

		foreach ($smart->getValues('Show Tables') as $table){
			if (!in_array($table, $except)){
				if ($descriptor->getTable($table)->isView()){
					$create = $connection->query("SHOW CREATE TABLE `" . $table . "`")->fetchColumn(1);
					preg_match('/^CREATE\s+(.*?)VIEW\s+`(.*?)`\s+AS\s+(.*?)$/', $create, $result);
					if (array_key_exists($table, $diffViews)) $diffViews[$table]->to = $result[3];
					else $diffViews[$table] = new ViewDiff($table, '', $result[3]);
				}else{
					$create = $connection->query("SHOW CREATE TABLE `" . $table . "`")->fetchColumn(1) . ";";
					if (array_key_exists($table, $diffTables)) $diffTables[$table]->to = $create;
					else $diffTables[$table] = new TableDiff($table, '', $create);
				}
			}
		}

		foreach ($diffTables as $diff) if ($diff->up() !== null){
			$dirty = true;
			$tables[$diff->name] = [
				'up'   => $diff->up(),
				'down' => $diff->down(),
			];
		}

		foreach ($diffViews as $diff) if ($diff->up() !== null){
			$dirty = true;
			$views[$diff->name] = [
				'up'   => $diff->up(),
				'down' => $diff->down(),
			];
		}

		$this->diff = [
			'views'  => $views,
			'tables' => $tables,
			'dirty'  => $dirty,
		];
	}
}