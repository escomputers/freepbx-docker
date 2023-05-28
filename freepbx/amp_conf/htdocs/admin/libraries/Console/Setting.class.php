<?php
namespace FreePBX\Console\Command;
//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

//la mesa
use Symfony\Component\Console\Helper\Table;

use Symfony\Component\Console\Command\HelpCommand;

use Respect\Validation\Validator as v;

class Setting extends Command {
	protected function configure(){
		$this->FreePBXConf = \FreePBX::Config();
		$this->setName('setting')
		->setAliases(array('set'))
		->setDescription(_('View and update settings. Usage: fwconsole setting <keyword> or fwconsole setting <keyword> <newvalue>'))
		->setDefinition(array(
			new InputOption('list', 'l', InputOption::VALUE_NONE, _('List Configs')),
			new InputOption('reset', 'r', InputOption::VALUE_NONE, _('Reset to default')),
			new InputOption('import', 'i', InputOption::VALUE_REQUIRED, _('Import settings from file')),
			new InputOption('export', 'e', InputOption::VALUE_REQUIRED, _('Export settings to file')),
			new InputArgument('args', InputArgument::IS_ARRAY, _('<keyword> [<value>]'), null),));
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$args = $input->getArgument('args');
		if ($input->getOption('export')){
			$filename = $input->getOption('export');
			$confdump = $this->FreePBXConf->get_conf_settings();
			$confarray = array();
			$showro = $this->FreePBXConf->get('AS_DISPLAY_READONLY_SETTINGS');
			foreach ($confdump as $key => $val){
				if(isset($val['hidden']) && $val['hidden']) {
					continue;
				}
				if(isset($val['readonly']) && $val['readonly'] && !$showro) {
					continue;
				}
				$confarray[$key] = $val['value'];
			}
			$configjson = json_encode($confarray);
			$fs = new Filesystem();
			try {
				$fs->dumpFile($filename,$configjson);
				return true;
			} catch (IOExceptionInterface $e){
				$output->writeln(sprintf(_("Could not write to %s"),$filename));
				return false;
			}
			return;
		}
		if ($input->getOption('import')){
			$filename = $input->getOption('import');
			$settings = json_decode(file_get_contents($filename));
			foreach($settings as $key => $val){
				if($this->FreePBXConf->conf_setting_exists($key)){
					$this->changeConfSetting($key, $val, $input, $output);
				}else{
					$output->writeln(sprintf(_('The setting %s was not found!'),$key));
				}
			}
			return;
		}
		if ($input->getOption('list')){
			$conf = $this->FreePBXConf->get_conf_settings();
			$table = new Table($output);
			$table->setHeaders(array(_('Name'),_('Value'),_('Default Value')));
			$rows = array();
			$showro = $this->FreePBXConf->get('AS_DISPLAY_READONLY_SETTINGS');
			foreach ($conf as $key => $val){
				if(isset($val['hidden']) && $val['hidden']) {
					continue;
				}
				if(isset($val['readonly']) && $val['readonly'] && !$showro) {
					continue;
				}
				$rows[] = array(
					$key,
					$val['value'],
					isset($val['defaultval']) ? $val['defaultval'] : $val['value']
				);
			}
			$table->setRows($rows);
			$table->render();
			return;
		}
		if($input->getOption('reset')){
			if($args){
				foreach($args as $arg){
					$helper = $this->getHelper('question');
					$question = new ConfirmationQuestion('<question>'.sprintf(_('Are you sure you want to set %s to its default?'),$arg).'</question>', true);
					if($helper->ask($input,$output,$question)){
						if($this->FreePBXConf->conf_setting_exists($arg)){
							$default = $this->FreePBXConf->get_conf_default_setting($arg);
							$output->writeln('Changing ' . $arg . ' to ' . $default);
							$this->FreePBXConf->set_conf_values(array($arg => $default),true,true);
						}else{
							$output->writeln(sprintf(_('The setting %s was not found!'),$arg));
						}
					}else{
						$output->writeln(sprintf(_('Current setting for %s left in place'),$arg));
					}
				}
			}
			return;
		}

		if(isset($args[0])) {
			$setting = trim($args[0]);
			if($this->FreePBXConf->conf_setting_exists($setting)){
				$info = $this->FreePBXConf->conf_setting($setting);
				if(!isset($args[1])) {
					$old = $this->FreePBXConf->get($setting);
					switch($info['type']) {
						case CONF_TYPE_BOOL:
							$old = !empty($old) ? 'true' : 'false';
						break;
					}
					$output->writeln(sprintf(_('Setting of "%s" is (%s)[%s]'),$setting,$info['type'],$old));
				} else {
					$this->changeConfSetting($setting, $args[1], $input, $output);
				}
			}else{
				$output->writeln(sprintf(_('The setting %s was not found!'),$setting));
			}
			return;
		}

		$this->outputHelp($input,$output);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 * @throws \Symfony\Component\Console\Exception\ExceptionInterface
	 */
	protected function outputHelp(InputInterface $input, OutputInterface $output)	 {
		$help = new HelpCommand();
		$help->setCommand($this);
		return $help->run($input, $output);
	}

	private function changeConfSetting($setting, $value, InputInterface $input, OutputInterface $output) {
		$info = $this->FreePBXConf->conf_setting($setting);
		$value = trim($value);
		$old = $this->FreePBXConf->get($setting);
		switch($info['type']) {
			case CONF_TYPE_BOOL:
				$old = !empty($old) ? '1' : '0';
				$value = strtolower($value);
				if(!v::trueVal()->validate($value) && !v::falseVal()->validate($value)) {
					throw new \Exception(sprintf(_("Invalid value for %s, needs to be one of 'on', 'off', 'true', 'false', '1' or '0'"),$setting));
				}
				$value = ($value === 'on' || $value === true || $value === 'true' || $value === 1 || $value === '1') ? true : false;
				$text = $value ? '1' : '0';
			break;
			default:
				$text = $value;
			break;
		}
		$output->writeln(sprintf(_('Changing "%s" from [%s] to [%s]'),$setting,$old,$text));
		$this->FreePBXConf->set_conf_values(array($setting => $value),true,true);
		$last = $this->FreePBXConf->get_last_update_status();
		if(!empty($last[$setting]) && !$last[$setting]['validated']) {
			$output->writeln("<error>".$last[$setting]['msg']."</error>");
		}
	}
}
