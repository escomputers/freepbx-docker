<?php
namespace FreePBX\Console\Command;
//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Unlock extends Command {
	protected function configure(){
		$this->FreePBXConf = \FreePBX::Config();
		$this->setName('unlock')
		->setDescription(_('Unlock Session'))
		->setDefinition(array(
			new InputArgument('args', InputArgument::IS_ARRAY, null, null),));
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$sp = session_save_path();
		if(empty($sp)) {
			$sp = "/var/lib/php/session"; //hard coded but we warn below!
			$output->writeln("<error>".sprintf(_("Session save path is undefined. This can cause undefined unlocks. Please set a 'session.save_path' in your php.ini file. It should match the same path that is set for the web portion of PHP. We have defaulted to [%s]"),$sp)."</error>");
			ini_set("session.save_path",$sp);
		}
		$FreePBX = \FreePBX::Create();
		$args = $input->getArgument('args');
		$file = $sp."/sess_".$args[0];
		//If we don't have a session file, it is probably not a valid session
		if(!file_exists($file)){
			$output->writeln(sprintf(_('Unlocking: %s Failed, Invalid session'),$args[0]));
			return;
		} else {
			unlink($file); //PHP gets pissy when I try to edit a session that's not mine
		}
		session_id($args[0]);
		session_start();
		$output->writeln(sprintf(_('Unlocking: %s'),$args[0]));
		if (!isset($_SESSION["AMP_user"])) {
			$_SESSION["AMP_user"] = new \ampuser('fwconsole');
			$_SESSION["AMP_user"]->setAdmin();
			$output->writeln(_('Session Should be unlocked now'));
		}
		session_write_close();
		chown($file,$this->FreePBXConf->get("AMPASTERISKWEBUSER"));
		chgrp($file,$this->FreePBXConf->get("AMPASTERISKWEBGROUP"));
	}
}
