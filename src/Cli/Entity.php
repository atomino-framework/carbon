<?php namespace Atomino\Carbon\Cli;

use Atomino\Core\Cli\Attributes\Command;
use Atomino\Core\Cli\CliCommand;
use Atomino\Core\Cli\CliModule;
use Atomino\Carbon\Generator\Generator;
use Symfony\Component\Console\Input\InputArgument;

class Entity extends CliModule{

	#[Command('entity:generate', "entity", "Regenerates all entities")]
	public function entity():CliCommand{
		return (new class extends CliCommand{
			protected function exec(mixed $config){
				$generator = new Generator($config['namespace'], $this->style);
				$generator->generate();
			}
		});
	}


	#[Command('entity:create', description: "Create new entity")]
	public function create():CliCommand{
		return (new class extends CliCommand{
			protected function exec(mixed $config){
				$generator = new Generator($config['namespace'], $this->style);
				$entity = $this->input->getArgument('entity');
				$generator->create($entity);
			}
		})
			->addArgument('entity', InputArgument::REQUIRED, '', null);
	}
	
}