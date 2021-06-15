<?php namespace Atomino\Carbon\Database\Cli;

use Atomino\Core\Cli\Attributes\Command;
use Atomino\Core\Cli\CliCommand;
use Atomino\Core\Cli\CliModule;
use Atomino\Core\Cli\Exceptions\Error;
use Atomino\Core\Cli\Style;
use Atomino\Carbon\Database\Migrator\Exception;
use Symfony\Component\Console\Input\InputArgument;

class Migrator extends CliModule{

	static function getMigrator(mixed $config, Style $style): \Atomino\Carbon\Database\Migrator{
		$migrator = $config['connection']->getMigrator($config['location'], $config['storage']);
		$migrator->setStyle($style);
		return $migrator;
	}

	#[Command( name: 'mig:init', description: 'Initializes the migration' )]
	public function init(): CliCommand{
		return ( new class extends CliCommand{
			protected function exec(mixed $config){
				try{
					Migrator::getMigrator($config, $this->style)->init();
				}catch (Exception $e){
					throw new Error($e->getMessage());
				}
			}
		} );
	}

	#[Command( name: 'mig:generate', description: 'Creates a new migration' )]
	public function generate(): CliCommand{
		return ( new class extends CliCommand{
			protected function exec(mixed $config){
				try{
					Migrator::getMigrator($config, $this->style)->generate($this->input->getOption('force'));
				}catch (Exception $e){
					throw new Error($e->getMessage());
				}
			}
		} )
			->addOption('force', ['f'], null, 'Forces the migration generation, even if no changes were found!');
	}

	#[Command( name: 'mig:migrate', description: 'Migrate to version' )]
	public function go(): CliCommand{
		return ( new class extends CliCommand{
			protected function exec(mixed $config){
				try{
					Migrator::getMigrator($config, $this->style)->migrate($this->input->getArgument('version'));
				}catch (Exception $e){
					throw new Error($e->getMessage());
				}
			}
		} )
			->addArgument('version', InputArgument::OPTIONAL, '', 'latest');
	}

	#[Command( name: 'mig:rebuild', description: 'Rebuilds migration' )]
	public function rebuild(): CliCommand{
		return ( new class extends CliCommand{
			protected function exec(mixed $config){
				try{
					Migrator::getMigrator($config, $this->style)->refresh($this->input->getArgument('version'));
				}catch (Exception $e){
					throw new Error($e->getMessage());
				}
			}
		} )
			->addArgument('version', InputArgument::OPTIONAL, 'version of the migration to work with', 'current');
	}

	#[Command( name: 'mig:status', description: "Shows migration status" )]
	public function status(): CliCommand{
		return ( new class extends CliCommand{
			protected function exec(mixed $config){
				try{
					$migrator = Migrator::getMigrator($config, $this->style);
					$migrator->init();
					$migrator->integrityCheck();
					$migrator->statusCheck();
					$migrator->diffCheck();
				}catch (Exception $e){
					throw new Error($e->getMessage());
				}
			}
		} );
	}

}