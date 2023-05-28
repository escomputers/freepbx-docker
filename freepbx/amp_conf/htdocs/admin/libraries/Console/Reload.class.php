<?php
namespace FreePBX\Console\Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Console\Command\LockableTrait;

class Reload extends Command {
	use LockableTrait;

	private $freepbx;
	private $error;
	private $symlink_error_modules = "";
	private $symlink_notice_modules = "";
	private $symlink_notice_modules_bin = "";
	private $symlink_notice_modules_agi = "";
	private $cp_errors = "";

	protected function configure(){
		$this->messageBuffer = [];
		$this->errorBuffer = [];
		$this->freepbx = \FreePBX::create();
		$this->symlink_dirs['bin'] = $this->freepbx->Config->get('AMPBIN');
		$this->symlink_dirs['etc'] = $this->freepbx->Config->get('ASTETCDIR');
		$this->symlink_dirs['images'] = $this->freepbx->Config->get('AMPWEBROOT') . "/admin/images";
		$this->symlink_sound_dirs['sounds'] = $this->freepbx->Config->get('ASTVARLIBDIR') . '/sounds';
		//TODO agi-bin needs to be symlinked in the future
		$this->cp_dirs['agi-bin'] 	= $this->freepbx->Config->get('ASTAGIDIR');
		/** BIN IS SYMLINKED **/
		//$this->cp_dirs['bin'] = $this->freepbx->Config->get('AMPBIN');

		$this->setName('reload')
		->setAliases(array('r'))
		->setDescription(_('Reload Configs'))
		->setDefinition(array(
			new InputOption('json', null, InputOption::VALUE_NONE, _('Force JSON')),
			new InputOption('dry-run', null, InputOption::VALUE_NONE, _('Dry-run only, no files will be written and Asterisk will not be reloaded')),
			new InputOption('skip-registry-checks', null, InputOption::VALUE_NONE, _('Skip registry checks')),
			new InputOption('dont-reload-asterisk', null, InputOption::VALUE_NONE, _('Dont reload asterisk')),
		));
	}

