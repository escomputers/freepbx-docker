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

class Stop extends Command {
	protected function configure(){
		$this->setName('stop')
			->setDescription(_('Stop Asterisk and run other needed FreePBX commands'))
			->addOption('pre', null, InputOption::VALUE_NONE, _('Only run pre-stop hooks'))
			->addOption('post', null, InputOption::VALUE_NONE, _('Only run post-stop hooks'))
			->addOption('immediate', 'i', InputOption::VALUE_NONE, _('Shutdown NOW rather than convieniently'))
			->addOption('maxwait', 'm', InputOption::VALUE_OPTIONAL, _('Maximum time (in seconds) to wait for asterisk to stop gracefully. Default 30 seconds'))
			->addArgument('args', InputArgument::IS_ARRAY, _('Module names'))
			->setHelp($this->showHelp());
	}

	protected function execute(InputInterface $input, OutputInterface $output){
		if(posix_geteuid() != 0) {
			$output->writeln("<error>"._("You need to be root to run this command")."</error>");
			exit(1);
		}
		$options = $input->getOptions();
		$args = $input->getArgument('args');
		$pre = $this->preAsteriskHooks($output,false);
		$post = $this->postAsteriskHooks($output,false);
		$aststat = $this->asteriskProcess();
		$bmo = \FreePBX::create();
		$maxwait = (int) $options['maxwait'];
		if ($maxwait < 5) {
			$maxwait = 30;
		}
		if ($maxwait > 3600) { // 1 hour
			$maxwait = 3600;
		}

		// We were asked to only run the pre-stop hooks?
		if ($options['pre']) {
			// Note: Do not i18n.
			$output->writeln("Only running pre-hooks");
			$runpre = true;
			$stopasterisk = false;
			$runpost = false;
		} elseif ($options['post']) {
			// Note: Do not i18n.
			$output->writeln("Only running post-hooks");
			$runpre = false;
			$stopasterisk = false;
			$runpost = true;
		} else {
			// Run both
			$runpre = true;
			$stopasterisk = true;
			$runpost = true;
		}

		// Do we have any params?
		if ($args) {
			// We do. Create a temporary array with our hooks, using the ones
			// we've been asked to do.
			$stopasterisk = false;
			$newpre = array();
			$newpost = array();
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
			}

			// And overwrite our hooks to run later
			$pre = $newpre;
			$post = $newpost;
		}

		if ($stopasterisk && empty($aststat)) {
			$output->writeln("<error>Asterisk not currently running</error>");
			$stopasterisk = false;
		}

		// Now we're ready to go.
		$brand = \FreePBX::Config()->get("DASHBOARD_FREEPBX_BRAND");

		$output->writeln(sprintf(_('Running %s shutdown...'),$brand));
		$output->writeln('');

		if ($runpre) {
			foreach($pre as $pri => $data) {
				$output->writeln(sprintf(_("Running Asterisk pre from %s module"),$data['module']));
				try {
					$module = $data['module'];
					$method = $data['method'];
					$bmo->$module->$method($output);
				} catch(\Exception $e) {
					$output->writeln('<error>'.$e->getMessage().'</error>');
				}
			}
		}

