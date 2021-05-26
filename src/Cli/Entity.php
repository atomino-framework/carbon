<?php namespace Atomino\Carbon\Cli;

use Atomino\Cli\Attributes\Command;
use Atomino\Cli\CliCommand;
use Atomino\Cli\CliModule;
use Atomino\Carbon\Generator\Generator;
use Symfony\Component\Console\Input\InputArgument;

class Entity extends CliModule{

	#[Command('entity')]
	public function entity():CliCommand{
		return (new class() extends CliCommand{
			protected function exec(mixed $config){
				$generator = new Generator($config['namespace'], $this->style);
				$entity = $this->input->getArgument('entity');
				if(is_null($entity)) $generator->generate();
				else $generator->create($entity);
			}
		})
			->addArgument('entity', InputArgument::OPTIONAL, '', null);
	}
}