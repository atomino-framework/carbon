<?php namespace Atomino\Carbon\Database;

use Atomino\Cli\Style;
use Atomino\Carbon\Database\Finder\Filter;
use Atomino\Carbon\Database\Migrator\Diff;
use Atomino\Carbon\Database\Migrator\Exception;
use JetBrains\PhpStorm\Pure;
use Symfony\Component\Console\Style\StyleInterface;

class Migrator{

	private Style|null $style = null;

	public function __construct(private Connection $connection, private string $location, private string $table){
		$this->location = realpath($this->location) . '/';
		chdir($this->location);
	}

	public function init(){
		if (!is_dir($this->location)){
			$this->style?->_error('<fg=red>Migration location does not exists.</>');
			$this->style?->_task('Create folder ' . $this->location);
			mkdir($this->location, 0777, true);
			$this->style?->_task_ok();
		}
		if (is_null($this->connection->getDescriptor()->getTable($this->table))){
			$this->style?->_error('<fg=red>Migration storage does not exists.</>');
			$this->style?->_task('Create table ' . $this->table);
			$this->connection->query('CREATE TABLE ' . $this->connection->escape($this->table) . " (
					  `structure` text NOT NULL,
					  `rollback` text,
					  `version` int(11) unsigned NOT NULL,
					  `integrity` varchar(255) DEFAULT '',
					  UNIQUE KEY `version` (`version`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
			$this->style?->_task_ok();
		}
	}

	public function generate(bool $force = false): int|bool{

		$this->init();
		$this->integrityCheck();
		$this->statusCheck();

		$diff = $this->diffCheck();

		if ($diff['dirty'] === false && !$force) return false;

		$version = $this->getNextVersionNumber();
		$this->style?->_task('Generate migration: ' . $version);

		$versionLocation = $this->location . $this->stringifyVersionNumber($version) . '/';
		if (!is_dir($versionLocation)) mkdir($versionLocation);

		foreach ($diff['views'] as $view => $migration){
			file_put_contents($versionLocation . 'up.view.' . $view . '.sql', $migration['up']);
			file_put_contents($versionLocation . 'down.view.' . $view . '.sql', $migration['down']);
		}
		foreach ($diff['tables'] as $table => $migration){
			file_put_contents($versionLocation . 'up.table.' . $table . '.sql', $migration['up']);
			file_put_contents($versionLocation . 'down.table.' . $table . '.sql', $migration['down']);
		}
		file_put_contents($versionLocation . 'up.script.sql', "SET FOREIGN_KEY_CHECKS = 0;\n--run up.table.*.sql\n--run up.view.*.sql\nSET FOREIGN_KEY_CHECKS = 1;\n");
		file_put_contents($versionLocation . 'down.script.sql', "SET FOREIGN_KEY_CHECKS = 0;\n--run down.table.*.sql\n--run down.view.*.sql\nSET FOREIGN_KEY_CHECKS = 1;\n");
		$this->connection->getSmart()->insert($this->table, ['version' => $version, 'structure' => $this->getDump()]);
		$this->style->_task_ok();
		$this->refresh($version);
		return $version;
	}

	public function migrate($version){
		if ($version === 'latest') $version = $this->getLatestMigrationVersion();
		$this->integrityCheck();
		$version = intval($version);
		$current = intval($this->getCurrentVersion());
		if (!is_dir($this->location . $this->stringifyVersionNumber($version))){
			throw new Exception('Migration (' . $version . ') files not found!', Exception::VersionNotFound);
		}
		if ($version === $current){
			$this->style->_ok('Database is already on the requested version (' . $version . ')', true);
		}elseif ($version < $current){
			$this->style->_icon('↘', 'magenta', 'Going to version ' . $version, true);

			$migrations = $this->connection->getSmart()->getRows("SELECT * FROM " . $this->table . " WHERE version>" . $version . " ORDER BY version DESC");
			foreach ($migrations as $migration){
				$this->style->_task('Applying down script ' . $migration['version']);

				try{
					$this->connection->getPdo()->exec($migration['rollback']);
				}catch (\Exception $e){
					$this->style?->_task_error();
					$this->style->_note(preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $migration['rollback']), 'Error');
					throw new Exception();
				}
				$this->style?->_task_ok();
				$this->connection->getSmart()->delete($this->table, Filter::where('version=$1', $migration['version']));

			}
		}elseif ($version > $current){
			$this->style->_icon('↗', 'magenta', 'Going to version ' . $version, true);

			for ($i = $current + 1; $i <= $version; $i++){
				$this->style->_task('Applying up script ' . $i);
				$sql = $this->parseScript($this->location . $this->stringifyVersionNumber($i) . '/', 'up.script.sql');
				try{
					$this->connection->getPdo()->exec($sql);
				}catch (\Exception $e){
					$this->style?->_task_error();
					$this->style->_note(preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $sql), 'Error');
					throw new Exception();
				}
				$this->style?->_task_ok();
				$this->connection->getSmart()->insert($this->table, ['version' => $i, 'structure' => $this->getDump()]);
				$this->refresh($i);
			}
		}
	}