	public function __destruct() {
		$lastError = error_get_last();
		$validErrors = [
			E_ERROR,
			E_CORE_ERROR,
			E_USER_ERROR,
			E_RECOVERABLE_ERROR
		];
		if(isset($this->error)) {
			$this->freepbx->Notifications->add_critical('freepbx','RCONFFAIL', _("'fwconsole reload' failed, config not applied"), $this->error);
		} elseif(is_array($lastError) && in_array($lastError['type'],$validErrors)) {
			$this->freepbx->Notifications->add_critical('freepbx','RCONFFAIL', _("'fwconsole reload' failed, config not applied"), $lastError['message']);
		} else {
			$this->freepbx->Notifications->delete('freepbx','RCONFFAIL');
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output){
		$this->input = $input;
		$this->output = $output;
		$this->json = $input->getOption('json');
		$this->dryrun = $input->getOption('dry-run');
		$this->skip_registry_checks = $input->getOption('skip-registry-checks');
		$this->dont_reload_asterisk = $input->getOption('dont-reload-asterisk');
		$this->setLock();
		if($this->json) {
			global $whoops;
			if ($whoops instanceof \Whoops\Run) {
				$whoops->unregister();
			}
			$this->getApplication()->setCatchExceptions(false);
			$errorHandler = \Symfony\Component\Debug\ErrorHandler::register(null, false);
			$errorHandler->throwAt(E_ALL, true);
			$errorHandler->setExceptionHandler(function ($e) {
				$this->removeLock();
				echo json_encode(["error" => $e->getMessage(), "trace" => $e->getTraceAsString()]);
				exit(-1);
			});
		}

		try {
			$this->reload();
			$this->removeLock();
		} catch(\Exception $e) {
			$this->removeLock();
			$this->error = $e->getMessage();
			throw $e;
		}
	}

	private function reload() {
		$this->writeln(_("Reload Started"));
		$this->freepbx->Hooks->processHooksByClassMethod('FreePBX\Reload', 'preReload');
		$this->runPreReloadScript();
		$this->checkMemoryLimits();
		$this->checkAsterisk();

		//Load Asterisk Manager into Memory
		$this->freepbx->astman->useCaching = true;
		$this->freepbx->Performance->Start("load_astdb");
		$this->freepbx->astman->LoadAstDB();
		$this->freepbx->Performance->Stop("load_astdb");

		//trust GPG keys
		$this->freepbx->Performance->Start("trust_gpg");
		$this->freepbx->GPG->trustFreePBX();
		$this->freepbx->Performance->Stop("trust_gpg");

		//Run HTML Format Checks
		$this->freepbx->Performance->Start("check_html5");
		$this->freepbx->Media->getSupportedHTML5Formats();
		$this->freepbx->Performance->Stop("check_html5");

		//Update BMO Hooks
		$this->freepbx->Performance->Start("retrieve_conf");
		$this->freepbx->Hooks->updateBMOHooks();

		//Putting the core module last, to move outbound-allroutes
		// last in from-internals-additional
		$active_modules = $this->freepbx->Modules->getActiveModules();
		if (isset($active_modules['core'])) {
			$core_tmp = $active_modules['core'];
			unset($active_modules['core']);
			$active_modules['core'] = $core_tmp;
		}

		$AMPWEBROOT = $this->freepbx->Config->get('AMPWEBROOT');
		$module_list = [];
		if(is_array($active_modules)){
			foreach($active_modules as $module => $data) {
				$module_list[] = $module;
				// create symlinks for files in appropriate sub directories
				// don't symlink framework files, it is a special case module
				// that happens to have some conflicting names
				//
				if (!isset($data['modtype']) || $data['modtype'] != 'framework') {
					// don't copy or symlink from framework type modules as they are not real modules
					$this->freepbx->Performance->Stamp("Symlinking files for ".$module);
					$this->symlink_subdirs( $AMPWEBROOT.'/admin/modules/'.$module );
					$this->symlink_sound_dirs( $AMPWEBROOT.'/admin/modules/'.$module );
					$this->symlink_assets($module);
					$this->freepbx->Performance->Stamp("Finished symlinking");

					$this->freepbx->Performance->Stamp("Copying Files for ".$module);
					$this->cp_subdirs( $AMPWEBROOT.'/admin/modules/'.$module );
					$this->freepbx->Performance->Stamp("Finished Copying Files");

					$this->freepbx->Performance->Stamp("Generating CSS from LESS for ".$module);
					$this->generate_less($module); //generate less for said module if needed
					$this->freepbx->Performance->Stamp("Finished Generating Less");
				}
			}
		}

		$this->freepbx->Performance->Start("Generating all compiled CSS files from less");
		$this->freepbx->Less->generateMainStyles();
		$this->freepbx->Performance->Stop();

		// Now that we have done all the symlinks and copies, we check and report if there were any errors
		//
		$this->symlink_check_errors();
		$this->cp_check_errors();

		//once we have all the connected files in place, lets compress the css
		if (!$this->freepbx->Config->get('DISABLE_CSS_AUTOGEN')) {
			compress_framework_css();
		}

		// create an object of the extensions class
		include $AMPWEBROOT."/admin/libraries/extensions.class.php";
		global $ext;
		$ext = new \extensions;

		if ($this->freepbx->Config->get('DISABLECUSTOMCONTEXTS')) {
			$ext->disableCustomContexts(true);
		}

		// create objects for any module classes
		// currently only 1 class can be declared per module, not sure if that will be an issue
		if(isset($module_list) && is_array($module_list)){
			foreach($module_list as $active_module) {
				$classname = $active_module."_conf";
				if(class_exists($classname)) {
					//TODO: This is deprecated TBH
					global ${$classname};
					${$classname} = new $classname;
				}
			}
		}

		//Get engine information
		$engineinfo = engine_getinfo();
		if($engineinfo['version'] == 0){
			fatal(sprintf(_("retreive_conf failed to get engine information and cannot configure up a softwitch with out it. Error: %s"),$engineinfo['engine']),true);
		}
		// was setting these variables before, assume we still need them
		$engine = $engineinfo['engine'];
		$version = $engineinfo['version'];
		$res_ver = IsAsteriskSupported($version); // method located in utility.function.php
		if ($res_ver["status"] == false) {
			fatal(sprintf(_("Running an unsupported version of Asterisk. %s Detected Asterisk version: %s "), $res_ver["message"], $version));
		}
		$chan_dahdi = ast_with_dahdi();

		// If BROWSER_STATS is set to true (default) and we have never provided a notice (NOTICE_BROWSER_STATS false) then do so one time only so
		// they are aware and can choose to opt out.
		if (!$this->freepbx->Config->get('NOTICE_BROWSER_STATS') && $this->freepbx->Config->get('BROWSER_STATS')) {
			$this->freepbx->Notifications->add_notice('framework', 'BROWSER_STATS', _("Collecting Anonymous Browser Stats"), _("The FreePBX project is collecting anonymous browser statistics using google analytics. These are used to focus development efforts based on real user input. All information is anonymous. You can disable this in Advanced Settings with the Browser Stats setting."));
			$this->freepbx->Config->update('NOTICE_BROWSER_STATS',true, true, true);
		}

		if (!$this->freepbx->Config->exists('AST_APP_VQA')) {
			// AST_APP_VQA
			//
			$set['value'] = '';
			$set['defaultval'] =& $set['value'];
			$set['options'] = '';
			$set['readonly'] = 1;
			$set['hidden'] = 1;
			$set['level'] = 10;
			$set['module'] = '';
			$set['category'] = 'Internal Use';
			$set['emptyok'] = 1;
			$set['name'] = 'Asterisk Application VQA';
			$set['description'] = "Set to the application name if the application is present in this Asterisk install";
			$set['type'] = CONF_TYPE_TEXT;
			$this->freepbx->Config->define_conf_setting('AST_APP_VQA',$set);
		}

		$this->freepbx->Config->update('AST_APP_VQA', $this->freepbx->astman->app_exists('VQA'), true, true);

		// Check for and report any extension conflicts
		//

		$extens_ok = true;
		$dests_ok = true;

		$my_hash = array_flip($module_list);
		$this->freepbx->Performance->Start("extenconflicts");
		$my_prob_extens = $this->skip_registry_checks ? false : $this->freepbx->Extensions->listExtensionConflicts();
		$this->freepbx->Performance->Stop();

		if (empty($my_prob_extens)) {
			$this->freepbx->Notifications->delete('retrieve_conf', 'XTNCONFLICT');
		} else {
			$previous = null;
			$str = null;
			$count = 0;
			foreach ($my_prob_extens as $extens) {
				foreach ($extens as $exten => $details) {
					if ($exten != $previous) {
						$str .=  _("Extension").": $exten:<br />";
						$count++;
					}
					$str .= sprintf("%8s: %s<br />",$details['status'], $details['description']);
					$previous = $exten;
				}
			}
			$this->freepbx->Notifications->add_error('retrieve_conf', 'XTNCONFLICT', sprintf(_("There are %s conflicting extensions"),$count), $str);
			$extens_ok = false;
		}

		// Check for and report any bogus destinations
		//
		$this->freepbx->Performance->Start("listproblems");
		$my_probs = $this->skip_registry_checks ? false : $this->freepbx->Destinations->listProblemDestinations(!$this->freepbx->Config->get('CUSTOMASERROR'));
		$this->freepbx->Performance->Stop();


		if (empty($my_probs)) {
			$this->freepbx->Notifications->delete('retrieve_conf', 'BADDEST');
		} else {
			$results = array();
			$count = 0;
			$str = null;
			foreach ($my_probs as $problem) {
				//print_r($problem);
				$results[$problem['status']][] = $problem['description'];
				$count++;
			}
			foreach ($results as $status => $subjects) {
				$str .= sprintf(_("DEST STATUS: %s%s"),$status,"\n");
				foreach ($subjects as $subject) {
					//$str .= $subject."<br />";
					$str .= "   ".$subject."\n";
				}
			}
			$this->freepbx->Notifications->add_error('retrieve_conf', 'BADDEST', sprintf(_("There are %s bad destinations"),$count), $str);
			$dests_ok = false;
		}

		if ((!$extens_ok && $this->freepbx->Config->get('XTNCONFLICTABORT')) || (!$dests_ok && $this->freepbx->Config->get('BADDESTABORT'))) {
			//was error 20
			throw new \Exception(_("Aborting reload because extension conflicts or bad destinations"));
		}

		// Generate an extension map of all extensions on the system
		$this->freepbx->Performance->Start("extmap");
		$this->freepbx->Extensions->setExtmap();
		$this->freepbx->Performance->Stop();

		// Dialplan Hook processing moved to BMO.
		$this->freepbx->Performance->Start("getAllDialplanHooks");
		$hooks = $this->freepbx->DialplanHooks->getAllHooks($active_modules);
		$this->freepbx->Performance->Stop();

		$this->freepbx->Performance->Start("processDialplanHooks");
		if (is_array($hooks)) {
			$this->freepbx->DialplanHooks->processHooks($engine, $hooks);
		}
		$this->freepbx->Performance->Stop();

		// extensions_additional.conf
		// create the from-internal-additional contexts so other can add to it
		$ext->add('from-internal-additional', 'h', '', new \ext_hangup(''));
		$ext->add('from-internal-noxfer-additional', 'h', '', new \ext_hangup(''));

		// Write extensions_additional.conf!
		$this->freepbx->Performance->Start("extensions_additional");
		$this->freepbx->WriteConfig->writeConfig($ext->get_filename(), $ext->generateConf());
		$this->freepbx->Performance->Stop();


		// Output any other configuration files from other modules.
		$this->freepbx->Performance->Start("processFileHooks");
		$this->freepbx->FileHooks->processFileHooks($module_list);
		$this->freepbx->Performance->Stop();

		// Now we write on amportal.conf if it is writable, which allows legacy applications in the
		// eco-system to take advantage of the settings.
		// we write out the error message here instead of in freepbx_settings so that we don't hit the db every single page load
		//
		if ($this->freepbx->Config->amportal_canwrite()) {
			file_put_contents('/etc/amportal.conf',$this->freepbx->Config->amportal_generate(true));
			$this->freepbx->Notifications->delete('framework', 'AMPORTAL_NO_WRITE');
		} elseif (!$this->freepbx->Notifications->exists('framework', 'AMPORTAL_NO_WRITE')) {
			$this->freepbx->Notifications->add_error('framework', 'AMPORTAL_NO_WRITE', _("amportal.conf not writeable"), _("Your amportal.conf file is not writeable. FreePBX is running in a crippled mode until changed. You can run 'amportal chown' from the Linux command line to rectify this."),true);
		}

		// Let's move some more of our checks to retrieve_conf so that we are not constantly checking these on page loads
		//

		// Warn about default Manager Interface Password
		//
		if ($this->freepbx->Config->get('AMPMGRPASS') == $this->freepbx->Config->get_conf_default_setting('AMPMGRPASS')) {
			if (!$this->freepbx->Notifications->exists('core', 'AMPMGRPASS')) {
				$this->freepbx->Notifications->add_warning('core', 'AMPMGRPASS', _("Default Asterisk Manager Password Used"), _("You are using the default Asterisk Manager password that is widely known, you should set a secure password"),'config.php?display=advancedsettings#ASTMANAGERHOST');
			}
		} else {
			$this->freepbx->Notifications->delete('core', 'AMPMGRPASS');
		}

		$this->freepbx->Notifications->delete('ari', 'ARI_ADMIN_PASSWORD');
		$this->freepbx->Notifications->delete('core', 'AMPDBPASS');

		// Warn if in deviceanduser mode and not using DYNAMICHINTS
		//
		if ($this->freepbx->Config->get('AMPEXTENSIONS') == 'deviceanduser' && !$this->freepbx->Config->get('DYNAMICHINTS')) {
			if (!$this->freepbx->Notifications->exists('framework', 'NO_DYNAMICHINTS')) {
				$this->freepbx->Notifications->add_warning('framework', 'NO_DYNAMICHINTS', _("Device & User Hints Issue"), _("You are set to Device and User mode but are not set to 'Dynamically Generate Hints' which can result in improper phone state behavior. This can be changed on the Advanced Settings page, check the tooltip for specific configuration details."));
			}
		} else {
			$this->freepbx->Notifications->delete('framework', 'NO_DYNAMICHINTS');
		}

		$this->installCrons();

		// run retrieve_conf_post_custom
		$post_custom = $this->freepbx->Config->get('AMPLOCALBIN').'/retrieve_conf_post_custom';
		if ($this->freepbx->Config->get('AMPLOCALBIN') && file_exists($post_custom)) {
			$this->freepbx->Notifications->add_warning('framework', 'retrieve_conf_post_custom', _("Retrieve Conf Post Custom Script Detected"), _("A Retrieve Conf Post Custom Script has been detected, the ability to run this after a reload has been removed"));
		} else {
			$this->freepbx->Notifications->delete('framework', 'retrieve_conf_post_custom');
		}

		//warning for use of mixmonitor for recordings
		if($this->freepbx->Modules->checkStatus("queues") && !$this->freepbx->Config->get('QUEUES_MIX_MONITOR')) {
			$this->freepbx->Notifications->add_warning('queues', 'QUEUES_MIX_MONITOR', _("MixMonitor for recordings is disabled"), _('It is recommended that Mixmonitor be used for call recordings as the Asterisk Monitor application is deprecated. In Advanced Settings please set "Use MixMonitor for Recordings" to Yes.'));
		} else {
			$this->freepbx->Notifications->delete('queues', 'QUEUES_MIX_MONITOR');
		}

		/* As of Asterisk 1.4.16 or there abouts, a missing #include file will make the reload fail. So
			we need to make sure that we have such for everything that is in our configs. We will simply
			look for the #include statements and touch the files vs. trying to inventory everything we may
			need and then forgetting something.
		*/
		$output = array();
		exec("grep '#include' ".$this->freepbx->Config->get('ASTETCDIR')."/*.conf | sed 's/;.*//; s/#include//'",$output,$retcode);
		if ($retcode != 0) {
			error("Error code $retcode: trying to search for missing #include files");
		}

		foreach($output as $file) {
			if (trim($file) == '') {
				continue;
			}
			$parse1 = explode(':',$file);
			$parse2 = explode(';',$parse1[1]);
			$rawfile = trim($parse2[0]);
			if ($rawfile == '') {
				continue;
			}

			$target = ($rawfile[0] == '/') ? $rawfile : $this->freepbx->Config->get('ASTETCDIR')."/$rawfile";

			if (!file_exists($target)) {
				$output = array();
				exec("touch $target", $output, $retcode);
				if ($retcode != 0) {
					error("Error code $retcode: trying to create empty file $target");
				}
			}
		}

		// **** Set reload flag for AMP admin
		needreload();

		if(!$this->freepbx->Config->get('SIGNATURECHECK')) {
			$this->freepbx->Notifications->add_notice('freepbx', 'SIGNATURE_CHECK', _('Signature checking is disabled'), _('FreePBX Module Signature checking has been disabled. Your system could be exposed to security vulnerabilities from compromised or tampered code'));
		} else {
			$this->freepbx->Notifications->delete('freepbx', 'SIGNATURE_CHECK');
			$this->freepbx->Performance->Start("Signature Checks");
			$external = true;
			if(file_exists($this->freepbx->Config->get('AMPBIN')."/fwconsole")) {
				if(!is_executable($this->freepbx->Config->get('AMPBIN')."/fwconsole")) {
					if(!@chmod($this->freepbx->Config->get('AMPBIN')."/fwconsole", 0755)) {
						$external = false;
					}
				}
			} else {
				$external = false;
			}
			if($external) {
				exec($this->freepbx->Config->get('AMPBIN')."/fwconsole util signaturecheck > /dev/null 2>&1 &");
			} else {
				\module_functions::create()->getAllSignatures(false);
			}
			$this->freepbx->Performance->Stop();
		}

		$this->freepbx->Notifications->delete('retrieve_conf', 'FATAL');

		$this->freepbx->Performance->Stop("retrieve_conf");

		if(!$this->dont_reload_asterisk) {
			//reload asterisk
			$this->freepbx->Performance->Start("Reload Asterisk");
			$this->freepbx->astman->Reload();
			$this->freepbx->Performance->Stop();
			if(version_compare($version,'12','lt')) {
				$this->freepbx->astman->UserEvent("reload");
			}

			//store asterisk reloaded status
			try {
				$sql = "UPDATE admin SET value = 'false' WHERE variable = 'need_reload'";
				$this->freepbx->Database->query($sql);
			} catch(\Exception $e) {
				throw new \Exception(_('Successful reload, but could not clear reload flag due to a database error: ').$e->getMessage());
			}
			//hmm should this be outside of this loop?
			$this->runPostReloadScript();
			$this->freepbx->Notifications->delete('freepbx','ASTRELOADSKIP');
		} else {
			$brand = $this->freepbx->Config->get('DASHBOARD_FREEPBX_BRAND');
			$this->freepbx->Notifications->add_warning('freepbx','ASTRELOADSKIP', _("Asterisk Reload Skipped"), sprintf(_("Asterisk reload was skipped but the %s configuration files were still written out. %s and Asterisk might be in a weird state"),$brand,$brand));
		}
		//conference app notification
		//check what is set for conference room app if app_meetme is selected then show the notification
		if($this->freepbx->Config->get('ASTCONFAPP') == 'app_meetme') {
			$this->freepbx->Notifications->add_warning('freepbx','ASTCONFAPP', _("Conference app_meetme"), _("As the app_meetme Asterisk application is deprecated and scheduled for removal in Asterisk 21, it's recommended that it be changed to app_confbridge."));
		} else {
			$this->freepbx->Notifications->delete('freepbx','ASTCONFAPP');
		}

		$this->freepbx->Hooks->processHooksByClassMethod('FreePBX\Reload', 'postReload');
		$this->writeln(_("Reload Complete"));
	}

	private function symlink_assets($module) {
		// e.g. /var/www/html/admin/modules/ringgroups/assets
		// e.g. /var/www/html/admin/assets/ringgroups
		//
		$srcdir = $this->freepbx->Config->get('AMPWEBROOT') . '/admin/modules/' . $module . '/assets';
		$targetdir = $this->freepbx->Config->get('AMPWEBROOT') . "/admin/assets/" . $module;

		// if assets does not exist in the module then there is
		// no need to have a link to it.
		if (!is_dir($srcdir)) {
			if (is_link($targetdir)) {
				$this->err_unlink($targetdir);
			}
			return;
		} else {
			// This module has assets that need to be linked.
			// Is the target already a directory?
			if (is_dir($targetdir) && !is_link($targetdir)) {
				// This shouldn't have happened. Rename it out of the way.
				rename($targetdir, "$targetdir.badasset");
			}
			// The assets dir exists in the module
			// If it's not a symlink, create it.
			if (!is_link($targetdir)) {
				if (!symlink($srcdir, $targetdir)) {
					freepbx_log(FPBX_LOG_ERROR, "Can not symlink $srcdir to $targetdir");
				} else {
					// All good, we created the correct link
					return true;
				}
			}

			// We're here because the link already exists. Make sure that the symlink is linking to the correct place.
			if (!is_link($targetdir)) {
				writeout(sprintf(_("Symlink error - %s should be linked to %s, and it isn't a link"),$targetdir,$srcdir),1);
				exit(255);
			}

			$dest = readlink($targetdir);
			if ($dest !== $srcdir) {
				// Wow. How did that happen?
				unlink($targetdir);
				if (!symlink($srcdir, $targetdir)) {
					freepbx_log(FPBX_LOG_ERROR, "Error replacing symlink $srcdir to $targetdir");
				}
			}
		}
	}

	private function generate_less($module) {
		// e.g. /var/www/html/admin/modules/ringgroups/assets
		// e.g. /var/www/html/admin/assets/ringgroups
		$this->freepbx->Less->generateModuleStyles($module);
	}

	private function symlink_sound_dirs($moduledir) {
		$language_dirs = array();
		foreach ($this->symlink_sound_dirs as $subdir => $targetdir) {
			$dir = $this->addslash($moduledir).$subdir;
			if (!is_dir($dir)) {
				continue;
			}
			$d = opendir($dir);
			while ($file = readdir($d)) {
				if ($file[0] != '.') {
					// If this is a directory, then put
					// it on the list of language
					// directories to process,
					// otherwise symlink it
					if (is_dir($this->addslash($dir).$file)) {
						$language_dirs[] = $file;
					} else {
						$this->do_symlink($this->addslash($dir).$file, $this->addslash($targetdir).$file, $subdir, $moduledir);
					}
				}
			}
			closedir($d);
			// If we found any langauge directories, then
			// check if they are installed on the target and
			// if so symlink them over.
			foreach ($language_dirs as $lang) {
				if (!is_dir($this->addslash($targetdir).$lang)) {
					// out(sprintf(_("found language dir %s for %s, not installed on system, skipping"),$lang,basename($moduledir)));
					continue;
				}
				$d = opendir($this->addslash($dir).$lang);
				while ($file = readdir($d)) {
					if ($file[0] != '.') {
						$this->do_symlink($this->addslash($dir).$this->addslash($lang).$file, $this->addslash($targetdir).$this->addslash($lang).$file, $subdir, $moduledir);
					}
				}
				closedir($d);
			}
		}
	}

	private function symlink_subdirs($moduledir) {
		foreach ($this->symlink_dirs as $subdir => $targetdir) {
			$dir = $this->addslash($moduledir).$subdir;
			if (is_dir($dir)) {
				$d = opendir($dir);
					while ($file = readdir($d)) {
					if ($file[0] != '.') {
						$this->do_symlink($this->addslash($dir).$file, $this->addslash($targetdir).$file, $subdir, $moduledir);
					}
				}
				closedir($d);
			}
		}
	}

	private function do_symlink($src, $dest, $subdir, $moduledir) {
		$f = basename($dest);
		if(in_array($f,array('amportal','fwconsole','retrieve_conf','freepbx_setting','freepbx_engine'))) {
			$this->symlink_error_modules .= "<br />&nbsp;&nbsp;&nbsp;".sprintf(_("Cannot replace reserved file %s from module %s (Not allowed)"),$f, basename($moduledir));
			return;
		}
		if ($this->file_exists_wrapper($dest)) {
			if ((!is_link($dest) || readlink($dest) != $src) && file_exists($src) && file_exists($dest) && !is_dir($dest) && (md5_file($src) == md5_file($dest))) {
				if(!@unlink($dest)) {
					freepbx_log(FPBX_LOG_ERROR, sprintf(_('Cannot remove conflicting file %s. Check Permissions?'),$dest));
					$this->symlink_error_modules .= "<br />&nbsp;&nbsp;&nbsp;".sprintf(_("Cannot remove conflicting file %s (Bad Permissions)"),$dest);
				} else {
					if (!symlink($src, $dest)) {
						freepbx_log(FPBX_LOG_ERROR, sprintf(_('Cannot symlink %s to %s. Check Permissions?'),$src,$dest));
						$this->symlink_error_modules .= "<br />&nbsp;&nbsp;&nbsp;".sprintf(_("%s from %s/%s (Bad Permissions)"),$dest,basename($moduledir),$subdir);
					}
				}
			} else if (!is_link($dest)) {
				//If the symlink error is coming from the etc directory then we move those files to backup
				if(preg_match('/^'.str_replace("/","\/",$this->freepbx->Config->get('ASTETCDIR')).'/',$dest) && is_writable($dest)) {
					if(!file_exists($this->freepbx->Config->get('ASTETCDIR').'/backup')) {
						mkdir($this->freepbx->Config->get('ASTETCDIR').'/backup');
					}
					$f = $this->freepbx->Config->get('ASTETCDIR').'/backup/'.basename($dest).".bk.".time();
					rename($dest,$f);
					if (!symlink($src, $dest)) {
						freepbx_log(FPBX_LOG_ERROR, sprintf(_('Cannot symlink %s to %s. Check Permissions?'),$src,$dest));
						$this->symlink_error_modules .= "<br />&nbsp;&nbsp;&nbsp;".sprintf(_("%s from %s/%s (Bad Permissions)"),$dest,basename($moduledir),$subdir);
					} else {
						$this->symlink_notice_modules .= "<br />&nbsp;&nbsp;&nbsp;".$dest;
					}
				} else if(preg_match('/^'.str_replace("/","\/",$this->freepbx->Config->get('AMPBIN')).'/',$dest) && is_writable($dest)) {
					if(!file_exists($this->freepbx->Config->get('AMPBIN').'/backup')) {
						mkdir($this->freepbx->Config->get('AMPBIN').'/backup');
					}
					$f = $this->freepbx->Config->get('AMPBIN').'/backup/'.basename($dest).".bk.".time();
					rename($dest,$f);
					if (!symlink($src, $dest)) {
						freepbx_log(FPBX_LOG_ERROR, sprintf(_('Cannot symlink %s to %s. Check Permissions?'),$src,$dest));
						$this->symlink_error_modules .= "<br />&nbsp;&nbsp;&nbsp;".sprintf(_("%s from %s/%s (Bad Permissions)"),$dest,basename($moduledir),$subdir);
					} else {
						$this->symlink_notice_modules_bin .= "<br />&nbsp;&nbsp;&nbsp;".$dest;
					}
				} else if(preg_match('/^'.str_replace("/","\/",$this->freepbx->Config->get('ASTAGIDIR')).'/',$dest) && is_writable($dest)) {
					if(!file_exists($this->freepbx->Config->get('ASTAGIDIR').'/backup')) {
						mkdir($this->freepbx->Config->get('ASTAGIDIR').'/backup');
					}
					$f = $this->freepbx->Config->get('ASTAGIDIR').'/backup/'.basename($dest).".bk.".time();
					rename($dest,$f);
					if (!symlink($src, $dest)) {
						freepbx_log(FPBX_LOG_ERROR, sprintf(_('Cannot symlink %s to %s. Check Permissions?'),$src,$dest));
						$this->symlink_error_modules .= "<br />&nbsp;&nbsp;&nbsp;".sprintf(_("%s from %s/%s (Bad Permissions)"),$dest,basename($moduledir),$subdir);
					} else {
						$this->symlink_notice_modules_agi .= "<br />&nbsp;&nbsp;&nbsp;".$dest;
					}
				} else {
					freepbx_log(FPBX_LOG_ERROR, sprintf(_('%s already exists, and is not a symlink!'),$dest));
					$this->symlink_error_modules .= "<br />&nbsp;&nbsp;&nbsp;".sprintf(_("%s from %s/%s (Already exists, not a link)"),$dest,basename($moduledir),$subdir);
				}
			} else if (readlink($dest) != $src) {
				//users need to be aware of symlink conflicts. We should attempt to resolve them properly though. So lets do that.
				freepbx_log(FPBX_LOG_ERROR, $dest.' already exists, and is linked to something else!');
				$this->symlink_error_modules .= "<br />&nbsp;&nbsp;&nbsp;".sprintf(_("%s from %s/%s (Already exists, linked to something else)"),$dest,basename($moduledir),$subdir);
			}
		} else {
			$targetdir = dirname($dest);
			if (!is_dir($targetdir)) {
				//The directory does not exist which can/could be bad but isn't.
				//It's mainly fw_ari old cruft here
				freepbx_log(FPBX_LOG_ERROR, sprintf(_("Tried to link %s to %s, but %s doesn't exist"),$src,$dest,$targetdir));
			} else {
				if (!symlink($src, $dest)) {
					freepbx_log(FPBX_LOG_ERROR, sprintf(_('Cannot symlink %s to %s. Check Permissions?'),$src,$dest));
					$this->symlink_error_modules .= "<br />&nbsp;&nbsp;&nbsp;".sprintf(_("%s from %s/%s (Bad Permissions)"),$dest,basename($moduledir),$subdir);
				}
			}
		}
	}

	private function symlink_check_errors() {
		if ($this->symlink_error_modules) {
			$this->freepbx->Notifications->add_error('retrieve_conf', 'SYMLINK', _("Symlink from modules failed"), sprintf(_("retrieve_conf failed to sym link: %s<br \>This can result in FATAL failures to your PBX. If the target file exists and not identical, the symlink will not occur and you should rename the target file to allow the automatic sym link to occur and remove this error, unless this is an intentional customization."),$this->symlink_error_modules));
		} else {
			$this->freepbx->Notifications->delete('retrieve_conf', 'SYMLINK');
		}
		if($this->symlink_notice_modules) {
			$this->freepbx->Notifications->add_notice('retrieve_conf', 'SYMLINKNOTICE', _("Symlink Conflict Resolved"),sprintf(_("retrieve_conf resolved a symlink with %s<br \>This is a notice to let you know that the original file was moved to %s, there is nothing more you need to do"),$this->symlink_notice_modules,$this->freepbx->Config->get('ASTETCDIR').'/backup'));
		}
		if($this->symlink_notice_modules_bin) {
			$this->freepbx->Notifications->add_notice('retrieve_conf', 'SYMLINKNOTICEBIN', _("Symlink Conflict Resolved"),sprintf(_("retrieve_conf resolved a symlink with %s<br \>This is a notice to let you know that the original file was moved to %s, there is nothing more you need to do"),$this->symlink_notice_modules,$this->freepbx->Config->get('AMPBIN').'/backup'));
		}
		if($this->symlink_notice_modules_agi) {
			$this->freepbx->Notifications->add_notice('retrieve_conf', 'SYMLINKNOTICEAGI', _("Symlink Conflict Resolved"),sprintf(_("retrieve_conf resolved a symlink with %s<br \>This is a notice to let you know that the original file was moved to %s, there is nothing more you need to do"),$this->symlink_notice_modules,$this->freepbx->Config->get('ASTAGIDIR').'/backup'));
		}
	}

	private function cp_subdirs($moduledir) {
		foreach ($this->cp_dirs as $subdir => $targetdir) {
			$dir = $this->addslash($moduledir).$subdir;
			if(is_dir($dir)){
				foreach($this->listdir($dir) as $idx => $file){
					$sourcefile = $file;
					$filesubdir=str_replace($dir.'/', '', $file);
					$targetfile = $this->addslash($targetdir).$filesubdir;

					if ($this->file_exists_wrapper($targetfile)) {
						if (is_link($targetfile)) {
							if (!$this->err_unlink($targetfile)) {
								freepbx_log(FPBX_LOG_ERROR, sprintf(_("%s is a symbolic link, failed to unlink!"),$targetfile));
								break;
							}
						}
					}
					// OK, now either the file is a regular file or
					// isn't there, so proceed
					if ($this->err_copy($sourcefile,$targetfile)) {
						// copy was successful, make sure it has execute permissions
						chmod($targetfile,0755);
						$ampowner = $this->freepbx->Config->get('AMPASTERISKWEBUSER');
						/* Address concerns carried over from amportal
						 * in FREEPBX-8268. If the apache user is different
						 * than the Asterisk user we provide permissions
						 * that allow both.
						 */
						$ampgroup =  $this->freepbx->Config->get('AMPASTERISKWEBUSER') != $this->freepbx->Config->get('AMPASTERISKUSER') ? $this->freepbx->Config->get('AMPASTERISKGROUP') : $this->freepbx->Config->get('AMPASTERISKWEBGROUP');
						chown($targetfile, $ampowner);
						chgrp($targetfile, $ampgroup);
					} else {
						freepbx_log(FPBX_LOG_ERROR, sprintf(_("%s failed to copy from module directory"),$targetfile));
					}
				}
			}
		}
	}

	private function cp_check_errors() {
		if ($this->cp_errors) {
			$this->freepbx->Notifications->add_error('retrieve_conf', 'CPAGIBIN', _("Failed to copy from module agi-bin"), sprintf(_("Retrieve conf failed to copy file(s) from a module's agi-bin dir: %s"),$this->cp_errors));
		} else {
			$this->freepbx->Notifications->delete('retrieve_conf', 'CPAGIBIN');
		}
	}

	private function add_cp_error($string) {
		$this->cp_errors .= $string;
	}

	private function err_copy($source, $dest) {
		$ret = false;
		set_error_handler(array($this,"report_errors"));
		//if were copying a directory, just mkdir the directory
		if (!is_link($dest) && !is_dir($dest)) {
			if(is_dir($source)){
				$ret = mkdir($dest,0754);
			}elseif(copy($source, $dest)) {
				$ret = chmod($dest,0754);
			}
		}
		restore_error_handler();
		return $ret;
	}

	private function err_unlink($dest) {
		set_error_handler(array($this,"report_errors"));
		$ret = unlink($dest);
		restore_error_handler();
		return $ret;
	}

	function report_errors($errno, $errstr, $errfile, $errline) {
		freepbx_log(FPBX_LOG_ERROR, sprintf(_("php reported: '%s' after copy or unlink attempt!"),$errstr));
		$this->add_cp_error($errstr."\n");
	}

	// Adds a trailing slash to a directory, if it doesn't already have one
	private function addslash($dir) {
		return (($dir[ strlen($dir)-1 ] == '/') ? $dir : $dir.'/');
	}

	/* file_exists_wrapper()
	* wrapper for file_exists() with the following additonal functionality.
	* if the file is a symlink, it will check if the link exists and if not
	* it will try to remove this file. It returns a false (file does not exists)
	* if the file is successfully removed, true if not. If not a symlink, just
	* returns file_exists()
	*/
	function file_exists_wrapper($string) {
		if (is_link($string)) {
			$linkinfo = readlink($string);
			if ($linkinfo === false) {
				//TODO: throw error?
				return !unlink($string);
			} else {
				if (file_exists($linkinfo)) {
					return true;
				} else {
					return !unlink($string);
				}
			}
		} else {
			return file_exists($string);
		}
	}

	//based on: http://snippets.dzone.com/posts/show/155
	function listdir($directory, $recursive=true) {
		$array_items = array();
			if ($handle = opendir($directory)) {
				while (false !== ($file = readdir($handle))) {
					if ($file != "." && $file != "..") {
						if (is_dir($directory. "/" . $file)) {
							if($recursive) {
								$array_items = array_merge($array_items, $this->listdir($directory. "/" . $file, $recursive));
							}
						$file = $directory . "/" . $file;
						$array_items[] = preg_replace("/\/\//si", "/", $file);
					}else{
						$file = $directory . "/" . $file;
						$array_items[] = preg_replace("/\/\//si", "/", $file);
					}
				}
			}
			closedir($handle);
		}
		return array_reverse($array_items);//reverse so that we get directories BEFORE the files that are in them
	}

	function installCrons() {
		$freepbxCron = $this->freepbx->Cron;
		foreach($freepbxCron->getAll() as $c) {
			if(preg_match('/fwconsole util cleanplaybackcache/',$c,$matches)) {
				$freepbxCron->remove($c);
			}
		}
		$freepbxCron->add(array(
			"command" => $this->freepbx->Config->get('AMPSBIN')."/fwconsole util cleanplaybackcache -q",
			"hour" => rand(0,5)
		));

		$um = new \FreePBX\Builtin\UpdateManager();
		$um->updateCrontab();
	}

	private function checkAsterisk() {
		$loc = fpbx_which("asterisk");
		if(empty($loc)) {
			throw new \Exception(_("Unable to find the Asterisk binary"));
		} else {
			$process = new Process($loc . " -rx 'core show version'");
			$process->mustRun();
		}

		if (!$this->freepbx->astman->connected()) {
			throw new \Exception(_("Unable to connect to Asterisk Manager"));
		}
	}

	private function runPostReloadScript() {
		$setting_post_reload = $this->freepbx->Config->get('POST_RELOAD');
		if ($setting_post_reload)  {
			exec( $setting_post_reload, $output, $exit_val );

			if ($exit_val != 0) {
				$desc = sprintf(_("Exit code was %s and output was: %s"), $exit_val, "\n\n".implode("\n",$output));
				$this->freepbx->Notifications->add_error('freepbx','reload_post_script', sprintf(_('Could not run %s script.'), $setting_post_reload), $desc);
			} else {
				$this->freepbx->Notifications->delete('freepbx', 'reload_post_script');
			}
		}
	}

	private function runPreReloadScript() {
		$setting_pre_reload = $this->freepbx->Config->get('PRE_RELOAD');
		if ($setting_pre_reload)  {
			$process = new Process($setting_pre_reload);
			$process->run();

			if (!$process->isSuccessful()) {
				$desc = sprintf(_("Exit code was %s and output was: %s"), $process->getExitCode(), "\n\n".implode("\n",$process->getOutput()));
				$this->freepbx->Notifications->add_error('freepbx','reload_pre_script', sprintf(_('Could not run %s script.'), $setting_pre_reload), $desc);
			} else {
				$this->freepbx->Notifications->delete('freepbx', 'reload_pre_script');
			}
		}
	}

	private function checkMemoryLimits() {
		$meminfo = getSystemMemInfo();
		if(!empty($meminfo['MemTotal'])) {
			$memt = preg_replace("/\D/","",$meminfo['MemTotal']);
			ini_set('memory_limit',$memt.'K');
		} else {
			$memt = 0;
		}
		$brand ='';
		if ($this->freepbx->Modules->checkStatus("sysadmin")) {
			$brand = $this->freepbx->Sysadmin->getDeploymentType();
		}
		if($brand != 'PBXact UCC'){
			$mems = isset($meminfo['SwapTotal']) ? preg_replace("/\D/","",$meminfo['SwapTotal']) : '';
			if(empty($mems)) {
				$this->freepbx->Notifications->add_warning('core', 'SWAP', _("No Swap"), _("Your system has no swap space. This should be fixed as soon as possible. Once fixed issue a reload to remove this message"));
			} else {
				if($mems < 200000) {
					$this->freepbx->Notifications->add_warning('core', 'SWAP', _("No Swap"), sprintf(_("The swap space of your system is too low (%s KB). You should have at least %s KB of swap space. This should be fixed as soon as possible. Once fixed issue a reload to remove this message"),$mems,200000));
				} else {
					$this->freepbx->Notifications->delete('core', 'SWAP');
				}
			}
		}
		//remove no swap warning for PBXact UCC
		if($brand == 'PBXact UCC'){
			$this->freepbx->Notifications->delete('core', 'SWAP');
		}

		// Check and increase php memory_limit if needed and if allowed on the system
		// TODO: should all be in bootstrap
		$current_memory_limit = rtrim(ini_get('memory_limit'),'M');
		$proper_memory_limit = '100';
		if ($current_memory_limit < $proper_memory_limit) {
			if (ini_set('memory_limit',$proper_memory_limit.'M') !== false) {
				$this->freepbx->Notifications->add_notice('core', 'MEMLIMIT', _("Memory Limit Changed"), sprintf(_("Your memory_limit, %sM, is set too low and has been increased to %sM. You may want to change this in you php.ini config file"),$current_memory_limit,$proper_memory_limit));
			} else {
				$this->freepbx->Notifications->add_warning('core', 'MEMERR', _("Low Memory Limit"), sprintf(_("Your memory_limit, %sM, is set too low and may cause problems. FreePBX is not able to change this on your system. You should increase this to %sM in you php.ini config file"),$current_memory_limit,$proper_memory_limit),'http://wiki.freepbx.org/x/lgK3AQ');
			}
		} else {
			$this->freepbx->Notifications->delete('core', 'MEMLIMIT');
		}
	}

	private function writeln($message) {
		$shell = !$this->json && isset($_SERVER['SHELL']);
		if($shell){
			$this->output->writeln($message);
		}else{
			echo $this->output->writeln(json_encode(array('message'=>$message)));
		}
	}

	private function write($message) {
		$shell = !$this->json && isset($_SERVER['SHELL']);
		if($shell){
			echo $this->output->write($message);
		}else{
			//writeln on purpose!
			echo $this->output->writeln(json_encode(array('message'=>$message)));
		}
	}

	private function setLock() {
		$ASTRUNDIR = \FreePBX::Config()->get("ASTRUNDIR");
		$lock = $ASTRUNDIR."/reload.lock";

		if(!$this->checkLock()) {
			$pid = getmypid();
			file_put_contents($lock,$pid);
			return true;
		} else {
			$pid = file_get_contents($lock);
			echo json_encode(["error" => 'Process is already running.', "trace" =>'Process ID : '.$pid]);
			exit(-1);
		}
		return false;
	}

	private function checkLock() {
		$ASTRUNDIR = \FreePBX::Config()->get("ASTRUNDIR");
		$lock = $ASTRUNDIR."/reload.lock";
		if(file_exists($lock)) {
			$pid = file_get_contents($lock);
			if(posix_getpgid($pid) !== false) {
				return true;
			} else {
				$this->removeLock();
			}
		}
		return false;
	}

	private function removeLock() {
		$ASTRUNDIR = \FreePBX::Config()->get("ASTRUNDIR");
		$lock = $ASTRUNDIR."/reload.lock";
		if(file_exists($lock)) {
			unlink($lock);
		}
		return true;
	}

}
