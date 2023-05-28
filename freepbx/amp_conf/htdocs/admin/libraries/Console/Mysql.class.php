<?php
namespace FreePBX\Console\Command;
//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\Question;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Exception\ProcessFailedException;
use FreePBX;
class Mysql extends Command {
	protected function configure(){
		$this->setName('mysql')
		->setAliases(array('m'))
		->setDescription('Run a mysql Query:')
		->setDefinition(array(
			new InputArgument('args', InputArgument::IS_ARRAY, null, null),));
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		global $amp_conf; //an angel lost it's wings today for using this
		$dbtype = $amp_conf["AMPDBENGINE"];

		if($dbtype != "mysql") {
			$output->writeln("<error>"._("Only mysql is supported")."</error>");
			exit(1);
		}

		$this->FreePBX = FreePBX::Create();		
		$cmd = $this->FreePBX->Database()->fetchSqlCommand();

		if(posix_isatty(STDIN)) {
			$process = new Process($cmd);
			$process->setTty(true);
		} else {
			$process = new Process($cmd.' -e '.escapeshellarg(fgets(STDIN)));
		}

		/* setting the mysql process timeout to 60 minutes */
		$process->setTimeout(3600);

		$process->mustRun();
		if(!posix_isatty(STDIN)) {
			echo $process->getOutput();
		}
	}
}
