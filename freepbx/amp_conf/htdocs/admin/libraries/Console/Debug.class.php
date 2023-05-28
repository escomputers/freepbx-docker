<?php
namespace FreePBX\Console\Command;
//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
//For running system commands.
use Symfony\Component\Process\Process;
//Tables
use Symfony\Component\Console\Helper\Table;
//Kill output buffering
@ini_set('output_buffering',0);
@ini_set('implicit_flush',1);

class Debug extends Command {
	protected function configure(){
		$this->FreePBXConf = \FreePBX::Config();
		$this->Notifications = \FreePBX::Notifications();
		$this->setName('debug')
		->setAliases(array('dbug'))
		->setDescription(_('Stream files for debugging'))
		->setDefinition(array(
			new InputArgument('args', InputArgument::IS_ARRAY, null, null),
			new InputOption('skipstandard', 's', InputOption::VALUE_NONE, _('Do not tail standard freepbx.log')),)
		);
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$this->FreePBXConf->set_conf_values(array('FPBXDBUGDISABLE' => 0),true,true);
		$DBUGFILE = $this->FreePBXConf->get('FPBXDBUGFILE');
		$FPBXLOGFILE = $this->FreePBXConf->get('FPBX_LOG_FILE');
		$user = $this->FreePBXConf->get('AMPASTERISKWEBUSER');
		$group = $this->FreePBXConf->get('AMPASTERISKWEBGROUP');
		touch($DBUGFILE);
		chown($DBUGFILE, $user);
		chgrp($DBUGFILE, $group);
		touch($FPBXLOGFILE);
		chown($FPBXLOGFILE, $user);
		chgrp($FPBXLOGFILE, $group);
		//Another hard coded list...
		$files = array(
			$DBUGFILE,
			'/var/log/httpd/error_log',
			'/var/log/asterisk/freepbx_security.log',
			);
		if (!$input->getOption('skipstandard')) {
			$files[] = $FPBXLOGFILE;
		}
		$table = new Table($output);
		$table->setHeaders(array('FreePBX Notifications'));
		$table->render();
		unset($table);
		foreach($this->Notifications->list_all() as $notice){
				if($notice['extended_text'] != strip_tags($notice['extended_text'])) {
					//breaks in the console make me sad.
					$longtext = preg_replace('#<br\s*/?>#i', PHP_EOL, $notice['extended_text']);
					$output->write($longtext);
				}else{
					$output->writeln('');
					$output->writeln($notice['extended_text']);
				}
		}
		$files = implode(' ', $files);
		//passthru('tail -f ' . $files);
		$process = new Process('tail -f ' . $files);
		//Timeout for the above process. Not sure if there is a no limit but 42 Years seems long enough.
		$process->setTimeout(1325390892);
		$process->run(function ($type, $buffer) {
			if (Process::ERR === $type) {
				echo 'ERR > '.$buffer;
			} else {
				echo 'OUT > '.$buffer;
			}
		});
	}
}
