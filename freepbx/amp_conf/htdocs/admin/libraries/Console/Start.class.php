<?php
//Namespace should be FreePBX\Console\Command
namespace FreePBX\Console\Command;

//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
//progress bar
use Symfony\Component\Console\Helper\ProgressBar;
//Process
use Symfony\Component\Process\Process;

class Start extends Command {
	private $booted = false;
	public $skipChown = false;

	protected function configure(){
		$this->setName('start')
			->setDescription(_('Start Asterisk and run other needed FreePBX commands'))
			->addArgument('args', InputArgument::IS_ARRAY, _('Module names'))
			->addOption('pre', null, InputOption::VALUE_NONE, _('Only run pre-start hooks'))
			->addOption('post', null, InputOption::VALUE_NONE, _('Only run post-start hooks'))
			->addOption('skipchown', null, InputOption::VALUE_NONE, _('Skip Chowning'))
			->setHelp($this->showHelp());
	}

	protected function execute(InputInterface $input, OutputInterface $output){
		if(posix_geteuid() != 0) {
			$output->writeln("<error>You need to be root to run this command</error>");
			exit(1);
		}
		$options = $input->getOptions();
		$this->skipChown = $options['skipchown'] ? true : $this->skipChown;
		$args = $input->getArgument('args');
		$pre = $this->preAsteriskHooks($output,false);
		$post = $this->postAsteriskHooks($output,false);
		$aststat = $this->asteriskProcess();
		$asteriskrunning = !empty($aststat);
		$bmo = \FreePBX::create();

		// We were asked to only run the pre-start hooks?
		if ($options['pre']) {
			// Note: Do not i18n.
			$output->writeln("Only running pre-hooks");
			$runpre = "force";
			$startasterisk = false;
			$runpost = false;
		} elseif ($options['post']) {
			// Note: Do not i18n.
			$output->writeln("Only running post-hooks");
			$runpre = false;
			$startasterisk = false;
			$runpost = "force";
		} else {
			$runpre = true;
			$startasterisk = true;
			$runpost = true;
		}

		// Do we have any params?
		if ($args) {
			// We do. Create a temporary array with our hooks, using the ones
			// we've been asked to do.
			$newpre = array();
			$newpost = array();
			$startasterisk = false;
			foreach ($args as $v) {
				if ($runpre) {
					foreach ($pre as $pri => $data) {
						if(strtolower($data['module']) == strtolower($v)) {
							$newpre[$pri] = $data;
						}
					}
				}
				if ($runpost) {
					foreach ($post as $pri => $data) {
						if(strtolower($data['module']) == strtolower($v)) {
							$newpost[$pri] = $data;
						}
					}
				}
				if(strtolower($v) == "ucp" && !\FreePBX::Config()->get("NODEJSENABLED")){
					$output->writeln('<error>'._("UCP Node Disabled in Advanced Settings.").'</error>');
					exit(0);
				}
			}

			// And overwrite our hooks to run later
			$pre = $newpre;
			$post = $newpost;
		}

		if ($startasterisk && $asteriskrunning) {
			$output->writeln("<info>Asterisk already running</info>");
		}

		// Now we're ready to go.
		$brand = \FreePBX::Config()->get("DASHBOARD_FREEPBX_BRAND");
		$output->writeln(sprintf(_('Running %s startup...'),$brand));
		if ($runpre) {
			if ($runpre !== "force") {
				if(!$this->skipChown){
					try {
						$chown = new Chown();
						$chown->execute($input, $output);
					} catch(\Exception $e) {
						$output->writeln('<error>'.$e->getMessage().'</error>');
					}
				}
			}

			if ($aststat) {
				$output->writeln(sprintf(_('Unable to run Pre-Asterisk hooks, because Asterisk is already running on PID %s and has been running for %s'),$aststat, $this->asteriskUptime()));
				$startasterisk = false;
			} else {
				foreach($pre as $pri => $data) {
					$output->writeln(sprintf(_("Running Asterisk pre from %s module"),$data['module']));
					try {
						$bmo->{$data['module']}->{$data['method']}($output);
					} catch(\Exception $e) {
						$output->writeln('<error>'.$e->getMessage().'</error>');
					}
				}
			}
		}

		if ($startasterisk) {
			if ($this->startAsterisk($output)) {
				$output->writeln('');
				$output->writeln(_("Asterisk Started"));
			} else {
				$output->writeln('');
				$output->writeln('<error>'._("Unable to start Asterisk!").'<.error>');
				exit(-1);
			}
		}

		if ($runpost) {
			foreach($post as $pri => $data) {
				$output->writeln(sprintf(_("Running Asterisk post from %s module"),$data['module']));
				try {
					$module = $data['module'];
					$method = $data['method'];
					$bmo->$module->$method($output);
				} catch(\Exception $e) {
					$output->writeln('<error>'.$e->getMessage().'</error>');
				}
			}
		}
	}

