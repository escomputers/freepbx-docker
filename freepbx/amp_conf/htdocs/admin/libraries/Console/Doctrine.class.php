<?php
namespace FreePBX\Console\Command;
//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Synchronizer\SingleDatabaseSynchronizer;
use Doctrine\DBAL\Schema\Schema;
class Doctrine extends Command {
	private $format = 'php';
	private $database = '';
	protected function configure(){
		$this->setName('doctrine')
		->setDescription('Run a doctrine commands')
		->setDefinition(array(
			new InputOption('database', '', InputOption::VALUE_REQUIRED, _("Database name")),
			new InputOption('format', '', InputOption::VALUE_REQUIRED, sprintf(_('Format can be: %s'),'xml,php')),
			new InputOption('force', '', InputOption::VALUE_NONE, _('Force XML/PHP output even if there is an alter string after generation checks')),
			new InputArgument('args', InputArgument::IS_ARRAY, null, null),));
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$args = $input->getArgument('args');
		$dbh = \FreePBX::Database();
		if(empty($args[0])) {
			$output->writeln('<error>You MUST declare a table name!</error>');
			return;
		}
		$table = $args[0];

		$db = $input->getOption('database');
		if(!empty($db)) {
			$this->database = $db;
		}

		switch($input->getOption('format')) {
			case "xml":
				$this->format = 'xml';
			break;
			case "php":
			default:
			break;
		}

		$table = \FreePBX::Database()->migrate($args[0]);
		$generate = $table->generateUpdateArray();

		switch($this->format) {
			case "xml":
				$xml = new \SimpleXMLElement('<database/>');
				if(!empty($this->database)) {
					$xml->addAttribute('name', $this->database);
				}
				$table = $xml->addChild("table");
				$table->addAttribute('name', $args[0]);
				foreach($generate['columns'] as $col => $data) {
					$c = $table->addChild("field");
					$c->addAttribute('name', $col);
					$c->addAttribute('type', $data['type']);

					if(isset($data['length'])) {
						$c->addAttribute('length', $data['length']);
					}

					if(isset($data['default'])) {
						$c->addAttribute('default', $data['default']);
					}

					if(isset($data['unsigned'])) {
						$c->addAttribute('unsigned', $data['unsigned'] ? 'true' : 'false');
					}

					if(isset($data['notnull'])) {
						$c->addAttribute('notnull', $data['notnull'] ? 'true' : 'false');
					}

					if(isset($data['primaryKey']) || isset($data['primarykey'])) {
						$c->addAttribute('primarykey', $data['primarykey'] ? 'true' : 'false');
					}

					if(isset($data['autoincrement'])) {
						$c->addAttribute('autoincrement', $data['autoincrement'] ? 'true' : 'false');
					}
				}
				foreach($generate['indexes'] as $index => $data) {
					$i = $table->addChild("key");
					$i->addAttribute('name', $index);
					$i->addAttribute('type', $data['type']);
					foreach($data['cols'] as $col) {
						$c = $i->addChild("column");
						$c->addAttribute('name', $col);
					}
				}
				$domxml = new \DOMDocument('1.0');
				$domxml->preserveWhiteSpace = false;
				$domxml->formatOutput = true;
				$domxml->loadXML($xml->asXML());

				$string = $domxml->saveXML();
				$string = str_replace('<?xml version="1.0"?>','',$string);
				$string = trim($string);

				$xml = simplexml_load_string($string);
				$test = \FreePBX::Database()->migrateMultipleXML($xml->table,true);
				if(!empty($test)) {
					if($input->getOption('force')) {
						$output->writeln("<error>Table did not accurately match generation.</error>");
						$output->writeln("<error>Modification string should be empty but returned: '".implode("; ",$test)."'.</error>");
						$output->writeln("<error>You **MAY** LOSE DATA!!!!</error>");
					} else {
						print_r("Cols");
						print_r($generate['columns']);
						print_r("Indexes");
						print_r($generate['indexes']);
						throw new \Exception("Error: Table did not accurately match generation. Aborting. Modification string should be empty but returned: '".implode("; ",$test)."'");
					}

				}
				$output->writeln($string);
			break;
			case "php":
				$table = \FreePBX::Database()->migrate($args[0]);
				$test = $table->modify($generate['columns'], $generate['indexes'], true);
				if(!empty($test)) {
					print_r("Cols");
					print_r($generate['columns']);
					print_r("Indexes");
					print_r($generate['indexes']);
					throw new \Exception("Error: Table did not accurately match generation. Aborting. Modification string should be empty but returned: '".implode("; ",$test)."'");
				}
				$output->writeln('$table = \FreePBX::Database()->migrate("'.$args[0].'");');
				$output->writeln('$cols = '.var_export($generate['columns'],true).';');
				$output->writeln(PHP_EOL);

				$output->writeln('$indexes = '.var_export($generate['indexes'],true).';');
				$output->writeln('$table->modify($cols, $indexes);');
				$output->writeln('unset($table);');
			break;
		}



	}
}
