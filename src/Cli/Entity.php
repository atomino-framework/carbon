<?php namespace Atomino\Carbon\Cli;

use Atomino\Carbon\Generator\Generator;
use Atomino\Core\Cli\Attributes\Command;
use Atomino\Core\Cli\CliCommand;
use Atomino\Core\Cli\CliModule;
use Atomino\Core\Cli\Style;
use Atomino\Core\Config\Config;
use Atomino\Core\PathResolverInterface;
use Atomino\Neutrons\CodeFinderInterface;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\Output;

class Entity extends CliModule {

	public function __construct(private CodeFinderInterface $codeFinder, private PathResolverInterface $pathResolver, private Config $config) { }

	#[Command('entity:generate', "entity", "Regenerates all entities")]
	public function entity(CliCommand $command) {
		$command->define(function (Input $input, Output $output, Style $style) {
			$generator = new Generator($this->config['namespace'], $style, $this->codeFinder, $this->pathResolver);
			$generator->generate();
		});
	}

	#[Command('entity:create', description: "Create new entity")]
	public function create(CliCommand $command) {
		$command->addArgument('entity', InputArgument::REQUIRED, '', null);
		$command->define(function (Input $input, Output $output, Style $style) {
			$generator = new Generator($this->config['namespace'], $style, $this->codeFinder, $this->pathResolver);
			$entity = $this->input->getArgument('entity');
			$generator->create($entity);
		});
	}

}