	public function refresh($version){
		if ($version === 'current') $version = $this->getCurrentVersion();
		$this->style?->_task('(re)building version: ' . $version);
		$versionLocation = $this->location . $this->stringifyVersionNumber($version) . '/';
		$downScript = $this->parseScript($versionLocation, 'down.script.sql');
		$this->connection->getSmart()->update($this->table, Filter::where('version=$1', $version), ['rollback' => $downScript, 'integrity' => $this->calculateIntegrity($version)]);
		$this->style?->_task_ok($this->calculateIntegrity($version));
	}

	public function integrityCheck(){
		$versions = $this->connection->getSmart()->getRows("SELECT version, integrity FROM " . $this->table . " ORDER BY version");
		$lastClean = null;
		foreach ($versions as $version){
			$this->style?->_task('checking integrity: <fg=cyan>' . $this->stringifyVersionNumber($version['version']) . '</> ' . $version['integrity'] . ' ');

			$integrity = $this->calculateIntegrity($version['version']);
			if ($integrity === null){
				$this->style?->_task_error('LOST');
				if (is_null($lastClean)) $lastClean = intval($version['version']);
			}elseif ($version['integrity'] !== $integrity){
				$this->style?->_task_error('DIRTY');
				if (is_null($lastClean)) $lastClean = intval($version['version']);
			}else{
				$this->style?->_task_ok();
			}
		}

		if (!is_null($lastClean)){
			throw new Exception('', Exception::IntegrityCheckError);
		}
	}

	public function statusCheck(){
		//$this->style?->_section('Status check');
		$current = intval($this->getCurrentVersion());
		$latest = $this->getLatestMigrationVersion();
		$this->style?->_task('checking version status');
		if ($latest === $current){
			$this->style?->_task_ok('version: ' . $current);
		}else{
			$this->style?->_task_error('Your database (' . $current . ') is not on the latest (' . $latest . ') version', false);
			throw new Exception('', Exception::StatusCheckError);
		}
	}

	public function diffCheck(){
		//$this->style?->_section('Diff check');
		$this->style?->_task('diff check');

		$diff = $this->getDiff();
		if ($diff['dirty'] === false){
			$this->style?->_task_ok('No changes found');
		}else{
			$this->style?->_task_warn('Changes found');
			$changes = '';
			foreach ($diff['tables'] as $table => $migration) $changes .= $migration['up'] . "\n\n";
			foreach ($diff['views'] as $view => $migration) $changes .= $migration['up'] . "\n\n";
			$this->style?->_note(message: $changes);
		}
		return $diff;
	}

	protected function parseScript($location, $script){
		if (!file_exists($location . $script)) throw new Exception('AttachmentCategory not found: ' . $location . $script);

		$script = file_get_contents($location . $script);
		$matches = preg_match_all("/^--run\s+(.*?)$/m", $script, $result);
		for ($i = 0; $i < $matches; $i++){
			$files = glob($location . $result[1][$i]);
			$includes = '';
			foreach ($files as $file){
				$includes .= file_get_contents($file) . "\n";
			}
			$script = str_replace($result[0][$i], $includes, $script);
		}
		return $script;
	}

	protected function getDump(){
		$dumper = $this->connection->getDumper($this->location, $this->location);
		$dumper->structure('dump.sql');
		$structure = file_get_contents($this->location . '/dump.sql');
		unlink($this->location . '/dump.sql');
		return $structure;
	}

	protected function getDiff(){
		$prevStructure = $this->getPreviousStructure();
		$structure = $this->getDump();
		return ( new Diff($prevStructure, $this->connection, $this->table) )->getDiff();
	}

	protected function getCurrentVersion(){ return $this->connection->getSmart()->getValue("SELECT Max(version) FROM " . $this->table); }

	protected function getLatestMigrationVersion(){
		$dirs = glob('*', GLOB_ONLYDIR);
		return intval(end($dirs));
	}

	protected function getNextVersionNumber(){
		$dirs = glob('*', GLOB_ONLYDIR);
		if (count($dirs) === 0) return 1;
		return intval(end($dirs)) + 1;
	}

	protected function getPreviousStructure(){ return $this->connection->getSmart()->getValue("SELECT structure FROM " . $this->table . " ORDER BY version DESC LIMIT 1"); }

	#[Pure] protected function stringifyVersionNumber($version){ return str_pad($version, 6, '0', STR_PAD_LEFT); }

	protected function calculateIntegrity($version){
		if (!is_dir($this->stringifyVersionNumber($version))) return null;
		$files = glob($this->stringifyVersionNumber($version) . '/*.sql');
		$hash = '';
		foreach ($files as $file) $hash .= md5_file($file);
		return md5($hash);
	}

	public function setStyle(?StyleInterface $style): void{ $this->style = $style; }

}