		if ($stopasterisk) {
			$astman = \FreePBX::create()->astman;
			$astman->disconnect();
			if ($options['immediate']) {
				$output->writeln(_('Shutting down Asterisk Immediately...'));
				$this->stopAsterisk($output, 'now');
			} else {
				$output->writeln(sprintf(_('Shutting down Asterisk Gracefully. Will forcefully kill after %s seconds.'), $maxwait));
				// Let people force the shutdown if they want
				$output->writeln(sprintf(_('Press %s to Cancel'),'C'));
				$output->writeln(sprintf(_('Press %s to shut down NOW'),'N'));
				// Wait for up to $maxwait before killing it hard
				$killafter = time() + $maxwait;
				$starttime = time();

				// Seconds may have ticked over between the two time() calls, which is why
				// we recalculate.
				$pct = 100/($killafter - $starttime);

				if (!$output->isQuiet()) {
					stream_set_blocking(STDIN,0);
					$term = `stty -g`;
					system("stty -icanon -echo");
				}

				$progress = new ProgressBar($output, 0);
				$progress->setFormat('[%bar%] %elapsed%');
				$this->stopAsterisk($output, 'gracefully');
				$progress->start();
				$isrunning = true;

				while ( time() < $killafter ) {
					if (!$output->isQuiet()) {
						$res = fread(STDIN,1);
						if ($res) {
							if (strtolower($res) === "c") {
								$progress->finish();
								print "\n";
								$output->writeln(_('Aborting Shutdown. Asterisk is still running'));
								$this->abortShutdown($output);
								system("stty $term");
								exit(1);
							} elseif (strtolower($res) === "n") {
								print "\n";
								$output->writeln(_('Killing asterisk forcefully.'));
								$this->stopAsterisk($output, 'now');
							}
						}
					}
					$current =  (int) (time() - $starttime) * $pct;
					$progress->setProgress($current);
					$aststat = $this->asteriskProcess();
					if (empty($aststat)) {
						$progress->setProgress(100);
						$isrunning = false;
						break;
					}
					fflush(STDOUT);
					usleep(10000);
				}

				$progress->finish();
				// Re-block the stream
				if (!$output->isQuiet()) {
					stream_set_blocking(STDIN,1);
					system("stty $term");
				}

				if ($isrunning) {
					$output->writeln("");
					$output->writeln(_('Killing asterisk forcefully.'));
					$this->stopAsterisk($output, 'now');
					$aststat = $this->asteriskProcess();
					if(!empty($aststat)) {
						//OK it still hasnt stopped... geesh
						$this->stopAsterisk($output, 'force');
						sleep(1);
						$aststat = $this->asteriskProcess();
						if(!empty($aststat)) {
							$output->writeln(_("Asterisk is still running and we can't stop it!"));
							exit(1);
						}
					}
				}
			}
		}
		$output->writeln("");

		if ($runpost) {
			foreach($post as $pri => $data) {
				$bmo->{$data['module']}->{$data['method']}($output);
			}
		}
	}

	private function asteriskProcess() {
		$pid = `/usr/bin/env pidof asterisk`;
		return trim($pid);
	}

	private function stopAsterisk($output, $method){
		switch($method) {
			case "force":
				//not sure if this is needed
				$sastbin = '/usr/bin/env killall safe_asterisk > /dev/null 2>&1';
				exec($sastbin);

				$sastbin = '/usr/bin/env killall asterisk > /dev/null 2>&1';
				exec($sastbin);
			break;
			case "now":
				$sastbin = '/usr/bin/env killall safe_asterisk > /dev/null 2>&1';
				exec($sastbin);
			case "gracefully":
				$astbin = '/usr/bin/env asterisk -rx "core stop ' . $method .'" &>/dev/null &';
				shell_exec($astbin);
			break;
		}
	}
	private function abortShutdown($output){
		$freepbx = \FreePBX::Create();
		$astman = $freepbx->astman;
		if (is_object($astman) && $astman->Connected()) {
			$astman->send_request('Command',array('Command'=>'core abort shutdown'));
		}
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

	private function showHelp() {
		$help = "<info>"._('Usage').": fwconsole stop [--immediate|--pre|--post] [-m..|--maxwait=..] [modulename] [modulename...]</info>".PHP_EOL;
		$options = array(
			"--immediate" => _("Run an immediate shutdown. Defaults to 'when convenient'"),
			"--pre" => _("Force run pre-stop asterisk hooks"),
			"--post" => _("Force run post-stop asterisk hooks"),
			"--maxwait" => _("Maximum amount of time (in seconds) to wait for asterisk to shut down"),
		);
		foreach ($options as $o => $t) {
			$help .= "<info>$o</info> : <comment>$t</comment>".PHP_EOL;
		}

		return $help;
	}
}
