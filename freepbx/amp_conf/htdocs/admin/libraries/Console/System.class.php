<?php
namespace FreePBX\Console\Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Command\LockableTrait;
class System extends Command {
	use LockableTrait;

	private $emailbody = [];
	private $sendemail = false;
	private $sysUpdate = false;
	private $updatemanager = false;

	public function __destruct() {
		$this->endOfLife();
	}

	private function endOfLife() {
		if(!$this->sendemail || empty($this->emailbody)) {
			return;
		}

		$brand = \FreePBX::Config()->get('DASHBOARD_FREEPBX_BRAND');
		$ident = \FreePBX::Config()->get('FREEPBX_SYSTEM_IDENT');
		// We are sending an email.
		$body = array_merge([
			sprintf(_("This is an automatic notification from your %s (%s) server."), $brand, $ident),
			"",
		], $this->emailbody);

		// Note this is force = true, as we always want to send it.
		$this->updatemanager->sendEmail("systemautoupdates", sprintf(_("%s (%s) System Updates"), $brand, $ident), implode("\n", $body), 4, true);
	}

	protected function configure(){
		$this->setName('system')
		->setAliases(array('sysup','sys','systemupdate'))
		->setDescription('System Update Administration')
		->setDefinition(array(
			new InputOption('sendemail', '', InputOption::VALUE_NONE, _('Send out finalized email')),
			new InputArgument('args', InputArgument::IS_ARRAY, 'arguments passed to system update admin, this is s stopgap', null)
		));
	}

	protected function execute(InputInterface $input, OutputInterface $output){
		if (!$this->lock()) {
			$output->writeln('The command is already running in another process.');
			return 0;
		}
		if($input->getOption('sendemail')) {
			$this->sendemail = true;
		}
		$this->updatemanager = new \FreePBX\Builtin\UpdateManager();
		$this->sysUpdate = new \FreePBX\Builtin\SystemUpdates(true);
		if(!$this->sysUpdate->canDoSystemUpdates()) {
			$output->writeln("<error>Sorry system updates can not be performed on this machine</error>");
			exit();
		}
		if($this->sysUpdate->isYumRunning()) {
			$output->writeln("<error>Sorry system updates are currently running as another user. Please try again later</error>");
			exit();
		}

		$args = $input->getArgument('args');
		if(!empty($args)){
			try {
				$this->handleArgs($args,$output);
			} catch(\Exception $e) {
				//run our last minute commands as they wont run later
				$this->endOfLife();
				throw $e;
			}
		}
	}

	private function handleArgs($args,$output){
		$action = array_shift($args);
		switch($action){
			case "listonline":
				$output->writeln("Checking for system updates");
				$this->sysUpdate->startCheckUpdates();
				$progress = new ProgressBar($output);
				$progress->setFormat('[%bar%] %elapsed:6s% %message%');
				$updates = $this->sysUpdate->getPendingUpdates();
				$progress->setMessage($updates['i18nstatus']);
				$progress->start();
				sleep(1);
				$updates = $this->sysUpdate->getPendingUpdates();
				$unknownCount = 0;
				$unknownMaxCount = 30; //we only allow max 30 seconds of unknowns
				while(in_array($updates['status'],["inprogress","unknown"]) && $unknownCount < $unknownMaxCount) {
					$updates = $this->sysUpdate->getPendingUpdates();
					$progress->setMessage($updates['i18nstatus']);
					$progress->advance();
					if($updates['status'] == "unknown") {
						$unknownCount++;
					}
					sleep(1);
				}
				$progress->setMessage("");
				$progress->finish();
				$output->writeln("");
				$rows = [];
				if($updates['status'] !== "complete") {
					$line = _("RPM(s) upgrade check had errors:");
					$this->addToEmail($line);
					$this->emailbody = is_array($updates['currentlog']) ? $updates['currentlog'] : [];
					$output->writeln("<error>".$updates['i18nstatus']."\n".implode("\n",$updates['currentlog'])."</error>");
					exit();
				}
				if(!empty($updates['rpms'])) {
					$line = _("RPM(s) requiring upgrades:");
					$this->addToEmail($line);
					$output->writeln($line);
					foreach($updates['rpms'] as $rpmname => $rpm) {
						if(!empty($updates['pbxupdateavail']['name']) && $updates['pbxupdateavail']['name'] == $rpmname) {
							// Make it stand out as a major upgrade
							array_unshift($rows,['<options=bold>'.$rpmname.'</>', '<options=bold>'.$rpm['currentversion'].'</>', '<options=bold>'.$rpm['newvers'].'</>', '<options=bold>'.$rpm['repo'].'</>']);
						} else {
							$rows[] = [$rpmname, $rpm['currentversion'], $rpm['newvers'], $rpm['repo']];
						}
						$this->addToEmail("${rpmname} ${rpm['newvers']} (current: ${rpm['currentversion']})");
					}
					$table = new Table($output);
					$table
						->setHeaders(array('RPM Name', 'Current Version', 'New Version', 'Repo'))
						->setRows($rows);
					$table->render();
				} else {
					$output->writeln(_("No RPMs need to be updated"));
				}
			break;
			case "installall":
			case "upgradeall":
				$output->writeln("Attempting to update system");
				$this->sysUpdate->startYumUpdate();
				$progress = new ProgressBar($output);
				$progress->setFormat('[%bar%] %elapsed:6s% %message%');
				$updates = $this->sysUpdate->getYumUpdateStatus();
				$progress->setMessage($updates['i18nstatus']);
				$progress->start();
				sleep(1);
				$updates = $this->sysUpdate->getYumUpdateStatus();
				$unknownCount = 0;
				$unknownMaxCount = 30; //we only allow max 30 seconds of unknowns
				while(in_array($updates['status'],["inprogress","unknown"]) && $unknownCount < $unknownMaxCount) {
					$updates = $this->sysUpdate->getYumUpdateStatus();
					$progress->setMessage($updates['i18nstatus']);
					$progress->advance();
					if($updates['status'] == "unknown") {
						$unknownCount++;
					}
					sleep(1);
				}
				$progress->setMessage("");
				$progress->finish();
				$output->writeln("");
				$rows = [];
				if($updates['status'] !== "complete") {
					$line = _("RPM(s) upgrade check had errors:");
					$this->addToEmail($line);
					$this->emailbody = array_merge($this->emailbody,(is_array($updates['currentlog']) ? $updates['currentlog'] : []));
					$output->writeln("<error>".$updates['i18nstatus']."</error>");
					exit();
				} else {
					$line = _("System update check completed. See log below:");
					$this->addToEmail($line);
					$this->emailbody = array_merge($this->emailbody,(is_array($updates['currentlog']) ? $updates['currentlog'] : []));
					$output->writeln(implode("\n",is_array($updates['currentlog']) ? $updates['currentlog'] : []));
				}
				$output->writeln("Finished!");
			break;
		}
	}

	/**
	 * Capture text for sending via email
	 *
	 * This is used when --sendemail is set.
	 *
	 * @param string $line
	 */
	private function addToEmail($line) {
		$this->emailbody[] = $line;
	}
}