	private function asteriskProcess() {
		$pid = `/usr/bin/env pidof asterisk`;
		return trim($pid);
	}

	private function asteriskUptime() {
		$uptime = `/usr/bin/env asterisk -rx 'core show uptime' | grep uptime`;
		if (!preg_match('/System uptime:(.+)/', $uptime, $out)) {
			return "ERROR";
		}
		return trim($out[1]);
	}

	private function startAsterisk($output){
		$output->writeln(_('Starting Asterisk...'));
		$astbin = '/usr/bin/env safe_asterisk -U '.\FreePBX::Config()->get('AMPASTERISKUSER').' -G '.\FreePBX::Config()->get('AMPASTERISKGROUP').' > /dev/null 2>&1 &';

		$process = new Process($astbin);
		$env = $this->getDefaultEnv();
		if(empty($env['TERM'])) {
			$env['TERM'] = 'xterm-256color';
		}
		$process->setEnv($env);
		try {
			$process->mustRun();
		} catch (ProcessFailedException $e) {
			throw new \Exception($e->getMessage());
		}
		$astman = \FreePBX::create()->astman;
		$progress = new ProgressBar($output, 0);
		$progress->setFormat('[%bar%] %elapsed%');
		$progress->start();
		$i = 0;
		while(!$this->asteriskIsReady()) {
			$astman->reconnect('on');
			usleep(300000); //0.3 seconds in microseconds, which when multiplied by 100 will wait up to 30 seconds
			$i++;
			if($i >= 100) {
				throw new \Exception("Unable to connect to Asterisk. Did it start?");
			}
			$progress->setProgress($i);
		}

		$astman->add_event_handler("FullyBooted", array($this, "fullyBooted"));
		while(!$this->booted) {
			$astman->wait_response(true,true);
			$i++;
			if($i >= 1000) { //should this be 1000??
				$this->booted = true;
				break; //we never got the fully booted response? But let's continue anyways.
			}
			$progress->setProgress($i);
		}
		$progress->finish();
		return true;
	}

	public function fullyBooted() {
		$this->booted = true;
	}

	private function preAsteriskHooks($output,$execute=true){
		if(!$execute) {
			return \FreePBX::Hooks()->returnHooks();
		}
		\FreePBX::Hooks()->processHooks($output);
		return;
	}
	private function postAsteriskHooks($output,$execute=true){
		if(!$execute) {
			return \FreePBX::Hooks()->returnHooks();
		}
		\FreePBX::Hooks()->processHooks($output);
		return;
	}

	private function asteriskIsReady() {
		$aststat = $this->asteriskProcess();
		if (empty($aststat)) {
			return false;
		}

		$astman = \FreePBX::create()->astman;
		return $astman->connected();
	}


	private function showHelp() {
		$help = "<info>"._('Usage').": fwconsole start [--pre|--post|--skipchown] [modulename] [modulename...]</info>".PHP_EOL;
		$options = array(
			"--pre" => _("Force run pre-start asterisk hooks"),
			"--post" => _("Force run post-start asterisk hooks"),
			"--skipchown" => _("Skip Chowning of files"),
		);
		foreach ($options as $o => $t) {
			$help .= "<info>$o</info> : <comment>$t</comment>".PHP_EOL;
		}

		return $help;
	}

	//thanks symfony console
	private function getDefaultEnv() {
		$env = array();

		foreach ($_SERVER as $k => $v) {
			if (is_string($v) && false !== $v = getenv($k)) {
				$env[$k] = $v;
			}
		}

		foreach ($_ENV as $k => $v) {
			if (is_string($v)) {
				$env[$k] = $v;
			}
		}

		return $env;
	}
}
