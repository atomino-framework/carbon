<?php namespace Atomino\Carbon\Cli;

use Atomino\Carbon\Generator\Generator;
use Atomino\Carbon\Link\LinkTableCreator;
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

class CreateLinkTable extends CliModule {

	public function __construct(private LinkTableCreator $linkTableCreator) { }

	#[Command('link-table', null, "Create link-table")]
	public function entity(CliCommand $command) {
		$command->addArgument('table', InputArgument::REQUIRED, '', null);
		$command->addArgument('left-table', InputArgument::REQUIRED, '', null);
		$command->addArgument('right-table', InputArgument::REQUIRED, '', null);

		$command->define(function (Input $input, Output $output, Style $style) {
			$this->linkTableCreator->create($input->getArgument('table'),$input->getArgument('left-table'),$input->getArgument('right-table'));
		});
	}
}