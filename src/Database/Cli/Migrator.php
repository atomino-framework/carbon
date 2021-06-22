<?php namespace Atomino\Carbon\Database\Cli;

use Atomino\Core\Cli\Attributes\Command;
use Atomino\Core\Cli\CliCommand;
use Atomino\Core\Cli\CliModule;
use Atomino\Core\Cli\Exceptions\Error;
use Atomino\Core\Cli\Style;
use Atomino\Carbon\Database\Migrator\Exception;
use Atomino\Core\Config\Config;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\Output;

class Migrator extends CliModule {

	public function __construct(private Config $config) { }

	private function getMigrator(Style $style): \Atomino\Carbon\Database\Migrator {
		$migrator = $this->config['connection']->getMigrator($this->config['location'], $this->config['storage']);
		$migrator->setStyle($style);
		return $migrator;
	}

	#[Command(name: 'mig:init', description: 'Initializes the migration')]
	public function init(CliCommand $command) {
		$command->define(function (Input $input, Output $output, Style $style) {
			try {
				Migrator::getMigrator($style)->init();
			} catch (Exception $e) {
				throw new Error($e->getMessage());
			}
		});
	}

	#[Command(name: 'mig:generate', description: 'Creates a new migration')]
	public function generate(CliCommand $command) {
		$command->define(function (Input $input, Output $output, Style $style) {
			try {
				Migrator::getMigrator($style)->generate($input->getOption('force'));
			} catch (Exception $e) {
				throw new Error($e->getMessage());
			}
		});
		$command->addOption('force', ['f'], null, 'Forces the migration generation, even if no changes were found!');
	}

	#[Command(name: 'mig:migrate', description: 'Migrate to version')]
	public function migrate(CliCommand $command) {
		$command->define(function (Input $input, Output $output, Style $style) {
			try {
				Migrator::getMigrator($style)->migrate($input->getArgument('version'));
			} catch (Exception $e) {
				throw new Error($e->getMessage());
			}

		});
		$command->addArgument('version', InputArgument::OPTIONAL, '', 'latest');
	}

	#[Command(name: 'mig:rebuild', description: 'Rebuilds migration')]
	public function rebuild(CliCommand $command) {
		$command->define(function (Input $input, Output $output, Style $style) {
			try {
				Migrator::getMigrator($style)->refresh($input->getArgument('version'));
			} catch (Exception $e) {
				throw new Error($e->getMessage());
			}
		}
		);
		$command->addArgument('version', InputArgument::OPTIONAL, 'version of the migration to work with', 'current');
	}

	#[Command(name: 'mig:status', description: "Shows migration status")]
	public function status(CliCommand $command) {
		$command->define(function (Input $input, Output $output, Style $style) {
			try {
				$migrator = $this->getMigrator($style);
				$migrator->init();
				$migrator->integrityCheck();
				$migrator->statusCheck();
				$migrator->diffCheck();
			} catch (Exception $e) {
				throw new Error($e->getMessage());
			}
		});
	}

}