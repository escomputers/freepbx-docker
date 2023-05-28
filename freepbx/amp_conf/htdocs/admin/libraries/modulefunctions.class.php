<?php
/**
 * Module functions
 */

define('MODULE_STATUS_NOTINSTALLED', 0);
define('MODULE_STATUS_DISABLED', 1);
define('MODULE_STATUS_ENABLED', 2);
define('MODULE_STATUS_NEEDUPGRADE', 3);
define('MODULE_STATUS_BROKEN', -1);
define('MODULE_STATUS_CONFLICT', -2);
define('MODULE_STATUS_CONFLICT_UPGRADE', -3);
if(false) {
	//Standard remote repositories
	_("Standard");
	_("Extended");
	_("Commercial");
	_("Unsupported");
	_("Orphan");
	//Standard tracks
	_("Stable");
	_("Beta");
	_("Nightly");
}

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;
use Doctrine\Common\Cache\ArrayCache;

class module_functions {
	public $security_array = null;
	public $modDepends = array();
	public $notFound = false;
	public $downloadedRawname = "";
	//Max Execution Time Limit
	private $maxTimeLimit = 750;
	private $onlineModules = null;

	private $modXMLCache = array();
	private $cacheDriver;

	private $getInfoCache = array();

	public static function create() {
		static $obj;
		if (!isset($obj) || !is_object($obj)) {
			$obj = new module_functions();
		}
		return $obj;
	}

	public function __construct() {
		$time_limit = ini_get('max_execution_time');
		if(!empty($time_limit)) {
			$this->maxTimeLimit = (int)$time_limit + $this->maxTimeLimit;
		}
		$this->cacheDriver = new ArrayCache();
	}

	/**
	* Get the latest module.xml file for this FreePBX version.
	*
	* Caches in the database for 5 mintues.
	* If $module is specified, only returns the data for that module.
	* If the module is not found (or none are available for whatever reason),
	* then null is returned.
	*
	* Sets the global variable $module_getonlinexml_error to true if an error
	* occurred getting the module from the repository, false if no error occurred,
	* or null if the repository wasn't checked. Note that this may change in the
	* future if we decide we need to return more error codes, but as long as it's
	* a php zero-value (false, null, 0, etc) then no error happened.
	*
	* @param string $module rawname of module to get xml for
	* @param bool $override_xml Different xml path to use for repos instead of the included default
	* @param bool $never_refresh Never look at the mirror server.  This is used when there may be several rapid calls to getonlinexml
	* @param bool force - If this is true then forcefully fetch the data from the mirror server irrespective of any local settings.
	* @return array combined module xml array or empty array
	*/
	function getonlinexml($module = false, $override_xml = false, $never_refresh = false, $force = false) { // was getModuleXml()
		global $amp_conf, $db, $module_getonlinexml_error;  // okay, yeah, this sucks, but there's no other good way to do it without breaking BC
		$module_getonlinexml_error = false;
		$got_new = false;
		$skip_cache = false;
		$sec_array=false;
		$modules = array();

		$result = sql("SELECT * FROM module_xml WHERE id = 'beta'",'getRow',DB_FETCHMODE_ASSOC);
		if(!empty($result['data'])) {
			$beta = json_decode($result['data'],true);
		}
		$result = sql("SELECT * FROM module_xml WHERE id = 'edge'",'getRow',DB_FETCHMODE_ASSOC);
		if(!empty($result['data'])) {
			$edge = json_decode($result['data'],true);
		}
		$result = sql("SELECT * FROM module_xml WHERE id = 'security'",'getRow',DB_FETCHMODE_ASSOC);
		if(!empty($result['data'])) {
			$security = json_decode($result['data'],true);
		}
		$result = sql("SELECT * FROM module_xml WHERE id = 'previous'",'getRow',DB_FETCHMODE_ASSOC);
		if(!empty($result['data'])) {
			$previous = json_decode($result['data'],true);
		}
		$result = sql("SELECT * FROM module_xml WHERE id = 'modules'",'getRow',DB_FETCHMODE_ASSOC);
		if(isset($result['data']) && !empty($result['data'])) {
			$modules = json_decode($result['data'],true);
		}

		// Check if the cached module xml is for the same repo as being requested
		// if not, then we get it anyhow
		//
		$repo_url = ($override_xml === false) ? $amp_conf['MODULE_REPO'] : $override_xml;
		$result2 = sql("SELECT * FROM module_xml WHERE id = 'module_repo'",'getRow',DB_FETCHMODE_ASSOC);
		$last_repo = !empty($result['data']) ? $result2['data']:'';
		if ($last_repo !== $repo_url) {
			sql("DELETE FROM module_xml WHERE id = 'module_repo'");
			$data4sql = $db->escapeSimple($repo_url);
			sql("INSERT INTO module_xml (id,time,data) VALUES ('module_repo',".time().",'".$data4sql."')");
			$skip_cache = true;
		}

		// if the epoch in the db is more than 2 hours old, or the xml is less than 100 bytes, then regrab xml
		// Changed to 5 minutes while not in release. Change back for released version.
		//
		$skip_cache |= $amp_conf['MODULEADMIN_SKIP_CACHE'];
		$version = getversion();
		// we need to know the freepbx major version we have running (ie: 12.0.1 is 12.0)
		preg_match('/(\d+\.\d+)/',$version,$matches);
		$base_version = $matches[1];
		if (!isset($result['time']) || empty($result['time'])) {
			$result['time'] = time(); // initialize time variable if not set
		}
		if(!$never_refresh && ($force || ( (time() - $result['time']) > 300 || $skip_cache || empty($modules) ))) {
			set_time_limit($this->maxTimeLimit);
			if ($override_xml) {
				$data = $this->url_get_contents($override_xml,"/modules-" . $base_version . ".xml");
			} else {
				// We pass in true to add options to accomodate future needs of things like php versions to get properly zended
				// tarballs of the same version for modules that are zended.
				//
				$all = $this->get_remote_contents("/all-" . $base_version . ".xml", true, true);
				if(!empty($all)) {
					try {
						$parser = new xml2ModuleArray($all);
						$allxml = $parser->parseAdvanced($all);
					} catch(\Exception $e) {
						freepbx_log(FPBX_LOG_ERROR,sprintf(_("Invalid Response from Mirror server: %s"),$all));
						throw new \Exception("Unable to Parse XML response from Mirror. See the log for more details");
					}
				} else {
					$module_getonlinexml_error = true;
				}
			}

			$old_modules = array();
			$got_new = false;
			if(!empty($allxml['xml']['module'])) {
				$modules = $allxml['xml']['module'];
				$sql = "SELECT data FROM module_xml WHERE id = 'modules'";
				$old_modules = sql($sql, "getOne");
				if(!empty($old_modules)) {
					$old_modules = json_decode($old_modules,true);
					$this->update_notifications($old_modules, $modules, ($old_modules == $modules));
				}
				// update the db with the new xml
				$data4sql = $db->escapeSimple(json_encode($modules));
				sql("REPLACE INTO module_xml (id,time,data) VALUES('modules',".time().",'".$data4sql."')");
			}
			if(!empty($allxml['xml']['edge'])) {
				$edge = $allxml['xml']['edge'];
				// update the db with the new xml
				$data4sql = $db->escapeSimple(json_encode($edge));
				sql("REPLACE INTO module_xml (id,time,data) VALUES('edge',".time().",'".$data4sql."')");
			} else {
				sql("REPLACE INTO module_xml (id,time,data) VALUES('edge',".time().",'')");
			}
			if(!empty($allxml['xml']['beta'])) {
				$beta = $allxml['xml']['beta'];
				// update the db with the new xml
				$data4sql = $db->escapeSimple(json_encode($beta));
				sql("REPLACE INTO module_xml (id,time,data) VALUES('beta',".time().",'".$data4sql."')");
			} else {
				sql("REPLACE INTO module_xml (id,time,data) VALUES('beta',".time().",'')");
			}
			if(!empty($allxml['xml']['security'])) {
				$security = $allxml['xml']['security'];
				// update the db with the new xml
				$data4sql = $db->escapeSimple(json_encode($security));
				sql("REPLACE INTO module_xml (id,time,data) VALUES('security',".time().",'".$data4sql."')");
			}
			if(!empty($allxml['xml']['previous'])) {
				$previous = $allxml['xml']['previous'];
				// update the db with the new xml
				$data4sql = $db->escapeSimple(json_encode($previous));
				sql("REPLACE INTO module_xml (id,time,data) VALUES('previous',".time().",'".$data4sql."')");
			}
		}

		if (empty($modules)) {
			// no data, probably couldn't connect online, and nothing cached
			return array();
		}

		if(!empty($security)) {
			foreach($security['issue'] as $item) {
				$this->security_array[$item['id']] = $item;
				$sec_array[$item['id']] = $item;
			}
		}

		//this is why xml to array is terrible on php, prime example here.
		$modules = !isset($modules['rawname']) ? $modules : array($modules);

		$exposures = $this->get_security($security, $base_version);
		$this->update_security_notifications($exposures);

		if(isset($modules)) {
			if ($module != false) {
				foreach ($modules as $mod) {
					if ($module == $mod['rawname']) {
						if(!empty($edge[$module]) && (isset($amp_conf['MODULEADMINEDGE']) && $amp_conf['MODULEADMINEDGE']) && version_compare_freepbx($mod['version'],$edge[$module]['version'],'<')) {
							$mod = $edge[$module];
						}
						$releases = !empty($previous[$module]['releases']['module']) ? $previous[$module]['releases']['module'] : array();
						$mod['previous'] = isset($releases['rawname']) ? array($releases) : $releases;
						if(!empty($beta[$module])) {
							$betalist = isset($beta[$module]['rawname']) ? array($beta[$module]) : $beta[$module];
							$mod['highreleasetrackver'] = $mod['version'];
							$mod['highreleasetracktype'] = 'stable';
							foreach($betalist as $release) {
								$mod['releasetracks'][$release['releasetracktype']] = $release;
								if(version_compare_freepbx($mod['highreleasetrackver'],$release['version'],'<')) {
									$mod['highreleasetrackver'] = $release['version'];
									$mod['highreleasetracktype'] = $release['releasetracktype'];
								}
							}
						} else {
							$mod['highreleasetrackver'] = $mod['version'];
							$mod['highreleasetracktype'] = 'stable';
							$mod['releasetracks'] = array();
						}
						return $mod;
					}
				}
				return array();
			} else {
				$final = array();
				foreach ($modules as $mod) {
					if(!empty($edge[$mod['rawname']]) && (isset($amp_conf['MODULEADMINEDGE']) && $amp_conf['MODULEADMINEDGE']) && version_compare_freepbx($mod['version'],$edge[$mod['rawname']]['version'],'<')) {
						$mod = $edge[$mod['rawname']];
					}
					$final[$mod['rawname']] = $mod;
					if (isset($exposures[$mod['rawname']])) {
						$final[$mod['rawname']]['vulnerabilities'] = $exposures[$mod['rawname']];
					}
					$releases = !empty($previous[$mod['rawname']]['releases']['module']) ? $previous[$mod['rawname']]['releases']['module'] : array();
					$final[$mod['rawname']]['previous'] = isset($releases['rawname']) ? array($releases) : $releases;
					if(!empty($beta[$mod['rawname']])) {
						$betalist = isset($beta[$mod['rawname']]['rawname']) ? array($beta[$mod['rawname']]) : $beta[$mod['rawname']];
						$final[$mod['rawname']]['highreleasetrackver'] = $final[$mod['rawname']]['version'];
						$final[$mod['rawname']]['highreleasetracktype'] = 'stable';
						foreach($betalist as $release) {
							$final[$mod['rawname']]['releasetracks'][$release['releasetracktype']] = $release;
							if(version_compare_freepbx($final[$mod['rawname']]['highreleasetrackver'],$release['version'],'<')) {
								$final[$mod['rawname']]['highreleasetrackver'] = $release['version'];
								$final[$mod['rawname']]['highreleasetracktype'] = $release['releasetracktype'];
							}
						}
					} else {
						$final[$mod['rawname']]['highreleasetrackver'] = $final[$mod['rawname']]['version'];
						$final[$mod['rawname']]['highreleasetracktype'] = 'stable';
						$final[$mod['rawname']]['releasetracks'] = array();
					}
				}
				$this->onlineModules = $final;
				return $final;
			}
		}
		return array();
	}

	/**
	* Return any existing security vulnerabilities in currently installed modules if
	* present in the xmlarray
	*
	* @param array the parsed xml array containing the security information
	* @param string the current base version of freepbx if already available, or leave out
	* @return array an array of vulnerable modules along with some vulnerability data
	*/
	function get_security($xmlarray=null, $base_version=null) {

		if($xmlarray === null) {
			$result = sql("SELECT * FROM module_xml WHERE id = 'security'",'getRow',DB_FETCHMODE_ASSOC);
			if(!empty($result['data'])) {
				$xmlarray = json_decode($result['data'],true);
			} else {
				return array();
			}
		}

		if ($base_version === null) {
			$version = getversion();
			// we need to know the freepbx major version we have running (ie: 2.1.2 is 2.1)
			preg_match('/(\d+\.\d+)/',$version,$matches);
			$base_version = $matches[1];
		}

		if (!empty($xmlarray)) {
			$exposures = array();
			$modinfo = $this->getinfo();

			// check each listed vulnerability to see if there are vulnerable modules for this version
			//
			foreach ($xmlarray['issue'] as $sinfo) {
				$vul = $sinfo['id'];
				if (!empty($sinfo['versions']['v' . $base_version])) {
					// If this version has vulnerabilities, check each vulnerable module to see if we have any
					//
					if (strtolower($sinfo['versions']['v' . $base_version]['vulnerable']) == 'yes' && !empty($sinfo['versions']['v' . $base_version]['fixes'])) foreach ($sinfo['versions']['v' . $base_version]['fixes'] as $rmod => $mver) {
						$rmod = trim($rmod);
						$mver = trim($mver);
						// If we have $rmod on our system then we will check if it is a vulnerable version
						//
						if (!empty($modinfo[trim($rmod)])) {
							$thisver = isset($modinfo[$rmod]['dbversion']) ? $modinfo[$rmod]['dbversion'] : $modinfo[$rmod]['version'];
							if (version_compare_freepbx($thisver, $mver, 'lt')) {
								if (!isset($exposures[$rmod])) {
									// First exposure we have seen that affects this module so hash it
									//
									$exposures[$rmod] = array('vul' => array($vul), 'minver' => $mver, 'curver' => $thisver);
								} else {
									// We already know this module is suceptible so check a higher version then already recorded
									// is need to secure this module
									//
									$exposures[$rmod]['vul'][] = $vul;
									if (version_compare_freepbx($mver, $exposures[$rmod]['minver'], 'gt')) {
										$exposures[$rmod]['minver'] = $mver;
									}
								}
							}
						}
					}
				}
			}
			return $exposures;
		}
	}


	/**
	* Determines if there are updates we don't already know about and posts to notification
	* server about those updates.
	*
	* @param array $old_xml The old xml taken from the DB cache
	* @param array $xmlarray The new XML taken from the online resource
	* @param array $passive Whether to allow notification to be reset
	*
	*/
	function update_notifications($omodules, $modules, $passive) {
		global $db;

		$notifications = notifications::create($db);

		$reset_value = $passive ? 'PASSIVE' : false;

		$new_modules = array();
		if (count($modules)) {
			foreach ($modules as $mod) {
				$new_modules[$mod['rawname']] = $mod;
			}
		}
		$old_modules = array();
		if (count($omodules)) {
			foreach ($omodules as $mod) {
				$old_modules[$mod['rawname']] = $mod;
			}
		}

		// If keys (rawnames) are different then there are new modules, create a notification.
		// This will always be the case the first time it is run since the xml is empty.
		//
		$diff_modules = array_diff_key($new_modules, $old_modules);
		$cnt = count($diff_modules);
		if ($cnt) {
			$active_repos = $this->get_active_repos();
			$extext = _("The following new modules are available for download. Click delete icon on the right to remove this notice.")."<br />";
			foreach ($diff_modules as $modname => $data) {
				$mod = $new_modules[$modname];
				// If it's a new module in a repo we are not interested in, then don't send a notification.
				if (isset($active_repos[$mod['repo']]) && $active_repos[$mod['repo']]) {
					$extext .= $mod['rawname']." (".$mod['version'].")<br />";
				} else {
					$cnt--;
				}
			}
			if ($cnt) {
				$notifications->add_notice('freepbx', 'NEWMODS', sprintf(_('%s New modules are available'),$cnt), $extext, 'config.php?display=modules', $reset_value, true);
			}
		}

		// Now check if any of the installed modules need updating
		//
		$this->upgrade_notifications($new_modules, $reset_value);
	}

	/**
	* Compare installed (enabled or disabled) modules against the xml to generate or
	* update the notification table of which modules have available updates. If the list
	* is empty then delete the notification.
	*
	* @param array $new_modules New Module XML
	* @param array $passive Whether to allow notification to be reset
	*/
	function upgrade_notifications($new_modules, $passive_value) {
		global $db;
		$notifications = notifications::create($db);

		$modules_upgradeable = \FreePBX::Modules()->getUpgradeableModules($new_modules);
		if ($modules_upgradeable) {
			$cnt = count($modules_upgradeable);
			if ($cnt == 1) {
				$text = _("There is 1 module available for online upgrade");
			} else {
				$text = sprintf(_("There are %s modules available for online upgrades"),$cnt);
			}
			$extext = "";
			foreach ($modules_upgradeable as $name => $mod) {
				$extext .= sprintf(_("%s (current: %s)"), $mod['name'].' '.$mod['online_version'], $mod['local_version'])."\n";
			}
			$notifications->add_update('freepbx', 'NEWUPDATES', $text, $extext, 'config.php?display=modules', $passive_value);
		} else {
			$notifications->delete('freepbx', 'NEWUPDATES');
		}
	}

	/**
	* Updates the notification panel of any known vulnerable modules present
	* on the system. Since these are security notifications, emails will be
	* sent out informing of the issues if enabled.
	*
	* @param array $exposures vulnerability information array returned by module_get_security()
	*/
	function update_security_notifications($exposures) {
		global $db;
		$notifications = notifications::create($db);

		if (!empty($exposures)) {
			$cnt = count($exposures);
			if ($cnt == 1) {
				$text = _("There is 1 module vulnerable to security threats");
			} else {
				$text = sprintf(_("There are %s modules vulnerable to security threats"), $cnt);
			}
			$extext = "";
			foreach($exposures as $m => $vinfo) {
				$extext .= sprintf(
					_("%s (Cur v. %s) should be upgraded to v. %s to fix security issues: %s")."\n",
					$m, $vinfo['curver'], $vinfo['minver'], implode(', ',$vinfo['vul'])
				);
			}
			$notifications->add_security('freepbx', 'VULNERABILITIES', $text, $extext, 'config.php?display=modules');
		} else {
			$notifications->delete('freepbx', 'VULNERABILITIES');
		}
	}

	/**
	* Get Active Locally Set Repos
	*
	* @return array Array of Active Repos array("<reponame>" => 1)
	*/
	function get_active_repos() {
		global $active_repos;
		global $db;

		if (!isset($active_repos) || !$active_repos) {
			$repos = sql("SELECT `data` FROM `module_xml` WHERE `id` = 'repos_json'","getOne");
			if(!empty($repos)) {
				$repos = json_decode($repos,TRUE);
			} else {
				$repos_serialized = sql("SELECT `data` FROM `module_xml` WHERE `id` = 'repos_serialized'","getOne");
				if(!empty($repos_serialized)) {
					$repos = unserialize($repos_serialized);
					$repos_json = $db->escapeSimple(json_encode($repos));
					sql("REPLACE INTO `module_xml` (`id`, `time`, `data`) VALUES ('repos_json', '".time()."','".$repos_json."')");
					sql("DELETE FROM `module_xml` WHERE `id` = 'repos_serialized'");
				}
			}
			if (!empty($repos)) {
				$active_repos = $repos;
				if(!isset($active_repos['standard']) || $active_repos['standard'] == 1) {
					$active_repos['standard'] = 1;
					$this->set_active_repo('standard',1);
				}
			} else {
				$active_repos = array('standard' => 1);
				$this->set_active_repo('standard',1);
			}
						$final_repos = array();
						foreach($active_repos as $repo => $state) {
								$repo = strtolower($repo);
								$final_repos[$repo] = $state;

						}
			return $final_repos;
		} else {
			return $active_repos;
		}
	}

	/**
	* Enable or disable an online repository
	*
	* @param string $repo The repository name
	* @param int $active 1 for true 0 for false
	*/
	function set_active_repo($repo,$active=1) {
		global $db;
		$repos = $this->get_active_repos();
		if(!empty($active)) {
			$repos[$repo] = 1;
		} elseif(isset($repos[$repo])) {
			unset($repos[$repo]);
		}
		$repos_json = $db->escapeSimple(json_encode($repos));
		$o = sql("REPLACE INTO `module_xml` (`id`, `time`, `data`) VALUES ('repos_json', '".time()."','".$repos_json."')");
		return $o;
	}

	/**
	* Retrieve and Store available remote repositories
	*
	*/
	function generate_remote_repos() {
		global $db;
		$xml = !empty($this->onlineModules) ? $this->onlineModules : $this->getonlinexml();
		if(!empty($xml)) {
			$repos = array();
			foreach($xml as $module) {
				$repo = strtolower($module['repo']);
				if(!in_array($repo,$repos)) {
					$repos[] = $repo;
				}
			}
			$repos_json = $db->escapeSimple(json_encode($repos));
			sql("REPLACE INTO `module_xml` (`id`, `time`, `data`) VALUES ('remote_repos_json', '".time()."','".$repos_json."')");
			return $repos;
		}
		return false;
	}

	/**
	* Store available remote repositories
	*
	* @param array $repos Array of remote repositories
	*/
	function set_remote_repos($repos) {
		global $db;
		$old_remote_repos = $this->get_remote_repos(true);
		$active_repos = $this->get_active_repos();
				$final_repos = array();
		foreach($repos as $repo) {
						if(in_array(strtolower($repo),$final_repos)) {
								continue;
						}
			//If there is a new repo detected and it's not in our former list of remote repos
			//and it was not previously medled with locally then enable it automatically
			if(!in_array($repo,$old_remote_repos) && !isset($active_repos[$repo]) && $repo != 'orphan') {
				$this->set_active_repo($repo,1);
			}
						$final_repos[] = $repo;
		}
		$repos_json = $db->escapeSimple(json_encode($final_repos));
		sql("REPLACE INTO `module_xml` (`id`, `time`, `data`) VALUES ('remote_repos_json', '".time()."','".$repos_json."')");
	}

	/**
	* Get the list of locally stored remote repository names
	*
	* @return array Array of remote repositories
	*/
	function get_remote_repos($online = false) {
		global $db;
		$repos = ($online) ? $this->generate_remote_repos() : array();
		if(empty($repos)) {
			$repos = sql("SELECT `data` FROM `module_xml` WHERE `id` = 'remote_repos_json'","getOne");
			if(!empty($repos)) {
				$repos = json_decode($repos,TRUE);
				$repos = array_diff($repos, array('local','broken'));
			}
		}
		return !empty($repos) && is_array($repos) ? $repos : array();
	}

	function set_tracks($modules) {
		global $db;
		$sql = "SELECT data FROM module_xml WHERE id = 'track'";
		$track = sql($sql, "getOne");

		$track = !empty($track) ? json_decode($track,TRUE) : array();
		foreach($modules as $module => $t) {
			$track[$module] = $t;
		}
		$track = json_encode($track);

		sql("REPLACE INTO module_xml (id,time,data) VALUES('track',".time().",'".$track."')");
	}

	function get_track($modulename) {
		global $db;
		if(empty($this->module_tracks)) {
			$sql = "SELECT data FROM module_xml WHERE id = 'track'";
			$this->module_tracks = sql($sql, "getOne");
			$this->module_tracks = !empty($this->module_tracks) ? json_decode($this->module_tracks,TRUE) : array();
		}

		return !empty($this->module_tracks[$modulename]) ? $this->module_tracks[$modulename] : 'stable';
	}

	/**
	* Looks through the modules directory and modules database and returns all available
	* information about one or all modules
	*
	* @param string $module (optional) The module name to query, or false for all module
	* @param mixed $status (optional) The status(es) to show, using MODULE_STATUS_* constants. Can
	*                either be one value, or an array of values.
	*/
	function getinfo($module = false, $status = false, $forceload = false) {
		//$this->FreePBX->Cache->
		$cacheKey = 'getinfo_'.$module . '_' . (is_array($status) ? implode("_",$status) : $status);
		if(isset($this->getInfoCache[$cacheKey])) {
			return $this->getInfoCache[$cacheKey];
		}
		if(!$forceload) {
			if(!empty($f)) {
				return $f;
			}
		}
		global $amp_conf, $db;
		$modules = array();
		$freepbx = FreePBX::Create();
		$modulelist = $freepbx->Modulelist;
		if ($module) {
			// get info on only one module
			$xml = $this->_readxml($module, !($forceload));
			if (!is_null($xml)) {
				$modules[$module] = $xml;
				// if status is anything else, it will be updated below when we read the db
				$modules[$module]['status'] = MODULE_STATUS_NOTINSTALLED;
			}
			// query to get just this one
			$sql = 'SELECT * FROM modules WHERE modulename = ?';
		} else {
			if ($forceload) {
				$modulelist->invalidate();
				$this->modXMLCache = array();
			}
			if (!$modulelist->is_loaded()) {
				// initialize list with "builtin" module
				$module_list = array('builtin');

				// read modules dir for module names
				if(file_exists($amp_conf['AMPWEBROOT'].'/admin/modules') && is_dir($amp_conf['AMPWEBROOT'].'/admin/modules')) {
					$dir = opendir($amp_conf['AMPWEBROOT'].'/admin/modules');
					while ($file = readdir($dir)) {
						if (($file != ".") && ($file != "..") && ($file != "CVS") &&
								($file != ".svn") && ($file != "_cache") &&
								is_dir($amp_conf['AMPWEBROOT'].'/admin/modules/'.$file)) {
							$module_list[] = $file;
						}
					}
					closedir($dir);
				}

				// read the xml for each
				foreach ($module_list as $file) {
					$xml = $this->_readxml($file, !($forceload));
					if (!is_null($xml)) {
						$modules[$file] = $xml;
						// if status is anything else, it will be updated below when we read the db
						$modules[$file]['status'] = MODULE_STATUS_NOTINSTALLED;
						// I think this is the source of reading every module from a file. The assumption is all modules
						// from the online repo will always have a repo defined but local ones may not so we need to define
						// them here.

						//TODO: should we have a master list of supported repos and validate against that, or do it dynamically
						//      we do with other stuff
						if (!isset($modules[$file]['repo']) || !$modules[$file]['repo']) {
							$modules[$file]['repo'] = 'local';
						}
					}
				}

				// query to get everything
				$sql = 'SELECT * FROM modules';
			}
		}
		// determine details about this module from database
		// modulename should match the directory name

		if ($module || !$modulelist->is_loaded()) {
			$sth = FreePBX::Database()->prepare($sql);
			if($module) {
				$sth->execute(array($module));
			} else {
				$sth->execute();
			}
			$results = $sth->fetchAll(\PDO::FETCH_ASSOC);

			if (is_array($results)) {
				foreach($results as $row) {
					if (isset($modules[ $row['modulename'] ])) {
						if ($row['enabled'] != 0) {

							// check if file and registered versions are the same
							// version_compare returns 0 if no difference
							if (version_compare_freepbx($row['version'], $modules[ $row['modulename'] ]['version']) == 0) {
								$modules[ $row['modulename'] ]['status'] = MODULE_STATUS_ENABLED;
							} else {
								$modules[ $row['modulename'] ]['status'] = MODULE_STATUS_NEEDUPGRADE;
							}

						} else {
							$modules[ $row['modulename'] ]['status'] = MODULE_STATUS_DISABLED;
						}

					} else {
						// no directory for this db entry
						$modules[ $row['modulename'] ]['status'] = MODULE_STATUS_BROKEN;
						$modules[ $row['modulename'] ]['repo'] = 'broken';
					}
					$modules[ $row['modulename'] ]['dbversion'] = $row['version'];
					$modules[ $row['modulename'] ]['track'] = $this->get_track($row['modulename']);
					$modules[ $row['modulename'] ]['signature'] = !empty($row['signature']) ? json_decode($row['signature'],true) : array();
				}
			}

			// "builtin" module is always enabled
			$modules['builtin']['status'] = MODULE_STATUS_ENABLED;
		}

		if (!$module && !$modulelist->is_loaded()) {
			$modulelist->initialize($modules);
		}

		$module_array = $modulelist->get();
		//ksort for consistency throughout freepbx
		if ($status === false) {
			if (!$module) {
				ksort($module_array);
				$this->getInfoCache[$cacheKey] = $module_array;
				return $module_array;
			} else {
				ksort($modules);
				$this->getInfoCache[$cacheKey] = $modules;
				return $modules;
			}
		} else {
			if (!$module) {
				$modules =  $module_array;
			}
			if (!is_array($status)) {
				// make a one element array so we can use in_array below
				$status = array($status);
			}
			$modules = is_array($modules) ? $modules : array();

			foreach (array_keys($modules) as $name) {
				if (!in_array($modules[$name]['status'], $status)) {
					// not found in the $status array, remove it
					unset($modules[$name]);
					continue;
				}
			}
			ksort($modules);
			$this->getInfoCache[$cacheKey] = $modules;
			return $modules;
		}
	}

	/**
	* Smart Dependency Resolver
	* This is smart because it will auto download/install/enable whatever it needs tool
	* @param string $modulename The raw module name
	* @param string $callback   The function callback name
	*/
	function resolveDependencies($modulename,$callback) {
		$devmode = FreePBX::Config()->get('DEVEL');
		$force = false;
		if (is_array($errors = $this->checkdepends($modulename))) {
			$depends = $this->modDepends;
			if(!empty($depends)) {
				foreach($depends as $m) {
					$module = $m['module'];
					$version = $m['version'];
					out(sprintf(_("Detected Missing Dependency of: %s %s"),$module,$version));
					$this->getinfo(false,false,false);
					$m = $this->getinfo($module);
					if(!empty($m[$module])) {
						if(!$devmode && ((!empty($m[$module]['dbversion']) && version_compare_freepbx($m[$module]['dbversion'],$version,'<')) || version_compare_freepbx($m[$module]['version'],$version,'<'))) {
							out(sprintf(_("Downloading Missing Dependency of: %s %s"),$module,$version));
							if (is_array($errors = $this->download($module,$force,$callback))) {
								out(_("The following error(s) occured:"));
								out(' - '.implode("\n - ",$errors));
								return false;
							} else {
								out("Module ".$module." successfully downloaded");
								if(!$this->resolveDependencies($module,$callback)) {
									return false;
								}
								out(sprintf(_("Installing Missing Dependency of: %s %s"),$module,$version));
								if (is_array($errors = $this->install($module,$force))) {
									out(_("The following error(s) occured:"));
									out(' - '.implode("\n - ",$errors));
									return false;
								} else {
									out(sprintf(_("Installed Missing Dependency of: %s %s"),$module,$version));
								}
							}
						} elseif(version_compare_freepbx($m[$module]['version'],$version,'>=')) {
							out(sprintf(_("Found local Dependency of: %s %s"),$module,$m[$module]['version']));
						} elseif($devmode) {
							out(_("Could not find dependency locally and 'Developer Mode' is enabled so will not check online"));
							return false;
						}
						if(!$this->resolveDependencies($module,$callback)) {
							return false;
						}
						switch($m[$module]['status']) {
							case MODULE_STATUS_NOTINSTALLED:
								out(sprintf(_("Installing Missing Dependency of: %s %s"),$module,$version));
								if (is_array($errors = $this->install($module,$force))) {
									out(_("The following error(s) occured:"));
									out(' - '.implode("\n - ",$errors));
									return false;
								} else {
									out(sprintf(_("Installed Missing Dependency of: %s %s"),$module,$version));
								}
							break;
							case MODULE_STATUS_DISABLED:
								out(sprintf(_("Enabling Missing Dependency of: %s %s"),$module,$version));
								if (is_array($errors = $this->enable($module))) {
									out(_("The following error(s) occured:"));
									out(' - '.implode("\n - ",$errors));
									return false;
								}
								out(sprintf(_("Missing Dependency %s %s successfully enabled"),$module,$version));
							break;
							case MODULE_STATUS_NEEDUPGRADE:
								out(sprintf(_("Installing Missing Dependency of: %s %s"),$module,$version));
								if (is_array($errors = $this->install($module,$force))) {
									out(_("The following error(s) occured:"));
									out(' - '.implode("\n - ",$errors));
									return false;
								} else {
									out(sprintf(_("Installed Missing Dependency of: %s %s"),$module,$version));
								}
							break;
							case MODULE_STATUS_BROKEN:
								out(sprintf(_("Downloading Missing Dependency of: %s %s"),$module,$version));
								if (is_array($errors = $this->download($module,$force,$callback))) {
									out(_("The following error(s) occured:"));
									out(' - '.implode("\n - ",$errors));
									return false;
								} else {
									out("Module ".$module." successfully downloaded");
									if(!$this->resolveDependencies($module,$callback)) {
										return false;
									}
									out(sprintf(_("Installing Missing Dependency of: %s %s"),$module,$version));
									if (is_array($errors = $this->install($module,$force))) {
										out(_("The following error(s) occured:"));
										out(' - '.implode("\n - ",$errors));
										return false;
									} else {
										out(sprintf(_("Installed Missing Dependency of: %s %s"),$module,$version));
									}
								}
							break;
							case MODULE_STATUS_ENABLED:
									out(sprintf(_("Dependency module %s  version %s is Enabled "),$module,$version));
							break;
							default:
								out(sprintf(_("Dependency %s has an unknown state of : %s %s"),$module,$version, $m[$module]['status']));
								return false;
							break;
						}
					} else {
						out(sprintf(_("Downloading Missing Dependency of: %s %s"),$module,$version));
						if (is_array($errors = $this->download($module,$force,$callback))) {
							out(_("The following error(s) occured:"));
							out(' - '.implode("\n - ",$errors));
							return false;
						} else {
							out("Module ".$module." successfully downloaded");
							if(!$this->resolveDependencies($module,$callback)) {
								return false;
							}
							out(sprintf(_("Installing Missing Dependency of: %s %s"),$module,$version));
							if (is_array($errors = $this->install($module,$force))) {
								out(_("The following error(s) occured:"));
								out(' - '.implode("\n - ",$errors));
								return false;
							} else {
								out(sprintf(_("Installed Missing Dependency of: %s %s"),$module,$version));
							}
						}
					}
				}
			}
		}
		return true;
	}

	/** Check if a module meets dependencies.
	* @param  mixed  The name of the module, or the modulexml Array
	* @return mixed  Returns true if dependencies are met, or an array
	*                containing a list of human-readable errors if not.
	*                NOTE: you must use strict type checking (===) to test
	*                for true, because  array() == true !
	*/
	function checkdepends($modulename) {
		$this->modDepends = array();
		// check if we were passed a modulexml array, or a string (name)
		// ensure $modulexml is the modules array, and $modulename is the name (as a string)
		if (is_array($modulename)) {
			$modulexml = $modulename;
			$modulename = $modulename['rawname'];
		} else {
			$modulexml = $this->getinfo($modulename);
			$modulexml = $modulexml[$modulename];
		}

		$errors = array();

		// special handling for engine
		$engine_dependency = false; // if we've found ANY engine dependencies to check
		$engine_matched = false; // if an engine dependency has matched
		$engine_errors = array(); // the error strings for engines

		if (isset($modulexml['depends']) && is_array($modulexml['depends'])) {
			foreach ($modulexml['depends'] as $type => $requirements) {
				// if only a single item, make it an array so we can use the same code as for multiple items
				// this is because if there is  <module>a</module><module>b</module>  we will get array('module' => array('a','b'))
				if (!is_array($requirements)) {
					$requirements = array($requirements);
				}

				foreach ($requirements as $value) {
					switch ($type) {
						case 'version':
							if (preg_match('/^(lt|le|gt|ge|==|=|eq|!=|ne)?\s*(\d*[beta|alpha|rc|RC]?\d+(\.[^\.]+)*)$/i', $value, $matches)) {
								// matches[1] = operator, [2] = version
								$installed_ver = getversion();
								$operator = (!empty($matches[1]) ? $matches[1] : 'ge'); // default to >=
								$compare_ver = $matches[2];
								if (version_compare_freepbx($installed_ver, $compare_ver, $operator) ) {
									// version is good
								} else {
									$errors[] = $this->_comparison_error_message('FreePBX', $compare_ver, $installed_ver, $operator);
								}
							}
						break;
						case 'phpversion':
							/* accepted formats
							<depends>
							<phpversion>5.1.0<phpversion>       TRUE: if php is >= 5.1.0
							<phpversion>gt 5.1.0<phpversion>    TRUE: if php is > 5.1.0
							</depends>
							*/
							if (preg_match('/^(lt|le|gt|ge|==|=|eq|!=|ne)?\s*(\d*[beta|alpha|rc|RC]?\d+(\.[^\.]+)*)$/i', $value, $matches)) {
								// matches[1] = operator, [2] = version
								$installed_ver = phpversion();
								$operator = (!empty($matches[1]) ? $matches[1] : 'ge'); // default to >=
								$compare_ver = $matches[2];
								if (version_compare($installed_ver, $compare_ver, $operator) ) {
									// php version is good
								} else {
									$errors[] = $this->_comparison_error_message('PHP', $compare_ver, $installed_ver, $operator);
								}
							}
						break;
						case 'phpcomponent':
							/* accepted formats
							<depends>
							<phpcomponent>zlib<phpversion>        TRUE: if extension zlib is loaded
							<phpcomponent>zlib 1.2<phpversion>    TRUE: if extension zlib is loaded and >= 1.2
							<phpcomponent>zlib gt 1.2<phpversion> TRUE: if extension zlib is loaded and > 1.2
							</depends>
							*/
							$phpcomponents = explode('||',$value);
							$newerrors = array();
							foreach($phpcomponents as $value) {
								if (preg_match('/^([a-z0-9_]+|Zend (Optimizer|Guard Loader))(\s+(lt|le|gt|ge|==|=|eq|!=|ne)?\s*(\d+(\.\d*[beta|alpha|rc|RC]*\d+)+))?$/i', $value, $matches)) {
									// matches[1] = extension name, [3]=comparison operator, [4] = version
									$compare_ver = isset($matches[4]) ? $matches[4] : '';
									if (extension_loaded($matches[1])) {
										if (empty($compare_ver)) {
											// extension is loaded and no version specified
										} else {
											if (($installed_ver = phpversion($matches[1])) != '') {
												$operator = (!empty($matches[3]) ? $matches[3] : 'ge'); // default to >=
												if (version_compare($installed_ver, $compare_ver, $operator) ) {
													// version is good
												} else {
													$newerrors[] = $this->_comparison_error_message("PHP Component ".$matches[1], $compare_ver, $installed_ver, $operator);
												}
											} else {
												$newerrors[] = $this->_comparison_error_message("PHP Component ".$matches[1], $compare_ver, "<no version info>", $operator);
											}
										}
									} else {
										if ($compare_version == '') {
											$newerrors[] = sprintf(_('PHP Component %s is required but missing from your PHP installation.'), $matches[1]);
										} else {
											$newerrors[] = sprintf(_('PHP Component %s version %s is required but missing from you PHP installation.'), $matches[1], $compare_version);
										}
									}
								}
							}
							if (count($newerrors) == count($phpcomponents)) {
								$errors = array_merge($errors,$newerrors);
							}
						break;
						case 'module':
							// Modify to allow versions such as 2.3.0beta1.2
							if (preg_match('/^([a-z0-9_]+)(\s+(lt|le|gt|ge|==|=|eq|!=|ne)?\s*(\d+(\.\d*[beta|alpha|rc|RC]*\d+)+))?$/i', $value, $matches)) {
								// matches[1] = modulename, [3]=comparison operator, [4] = version
								$modules = $this->getinfo($matches[1]);
								if (isset($modules[$matches[1]])) {
									$mod = isset($modules[$matches[1]]['rawname']) ? $modules[$matches[1]]['rawname'] : $matches[1];
									$needed_module = "<strong>".(isset($modules[$matches[1]]['name'])?$modules[$matches[1]]['name']:$matches[1])."</strong>";
									$compare_ver = !empty($matches[4]) ? $matches[4] : null;
									switch ($modules[$matches[1]]['status'] ) {
										case MODULE_STATUS_ENABLED:
											if (!empty($compare_ver)) {
												// also doing version checking
												$installed_ver = $modules[$matches[1]]['dbversion'];
												$operator = (!empty($matches[3]) ? $matches[3] : 'ge'); // default to >=

												if (version_compare_freepbx($installed_ver, $compare_ver, $operator) ) {
													// version is good
												} else {
													$errors[$mod] = $this->_comparison_error_message($needed_module.' module', $compare_ver, $installed_ver, $operator);
													$this->modDepends[] = array("module" => $mod, "version" => $compare_ver);
												}
											}
										break;
										case MODULE_STATUS_BROKEN:
											$errors[$mod] = sprintf(_('The Module Named "%s" is required, but yours is broken. You should reinstall it and try again.'), $needed_module);
											$this->modDepends[] = array("module" => $mod, "version" => $compare_ver);
										break;
										case MODULE_STATUS_DISABLED:
											$errors[$mod] = sprintf(_('The Module Named "%s" is required, but yours is disabled.'), $needed_module);
											$this->modDepends[] = array("module" => $mod, "version" => $compare_ver);
										break;
										case MODULE_STATUS_NEEDUPGRADE:
											$errors[$mod] = sprintf(_('The Module Named "%s" is required, but yours is disabled because it needs to be upgraded. Please upgrade %s first, and then try again.'), $needed_module, $needed_module);
											$this->modDepends[] = array("module" => $mod, "version" => $compare_ver);
										break;
										default:
										case MODULE_STATUS_NOTINSTALLED:
											$errors[$mod] = sprintf(_('The Module Named "%s" is required, yours is not installed.'), $needed_module);
											$this->modDepends[] = array("module" => $mod, "version" => $compare_ver);
										break;
									}
								} else {
									$mod = $matches[1];
									$errors[$mod] = sprintf(_('The Module Named "%s" is required.'), $mod);
									$this->modDepends[] = array("module" => $mod);
								}
							}
						break;
						case 'file': // file exists
							// replace embedded amp_conf %VARIABLES% in string

							$file = $this->_ampconf_string_replace($value);

							if (!file_exists( $file )) {
								$errors[] = sprintf(_('The File "%s" must exist.'), $file);
							}
						break;
						case 'engine':
							/****************************
							*  NOTE: there is special handling for this check. We want to "OR" conditions, instead of
							*        "AND"ing like the rest of them.
							*/

							// we found at least one engine, so mark that we're matching this
							$engine_dependency = true;

							if (preg_match('/^([a-z0-9_]+)(\s+(lt|le|gt|ge|==|=|eq|!=|ne)?\s*(\d+(\.[^\.]+)*))?$/i', $value, $matches)) {
								// matches[1] = engine, [3]=comparison operator, [4] = version
								$operator = (!empty($matches[3]) ? $matches[3] : 'ge'); // default to >=

								$engine = engine_getinfo();
								if (($engine['engine'] == $matches[1]) && (empty($matches[4]) || version_compare($engine['version'], $matches[4], $operator))) {
									$engine_matched = true;
								} else {
									// add it to the error messages
									if ($matches[4]) {
										// version specified
										$operator_friendly = str_replace(array('gt','ge','lt','le','eq','ne'), array('>','>=','<','<=','=','not ='), $operator);
										$engine_errors[] = $matches[1].' ('.$operator_friendly.' '.$matches[4].')';
									} else {
										// no version
										$engine_errors[] = $matches[1];
									}
								}
							}
						break;
					}
				}
			}

			// special handling for engine
			// if we've had at least one engine dependency check, and no engine dependencies matched, we have an error
			if ($engine_dependency && !$engine_matched) {

				$engineinfo = engine_getinfo();
				$yourengine = $engineinfo['engine'].' '.$engineinfo['version'];
				// print it nicely
				if (count($engine_errors) == 1) {
					$errors[] = sprintf(_('Requires engine %s, you have: %s'),$engine_errors[0],$yourengine);
				} else {
					$errors[] = sprintf(_('Requires one of the following engines: %s; you have: %s'),implode(', ', $engine_errors),$yourengine);
				}
			}
		}

		if (count($errors) > 0) {
			return $errors;
		} else {
			return true;
		}
	}

	function _comparison_error_message($module, $reqversion, $version, $operator) {
		switch ($operator) {
			case 'lt': case '<':
				return sprintf(_('A %s version below %s is required, you have %s'), $module, $reqversion, $version);
			break;
			case 'le': case '<=';
				return sprintf(_('%s version %s or below is required, you have %s'), $module, $reqversion, $version);
			break;
			case 'gt': case '>';
				return sprintf(_('A %s version newer than %s required, you have %s'), $module, $reqversion, $version);
			break;
			case 'ne': case '!=': case '<>':
				return sprintf(_('Your %s version (%s) is incompatible.'), $version, $reqversion);
			break;
			case 'eq': case '==': case '=':
				return sprintf(_('Only %s version %s is compatible, you have %s'), $module, $reqversion, $version);
			break;
			default:
			case 'ge': case '>=':
				return sprintf(_('%s version %s or higher is required, you have %s'), $module, $reqversion, $version);
		}
	}

	/**
	* Finds all the enabled modules that depend on a given module
	* @param  mixed  The name of the module, or the modulexml Array
	* @return array  Array containing the list of modules, or false if no dependencies
	*/
	function reversedepends($modulename) {
		// check if we were passed a modulexml array, or a string (name)
		// ensure $modulename is the name (as a string)
		if (is_array($modulename)) {
			$modulename = $modulename['rawname'];
		}

		$info = $this->getinfo($modulename);
		if($info[$modulename]['status'] == MODULE_STATUS_BROKEN) {
			return false;
		}

		$modules = $this->getinfo(false, array(MODULE_STATUS_ENABLED, MODULE_STATUS_NEEDUPGRADE));

		$depends = array();

		$modules = is_array($modules) ? $modules : array();
		foreach (array_keys($modules) as $name) {
			if (!empty($modules[$name]['depends']) && is_array($modules[$name]['depends'])) {
				foreach ($modules[$name]['depends'] as $type => $requirements) {
					if ($type == 'module') {
						// if only a single item, make it an array so we can use the same code as for multiple items
						// this is because if there is  <module>a</module><module>b</module>  we will get array('module' => array('a','b'))
						if (!is_array($requirements)) {
							$requirements = array($requirements);
						}
						if(!empty($requirements) && is_array($requirements)) {
							foreach ($requirements as $value) {

								if (preg_match('/^([a-z0-9_]+)(\s+(lt|le|gt|ge|==|=|eq|!=|ne)?\s*(\d+(\.\d*[beta|alpha|rc|RC]*\d+)+))?$/i', $value, $matches)) {
									// matches[1] = modulename, [3]=comparison operator, [4] = version

									// note, we're not checking version here. Normally this function is used when
									// uninstalling a module, so it doesn't really matter anyways, and version
									// dependency should have already been checked when the module was installed
									if ($matches[1] == $modulename) {
										$depends[] = $name;
									}
								}
							}
						}
					}
				}
			}
		}

		return (count($depends) > 0) ? $depends : false;
	}

	/**
	* Enables a module
	* @param string    The name of the module to enable
	* @param bool      If true, skips status and dependency checks
	* @param bool	  ignore conflicts
	* @return  mixed   True if succesful, array of error messages if not succesful
	*/
	function enable($modulename, $force = false, $ignorechecks = true) { // was enableModule
		$this->modDepends = array();
		global $db;
		$modules = $this->getinfo($modulename);

		if ($modules[$modulename]['status'] == MODULE_STATUS_ENABLED) {
			return array(_("Module ".$modulename." is already enabled"));
		}

		// doesn't make sense to skip this on $force - eg, we can't enable a non-installed or broken module
		if ($modules[$modulename]['status'] != MODULE_STATUS_DISABLED) {
			return array(_("Module ".$modulename." cannot be enabled"));
		}

		$mod = FreePBX::GPG()->verifyModule($modulename);
		$revoked = $mod['status'] & FreePBX\GPG::STATE_REVOKED;
		if($revoked) {
			return array(_("Module ".$modulename." has a revoked signature and cannot be enabled"));
		}

		if (!$force) {
			if (($errors = $this->checkdepends($modules[$modulename])) !== true) {
				return $errors;
			}
		}

		if(!$force && !$ignorechecks) {
			$FreePBX = FreePBX::Create();
			$bmoModules = $FreePBX->Modules;
			// check dependencies
			$errors = $bmoModules->checkConflicts($modules[$modulename]);
			if(!empty($errors['breaking'])) {
				$final = [];
				foreach($errors['issues'] as $module => $issues){
					foreach ($issues as $issue) {
						$final[] = sprintf('%s: %s',$module, $issue);
					}
				}
				return $final;
			}
		}

		// disabled (but doesn't needupgrade or need install), and meets dependencies
		$this->setenabled($modulename, true);
		needreload();
		\FreePBX::Modulelist()->invalidate();

		// run the scripts
		if (!$this->_runscripts($modulename, 'enable', $modules)) {
			return array(_("Failed to run enable method"));
		}

		//enable all jobs
		\FreePBX::Job()->setEnabledByModule($modulename, true);

		return true;
	}

	/**
	* Downloads the latest version of a module and extracts it to the directory
	* @param string    The location of the module to install
	* @param bool      If true, skips status and dependency checks
	* @param string    The name of a callback function to call with progress updates.
	*                   function($action, $params). Possible actions:
	*                     getinfo: while downloading modules.xml
	*                     downloading: while downloading file; params include 'read' and 'total'
	*                     untar: before untarring
	*                     done: when complete
	* @return  mixed   True if succesful, array of error messages if not succesful
	*/
	function download($moduledata, $force = false, $progress_callback = null, $override_svn = false, $override_xml = false, $ignorechecks = false) {
		$this->getInfoCache = array(); //invalidate local
		$this->notFound = false;
		global $amp_conf;
		if(!is_array($moduledata)) {
			if(!empty($override_xml) && file_exists($override_xml)) {
				$data = file_get_contents($override_xml);
				$parser = new xml2ModuleArray($data);
				$xml = $parser->parseAdvanced($data);
				if(!isset($xml[$modeuledata])) {
					$errors[] = _("Module XML was not in the proper format");
					return $errors;
				}
				$modulexml = $xml[$modeuledata];
			} else {
				$modulexml = $this->getonlinexml($moduledata, false, false , $force);
			}
		} elseif(!empty($moduledata['version'])) {
			$modulexml = $moduledata;
		} else {
			$errors[] = _("Module XML was not in the proper format");
			return $errors;
		}
		if(!file_exists($amp_conf['AMPWEBROOT']."/admin/modules/_cache")) {
			if(!mkdir($amp_conf['AMPWEBROOT']."/admin/modules/_cache")) {
				$errors[] = sprintf(_("Could Not Create Cache Folder: %s"),$amp_conf['AMPWEBROOT']."/admin/modules/_cache");
				return $errors;
			}
		}

		if(empty($modulexml['rawname'])) {
			$errors[] = _("Retrieved Module XML Was Empty");
			return $errors;
		}

		if(!$force && !$ignorechecks) {
			$bmoModules = \FreePBX::Modules();
			// check dependencies
			$errors = $bmoModules->checkConflicts($modulexml);
			if(!empty($errors['breaking'])) {
				$final = [];
				foreach($errors['issues'] as $module => $issues){
					foreach ($issues as $issue) {
						$final[] = sprintf('%s: %s',$module, $issue);
					}
				}
				return $final;
			}
		}

		$modulename = $modulexml['rawname'];

		set_time_limit($this->maxTimeLimit);

		// size of download blocks to fread()
		// basically, this controls how often progress_callback is called
		$download_chunk_size = 12*1024;

		// invoke progress callback
		if (!is_array($progress_callback) && function_exists($progress_callback)) {
			$progress_callback('getinfo', array('module'=>$modulename));
		} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
			$progress_callback[0]->{$progress_callback[1]}('getinfo', array('module'=>$modulename));
		}

		$file = basename($modulexml['location']);
		$filename = $amp_conf['AMPWEBROOT']."/admin/modules/_cache/".$file;
		// if we're not forcing the download, and a file with the target name exists..
		if (!$force && file_exists($filename)) {
			if (!is_array($progress_callback) && function_exists($progress_callback)) {
				$progress_callback('verifying', array('module'=>$modulename, 'status' => 'start'));
			} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
				$progress_callback[0]->{$progress_callback[1]}('verifying', array('module'=>$modulename, 'status' => 'start'));
			}
			if(!empty($modulexml['signed']['type']) && $modulexml['signed']['type'] == 'gpg' && $modulexml['signed']['sha1'] == sha1_file($filename)) {
				try {
					if(!FreePBX::GPG()->verifyFile($filename)) {
						if (!is_array($progress_callback) && function_exists($progress_callback)) {
							$progress_callback('verifying', array('module'=>$modulename, "status" => "redownload"));
						} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
							$progress_callback[0]->{$progress_callback[1]}('verifying', array('module'=>$modulename, "status" => "redownload"));
						}
						unlink($filename);
					}
				} catch(\Exception $e) {
					if (!is_array($progress_callback) && function_exists($progress_callback)) {
						$progress_callback('verifying', array('module'=>$modulename, "status" => "redownload"));
					} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
						$progress_callback[0]->{$progress_callback[1]}('verifying', array('module'=>$modulename, "status" => "redownload"));
					}
					unlink($filename);
				}
				try {
					$filename = FreePBX::GPG()->getFile($filename);
					if(!file_exists($filename)) {
						if (!is_array($progress_callback) && function_exists($progress_callback)) {
							$progress_callback('verifying', array('module'=>$modulename, "status" => "redownload"));
						} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
							$progress_callback[0]->{$progress_callback[1]}('verifying', array('module'=>$modulename, "status" => "redownload"));
						}
					}
				} catch(\Exception $e) {
					if (!is_array($progress_callback) && function_exists($progress_callback)) {
						$progress_callback('verifying', array('module'=>$modulename, "status" => "redownload"));
					} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
						$progress_callback[0]->{$progress_callback[1]}('verifying', array('module'=>$modulename, "status" => "redownload"));
					}
					if (file_exists($filename)) {
						unlink($filename);
					}
				}
			}
			// We might already have it! Let's check the MD5.
			if ((isset($modulexml['sha1sum']) && $modulexml['sha1sum'] == sha1_file($filename)) || (isset($modulexml['md5sum']) && $modulexml['md5sum'] == md5_file($filename))) {
				if (!is_array($progress_callback) && function_exists($progress_callback)) {
					$progress_callback('verifying', array('module'=>$modulename, "status" => "verified"));
				} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
					$progress_callback[0]->{$progress_callback[1]}('verifying', array('module'=>$modulename, "status" => "verified"));
				}
				// Note, if there's no MD5 information, it will redownload
				// every time. Otherwise theres no way to avoid a corrupt
				// download

				// invoke progress callback
				if (!is_array($progress_callback) && function_exists($progress_callback)) {
					$progress_callback('untar', array('module'=>$modulename, 'size'=>filesize($filename)));
				} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
					$progress_callback[0]->{$progress_callback[1]}('untar', array('module'=>$modulename, 'size'=>filesize($filename)));
				}

				/* We will explode the tarball in the cache directory and then once successful, remove the old module before before
				* moving the new one over. This way, things like removed files end up being removed instead of laying around
				*
				* TODO: save old module being replaced, if there is an old one.
				*/
				exec("rm -rf ".$amp_conf['AMPWEBROOT']."/admin/modules/_cache/$modulename", $output, $exitcode);
				if ($exitcode != 0) {
					return array(sprintf(_('Could not remove %s to install new version'), $amp_conf['AMPWEBROOT'].'/admin/modules/_cache/'.$modulename));
				}
				exec("tar zxf ".escapeshellarg($filename)." -C ".escapeshellarg($amp_conf['AMPWEBROOT'].'/admin/modules/_cache/'), $output, $exitcode);
				if (posix_getuid() == 0) {
					exec('chown -R '.escapeshellarg($amp_conf['AMPASTERISKWEBUSER'].":".$amp_conf['AMPASTERISKWEBGROUP']).' '.escapeshellarg($amp_conf['AMPWEBROOT']."/admin/modules/_cache/$modulename"));
				}
				if ($exitcode != 0) {
					freepbx_log(FPBX_LOG_ERROR,sprintf(_("failed to open %s module archive into _cache directory."),$filename));
					return array(sprintf(_('Could not untar %s to %s'), $filename, $amp_conf['AMPWEBROOT'].'/admin/modules/_cache'));
				} else {
					// since untarring was successful, remvove the tarball so they do not accumulate
					if (unlink($filename) === false) {
						freepbx_log(FPBX_LOG_WARNING,sprintf(_("failed to delete %s from cache directory after opening module archive."),$filename));
					}
				}
				exec("rm -rf ".$amp_conf['AMPWEBROOT']."/admin/modules/$modulename", $output, $exitcode);
				if ($exitcode != 0) {
					return array(sprintf(_('Could not remove old module %s to install new version'), $amp_conf['AMPWEBROOT'].'/admin/modules/'.$modulename));
				}
				exec("mv ".$amp_conf['AMPWEBROOT']."/admin/modules/_cache/$modulename ".$amp_conf['AMPWEBROOT']."/admin/modules/$modulename", $output, $exitcode);
				if ($exitcode != 0) {
					return array(sprintf(_('Could not move %s to %s'), $amp_conf['AMPWEBROOT']."/admin/modules/_cache/$modulename", $amp_conf['AMPWEBROOT'].'/admin/modules/'));
				}

				// invoke progress_callback
				if (!is_array($progress_callback) && function_exists($progress_callback)) {
					$progress_callback('done', array('module'=>$modulename));
				} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
					$progress_callback[0]->{$progress_callback[1]}('done', array('module'=>$modulename));
				}

				return true;
			} else {
				if (!is_array($progress_callback) && function_exists($progress_callback)) {
					$progress_callback('verifying', array('module'=>$modulename, "status" => "redownload"));
				} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
					$progress_callback[0]->{$progress_callback[1]}('verifying', array('module'=>$modulename, "status" => "redownload"));
				}
				unlink($filename);
			}
		}

		if (!($fp = @fopen($filename,"w"))) {
			return array(sprintf(_("Error opening %s for writing"), $filename));
		}

		if ($override_svn) {
			$url_list = array($override_svn.$modulexml['location']);
		} else {
			if (parse_url($modulexml["location"], PHP_URL_SCHEME) === 'https') {
				// absolute https URL was provided
				$url_list = array($modulexml['location']);
			} else {
				$urls = $this->generate_remote_urls("/modules/".$modulexml['location'], true);
				foreach($urls['mirrors'] as $url) {
					$url_list[] = $url.$urls['path'];
				}
			}
		}

		// Check each URL until get_headers_assoc() returns something intelligible. We then use
		// that URL and hope the file is there, we won't check others.
		//
		$headers = false;
		foreach ($url_list as $u) {
			$headers = get_headers_assoc($u);
			if (!empty($headers)) {
				$url = $u;
				break;
			}
			freepbx_log(FPBX_LOG_ERROR,sprintf(_('Failed download module tarball from %s, server may be down'),$u));
		}
		if (!$headers || !$url) {
			return array(sprintf(_("Unable to connect to servers from URLs provided: %s"), implode(',',$url_list)));
		}

		// TODO: do we want to make more robust past this point:
		// At this point we have settled on a specific URL that we can reach, if the file isn't there we won't try
		// other servers to check for it. The assumption is that no backup server will have it either at this point
		// If we wanted to make this more robust we could go back and try other servers. This code is a bit tangled
		// so some better factoring might help.
		//
		$totalread = 0;
		// invoke progress_callback
		if (!is_array($progress_callback) && function_exists($progress_callback)) {
			$progress_callback('downloading', array('module'=>$modulename, 'read'=>$totalread, 'total'=>$headers['content-length']));
		} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
			$progress_callback[0]->{$progress_callback[1]}('downloading', array('module'=>$modulename, 'read'=>$totalread, 'total'=>$headers['content-length']));
		}

		$hooks = new Requests_Hooks();
		$hooks->register('request.progress', function($data,$response_bytes,$response_byte_limit) use($progress_callback,$modulename,$headers) {
			if (!is_array($progress_callback) && function_exists($progress_callback)) {
				$progress_callback('downloading', array('module'=>$modulename, 'read'=>$response_bytes, 'total'=>$headers['content-length']));
			} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
				$progress_callback[0]->{$progress_callback[1]}('downloading', array('module'=>$modulename, 'read'=>$response_bytes, 'total'=>$headers['content-length']));
			}
		});

		$requests = FreePBX::Curl()->requests($url);
		$options = array(
			'hooks' => $hooks,
			'timeout' => 1800, // Allow up to 1800 seconds (30 minutes) for the download to complete
		);
		$response = $requests->post('', array(), $urls['options'], $options);
		file_put_contents($filename,$response->body);

		if (is_readable($filename) !== TRUE ) {
			return array(sprintf(_('Unable to save %s'),$filename));
		}

		if(!empty($modulexml['signed']['type']) && $modulexml['signed']['type'] == 'gpg') {
				if($modulexml['signed']['sha1'] != sha1_file($filename)) {
						return array(sprintf(_('File Integrity failed for %s - aborting (sha1 did not match)'), $filename));
				}
				try {
					if(!FreePBX::GPG()->trustFreePBX()) {
						return array(sprintf(_('Cant Verify downloaded module %s. Unable to trust GPG Key - aborting (Cause: No Cause Given)'), $filename));
					}
				}catch(\Exception $e) {
					return array(sprintf(_('Cant Verify downloaded module %s. Unable to trust GPG Key - aborting (Cause: %s)'), $filename, $e->getMessage()));
				}
				try {
					if(!FreePBX::GPG()->verifyFile($filename)) {
						if(!FreePBX::GPG()->refreshKeys() || !FreePBX::GPG()->trustFreePBX()) {
							return array(sprintf(_('File Integrity failed for %s - aborting (GPG Verify File check failed)'), $filename));
						} else {
							if(!FreePBX::GPG()->verifyFile($filename)) {
								return array(sprintf(_('File Integrity failed for %s - aborting (GPG Verify File check failed)'), $filename));
							}
						}
					}
				}catch(\Exception $e) {
					return array(sprintf(_('File Integrity failed for %s - aborting (Cause: %s)'), $filename, $e->getMessage()));
				}
				try {
						$filename = FreePBX::GPG()->getFile($filename);
						if(!file_exists($filename)) {
								return array(sprintf(_('Could not find extracted module: %s'), $filename));
						}
				} catch(\Exception $e) {
						return array(sprintf(_('Unable to work with GPG file, message was: %s'), $e->getMessage()));
				}
		}
		// Check the MD5 info against what's in the module's XML
		if (!isset($modulexml['md5sum']) || empty($modulexml['md5sum'])) {
			//echo "<div class=\"error\">"._("Unable to Locate Integrity information for")." {$filename} - "._("Continuing Anyway")."</div>";
		} else if ($modulexml['md5sum'] != md5_file ($filename)) {
			unlink($filename);
			return array(sprintf(_('File Integrity failed for %s - aborting (md5sum did not match)'), $filename));
		}

		// Check the SHA1 info against what's in the module's XML
		if (!isset($modulexml['sha1sum']) || empty($modulexml['sha1sum'])) {
			//echo "<div class=\"error\">"._("Unable to Locate Integrity information for")." {$filename} - "._("Continuing Anyway")."</div>";
		} else if ($modulexml['sha1sum'] != sha1_file($filename)) {
			unlink($filename);
			return array(sprintf(_('File Integrity failed for %s - aborting (sha1 did not match)'), $filename));
		}

		// invoke progress callback
		if (!is_array($progress_callback) && function_exists($progress_callback)) {
			$progress_callback('untar', array('module'=>$modulename, 'size'=>filesize($filename)));
		} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
			$progress_callback[0]->{$progress_callback[1]}('untar', array('module'=>$modulename, 'size'=>filesize($filename)));
		}

		/* We will explode the tarball in the cache directory and then once successful, remove the old module before before
		* moving the new one over. This way, things like removed files end up being removed instead of laying around
		*/
		exec("rm -rf ".$amp_conf['AMPWEBROOT']."/admin/modules/_cache/$modulename", $output, $exitcode);
		if ($exitcode != 0) {
			return array(sprintf(_('Could not remove %s to install new version'), $amp_conf['AMPWEBROOT'].'/admin/modules/_cache/'.$modulename));
		}
		exec("tar zxf ".escapeshellarg($filename)." -C ".escapeshellarg($amp_conf['AMPWEBROOT'].'/admin/modules/_cache/'), $output, $exitcode);
		if ($exitcode != 0) {
			freepbx_log(FPBX_LOG_ERROR,sprintf(_("failed to open %s module archive into _cache directory."),$filename));
			return array(sprintf(_('Could not untar %s to %s'), $filename, $amp_conf['AMPWEBROOT'].'/admin/modules/_cache'));
		} else {
			// since untarring was successful, remvove the tarball so they do not accumulate
			if (unlink($filename) === false) {
				freepbx_log(FPBX_LOG_WARNING,sprintf(_("failed to delete %s from cache directory after opening module archive."),$filename));
			}
		}

		if (posix_getuid() == 0) {
			exec('chown -R '.escapeshellarg($amp_conf['AMPASTERISKWEBUSER'].":".$amp_conf['AMPASTERISKWEBGROUP']).' '.escapeshellarg($amp_conf['AMPWEBROOT']."/admin/modules/_cache/$modulename"));
		}

		$dest = $amp_conf['AMPWEBROOT']."/admin/modules/$modulename";
		exec("rm -rf $dest", $output, $exitcode);
		if ($exitcode != 0) {
			return array(sprintf(_('Could not remove old module %s to install new version'), $dest));
		}
		exec("mv ".$amp_conf['AMPWEBROOT']."/admin/modules/_cache/$modulename $dest", $output, $exitcode);
		if ($exitcode != 0) {
			return array(sprintf(_('Could not move %s to %s'), $amp_conf['AMPWEBROOT']."/admin/modules/_cache/$modulename", $dest));
		}

		// invoke progress_callback
		if (!is_array($progress_callback) && function_exists($progress_callback)) {
			$progress_callback('done', array('module'=>$modulename));
		} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
			$progress_callback[0]->{$progress_callback[1]}('done', array('module'=>$modulename));
		}

		// See if there is a signature folder in the module directory, and if there is, add any GPG keys
		// that are in there to our signature folder
		if (is_dir("$dest/signatures")) {
			// Find all the key files in there and copy them to the GPG key folder
			$gpg = \FreePBX::GPG();
			// Don't bother if there isn't a keydir.
			if (is_dir($gpg->keydir)) {
				$keys = glob("$dest/signatures/*.key");
				foreach ($keys as $k) {
					copy($k, $gpg->keydir);
				}
			}
		}

		return true;
	}

	function handledownload($module_location, $progress_callback = null) {
		global $amp_conf;
		$errors = array();

		if(!file_exists($amp_conf['AMPWEBROOT']."/admin/modules/_cache")) {
			if(!mkdir($amp_conf['AMPWEBROOT']."/admin/modules/_cache")) {
				$errors[] = sprintf(_("Could Not Create Cache Folder: %s"),$amp_conf['AMPWEBROOT']."/admin/modules/_cache");
				return $errors;
			}
		}

		set_time_limit($this->maxTimeLimit);

		// size of download blocks to fread()
		// basically, this controls how often progress_callback is called
		$download_chunk_size = 12*1024;

		// invoke progress callback
		if (!is_array($progress_callback) && function_exists($progress_callback)) {
			$progress_callback('getinfo', array('module'=>$modulename));
		} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
			$progress_callback[0]->{$progress_callback[1]}('getinfo', array('module'=>$modulename));
		}

		$file = basename(parse_url($module_location, PHP_URL_PATH));
		$filename = $amp_conf['AMPWEBROOT']."/admin/modules/_cache/".$file;

		// Check each URL until get_headers_assoc() returns something intelligible. We then use
		// that URL and hope the file is there, we won't check others.
		$headers = get_headers_assoc($module_location);
		if (empty($headers)) {
			return array(sprintf(_('Failed download module tarball from %s, server may be down'),$module_location));
		}

		if (!($fp = @fopen($filename,"w"))) {
			return array(sprintf(_("Error opening %s for writing"), $filename));
		}

		// TODO: do we want to make more robust past this point:
		// At this point we have settled on a specific URL that we can reach, if the file isn't there we won't try
		// other servers to check for it. The assumption is that no backup server will have it either at this point
		// If we wanted to make this more robust we could go back and try other servers. This code is a bit tangled
		// so some better factoring might help.
		//
		$totalread = 0;
		// invoke progress callback
		$headers['content-length'] = !empty($headers['content-length']) ? $headers['content-length'] : '0';
		if (!is_array($progress_callback) && function_exists($progress_callback)) {
			$progress_callback('downloading', array('read'=>$totalread, 'total'=>$headers['content-length']));
		} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
			$progress_callback[0]->{$progress_callback[1]}('downloading', array('read'=>$totalread, 'total'=>$headers['content-length']));
		}

		$hooks = new Requests_Hooks();
		$hooks->register('request.progress', function($data,$response_bytes,$response_byte_limit) use($progress_callback,$modulename,$headers) {
			if (!is_array($progress_callback) && function_exists($progress_callback)) {
				$progress_callback('downloading', array('module'=>$modulename, 'read'=>$response_bytes, 'total'=>$headers['content-length']));
			} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
				$progress_callback[0]->{$progress_callback[1]}('downloading', array('module'=>$modulename, 'read'=>$response_bytes, 'total'=>$headers['content-length']));
			}
		});
		$requests = FreePBX::Curl()->requests($module_location);
		$options = array(
			'hooks' => $hooks,
			'timeout' => 1800, // Allow up to 1800 seconds (30 minutes) for the download to complete
			'filename' => $filename, //avoid the requests object redundant decompress code
		);
		$response = $requests->get('', array(), $options);

		$errors = $this->_process_archive($filename,$progress_callback);
		if(is_array($errors) && count($errors)) {
			return $errors;
		}

		return true;
	}

	function handleupload($uploaded_file) {
		global $amp_conf;
		$errors = array();
		if(!file_exists($amp_conf['AMPWEBROOT']."/admin/modules/_cache")) {
			if(!mkdir($amp_conf['AMPWEBROOT']."/admin/modules/_cache")) {
				$errors[] = sprintf(_("Could Not Create Cache Folder: %s"),$amp_conf['AMPWEBROOT']."/admin/modules/_cache");
				return $errors;
			}
		}

		if(!empty($uploaded_file['error'])) {
			switch ($uploaded_file['error']) {
				case UPLOAD_ERR_INI_SIZE:
					$message = _("The uploaded file exceeds the upload_max_filesize directive in php.ini");
				break;
				case UPLOAD_ERR_FORM_SIZE:
					$message = _("The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form");
				break;
				case UPLOAD_ERR_PARTIAL:
					$message = _("The uploaded file was only partially uploaded");
				break;
				case UPLOAD_ERR_NO_FILE:
					$message = _("No file was uploaded");
				break;
				case UPLOAD_ERR_NO_TMP_DIR:
					$message = _("Missing a temporary folder");
				break;
				case UPLOAD_ERR_CANT_WRITE:
					$message = _("Failed to write file to disk");
				break;
				case UPLOAD_ERR_EXTENSION:
					$message = _("File upload stopped by extension");
				break;
				default:
					$message = _("Unknown upload error");
				break;
			}
			return array($message);
		}

		if (!isset($uploaded_file['tmp_name']) || !file_exists($uploaded_file['tmp_name'])) {
			$errors[] = _("Error finding uploaded file - check your PHP and/or web server configuration");
			return $errors;
		}

		$filename = $amp_conf['AMPWEBROOT']."/admin/modules/_cache/".$uploaded_file['name'];

		move_uploaded_file($uploaded_file['tmp_name'], $filename);

		$errors = $this->_process_archive($filename);
		if(is_array($errors) && count($errors)) {
			return $errors;
		}
		// finally, module installation is successful
		return true;
	}

	function _process_archive($filename,$progress_callback='') {
		global $amp_conf;

		if (is_readable($filename) !== TRUE ) {
			return array(sprintf(_('Unable to save %s'),$filename));
		}

		// invoke progress callback
		if(isset($progress_callback)) {
			if (!is_array($progress_callback) && function_exists($progress_callback)) {
				$progress_callback('untar', array('module'=>$modulename, 'size'=>filesize($filename)));
			} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
				$progress_callback[0]->{$progress_callback[1]}('untar', array('module'=>$modulename, 'size'=>filesize($filename)));
			}
		}

		$temppath = $amp_conf['AMPWEBROOT'].'/admin/modules/_cache/'.uniqid("upload");
		if (! @mkdir($temppath) ) {
			return array(sprintf(_("Error creating temporary directory: %s"), $temppath));
		}

		$extension = pathinfo($filename,PATHINFO_EXTENSION);
		if(preg_match('/^(gpg)$/', $extension)) {
			try {
				if(!FreePBX::GPG()->verifyFile($filename)) {
					return array(sprintf(_('File Integrity failed for %s - aborting (GPG Verify File check failed)'), $filename));
				}
			} catch(\Exception $e) {
				return array(sprintf(_('File Integrity failed for %s - aborting (Cause: %s)'), $filename, $e->getMessage()));
			}
			try {
				$filename = FreePBX::GPG()->getFile($filename);
				if(!file_exists($filename)) {
					return array(sprintf(_('Could not find extracted module: %s'), $filename));
				}
				$extension = pathinfo($filename,PATHINFO_EXTENSION);
			} catch(\Exception $e) {
				return array(sprintf(_('Unable to work with GPG file, message was: %s'), $e->getMessage()));
			}
		}
		switch(true) {
			case preg_match('/^(tgz|tar)$/', $extension) || preg_match('/(tar\.gz)$/', $filename):
				$err = exec("/usr/bin/env tar xf ".escapeshellarg($filename)." -C ".escapeshellarg($temppath), $output, $exitcode);
				if ($exitcode != 0) {
					return array(sprintf(_('Error from %s: %s'), 'tar', $err));
				}
			break;
			case preg_match('/^(zip)$/', $extension):
				exec("/usr/bin/env unzip", $output, $exitcode);
				if ($exitcode != 0) {
					return array(_("The binary unzip is not installed. Unable to work with zip file"));
				}
				$err = exec("/usr/bin/env unzip  ".escapeshellarg($filename)." -d ".escapeshellarg($temppath), $output, $exitcode);
				if ($exitcode != 0) {
					return array(sprintf(_('Error from %s: %s'), 'unzip', $err));
				}
			break;
			case preg_match('/^(bz2|bz|tbz2|tbz)$/', $extension):
				$err = exec("/usr/bin/env tar xjf ".escapeshellarg($filename)." -C ".escapeshellarg($temppath), $output, $exitcode);
				if ($exitcode != 0) {
					return array(sprintf(_('Error from %s: %s'), 'tar', $err));
				}
			break;
			default:
				return array(sprintf(_('Unknown file format of %s for %s, supported formats: tar,tgz,tar.gz,zip,bzip'),$extension,basename($filename)));
			break;
		}

		if (posix_getuid() == 0) {
			exec('chown -R '.escapeshellarg($amp_conf['AMPASTERISKWEBUSER'].":".$amp_conf['AMPASTERISKWEBGROUP']).' '.escapeshellarg($temppath));
		}

		// since untarring was successful, remvove the tarball so they do not accumulate
			if (unlink($filename) === false) {
			freepbx_log(FPBX_LOG_WARNING,sprintf(_("failed to delete %s from cache directory after opening module archive."),$filename));
			}

		if(!file_exists($temppath.'/module.xml')) {
			$dirs = glob($temppath.'/*',GLOB_ONLYDIR);
			if(count($dirs) > 1 || !count($dirs)) {
				return array(sprintf(_('Incorrect Number of Directories for %s'),$filename));
			}
			$archivepath = $dirs[0];
		} else {
			$archivepath = $temppath;
		}

		if(!file_exists($archivepath.'/module.xml')) {
			return array(sprintf(_('Missing module.xml in %s'),$filename));
		}

		$xml = simplexml_load_file($archivepath.'/module.xml');
		$modulename = (string)$xml->rawname;
		$this->downloadedRawname = !empty($modulename) ? $modulename : "";
		if(empty($modulename)) {
			return array(sprintf(_('Module Name is blank in %s'),$filename));
		}

		$dest = $amp_conf['AMPWEBROOT']."/admin/modules/".$modulename;

		if(file_exists($dest)) {
			exec("rm -rf $dest 2>&1", $output, $exitcode);
			if ($exitcode != 0) {
				return array(sprintf(_('Could not remove old module %s to install new version'), $dest), implode("\n", $output));
			}
		}

		//we just deleted it, if we cant recreate it then we have some serious issues going on
		if (! @mkdir($dest) ) {
			return array(sprintf(_("Error creating module directory: %s"), $dest));
		}

		exec("cp -R $archivepath/* $dest 2>&1", $output, $exitcode);
		// Why would this ever fail?
		if ($exitcode != 0) {
			return $output;
		}

		// Fix default ownership, in case this was run as root. (We don't care if this
		// errors)
		exec("chown -R ".$amp_conf['AMPASTERISKWEBUSER'].".".$amp_conf['AMPASTERISKWEBGROUP']." $dest");

		// These are known 'binary' directories. If they exist, always set them and their
		// contents to be executable.
		// NOTE: this is also done in fwconsole chown class!
		$bindirs = array("bin", "hooks", "agi-bin");
		foreach ($bindirs as $bindir) {
			if (is_dir("$dest/$bindir")) {
				exec("chmod -R 0755 $dest/$bindir");
			}
		}

		exec("rm -rf $temppath", $output, $exitcode);
		if ($exitcode != 0) {
			return array(sprintf(_('Could not remove temporary location %s'), $temppath));
		}

		// invoke progress_callback
		if(isset($progress_callback)) {
			if (!is_array($progress_callback) && function_exists($progress_callback)) {
				$progress_callback('done', array('module'=>$modulename));
			} else if(is_array($progress_callback) && method_exists($progress_callback[0],$progress_callback[1])) {
				$progress_callback[0]->{$progress_callback[1]}('done', array('module'=>$modulename));
			}
		}
		return true;
	}

	/**
	* Installs or upgrades a module from it's directory
	* Checks dependencies, and enables
	* @param string   The name of the module to install
	* @param bool     If true, skips status and dependency checks
	* @param bool	  ignore conflicts
	* @return mixed   True if succesful, array of error messages if not succesful
	*/
	function install($modulename, $force = false, $ignorechecks = true) {
		$this->getInfoCache = array(); //invalidate local
		$this->modDepends = array();
		$this->notFound = false;
		global $db, $amp_conf;
		$FreePBX = FreePBX::Create();

		set_time_limit($this->maxTimeLimit);

		// make sure we have a directory, to begin with
		$dir = $amp_conf['AMPWEBROOT'].'/admin/modules/'.$modulename;
		if (!is_dir($dir)) {
			$this->notFound = true;
			return array(_("Cannot find module"));
		}

		// read the module.xml file
		$this->modXMLCache[$modulename] = null;
		$modules = $this->getinfo($modulename);
		if (!isset($modules[$modulename])) {
			return array(_("Could not read module.xml"));
		}

		// don't force this bit - we can't install a broken module (missing files)
		if ($modules[$modulename]['status'] == MODULE_STATUS_BROKEN) {
			return array(sprintf(_("Module %s is broken and cannot be installed. You should try to download it again."), $modules[$modulename]['rawname']));
		}

		$mod = $FreePBX->GPG->verifyModule($modulename);
		$revoked = $mod['status'] & FreePBX\GPG::STATE_REVOKED;
		if($revoked) {
			return array(_("Module ".$modulename." has a revoked signature and cannot be installed"));
		}

		if (!$force) {
			if (!in_array($modules[$modulename]['status'], array(MODULE_STATUS_ENABLED, MODULE_STATUS_NOTINSTALLED, MODULE_STATUS_NEEDUPGRADE))) {
				//return array(_("This module is already installed."));
				// This isn't really an error, we just exit
				return true;
			}

			// check dependencies
			if (is_array($errors = $this->checkdepends($modules[$modulename]))) {
				return $errors;
			}
		}

		$bmoModules = $FreePBX->Modules;
		if(!$force && !$ignorechecks) {
			// check dependencies
			$errors = $bmoModules->checkConflicts($modules[$modulename]);
			if(!empty($errors['breaking'])) {
				$final = [];
				foreach($errors['issues'] as $module => $issues){
					foreach ($issues as $issue) {
						$final[] = sprintf('%s: %s',$module, $issue);
					}
				}
				return $final;
			}
		}

		// Check if another module wants this install to be rejected
		// The module must have a callback: [modulename]_module_install_check_callback() that takes
		// a single modules array from module_getinfo() about the module to be installed
		// and it must pass back boolean true if the installation can proceed, or a message
		// indicating why the installation must fail
		//
		$rejects = array();

		//We need to include developer files before the callback happens during an install
		if (!$this->_runscripts_include($modules, 'install')) {
			return array(_("Failed to run installation scripts"));
		}

		foreach ($bmoModules->functionIterator('module_install_check_callback', $modules) as $mod => $res) {
			if ($res !== true) {
				$rejects[] = $res;
			}
		}
		if (!empty($rejects)) {
			return $rejects;
		}

		$xml = simplexml_load_file($dir.'/module.xml');
		if(!empty($xml->database)) {
			$tables = array();
			foreach($xml->database->table as $table) {
				$tname = (string)$table->attributes()->name;
				$tables[] = $tname;
			}
			outn(sprintf(_("Updating tables %s..."),implode(", ",$tables)));
			$FreePBX->Database->migrateMultipleXML($xml->database->table);
			out(_("Done"));
		}

		// run the scripts
		if (!$this->_runscripts($modulename, 'install', $modules)) {
			return array(_("Failed to run installation scripts"));
		}

		if ($modules[$modulename]['status'] == MODULE_STATUS_NOTINSTALLED) {
			// customize INSERT query
			$sql = "INSERT INTO modules (modulename, version, enabled) values ('".$db->escapeSimple($modules[$modulename]['rawname'])."','".$db->escapeSimple($modules[$modulename]['version'])."', 1);";
			freepbx_log(FPBX_LOG_UPDATE,sprintf(_("Module: %s installed at version %s"),$modules[$modulename]['rawname'],$modules[$modulename]['version']));
		} else {
			// just need to update the version
			$sql = "UPDATE modules SET version='".$db->escapeSimple($modules[$modulename]['version'])."' WHERE modulename = '".$db->escapeSimple($modules[$modulename]['rawname'])."'";
			freepbx_log(FPBX_LOG_UPDATE,sprintf(_("Module: %s Updated to version %s"),$modules[$modulename]['rawname'],$modules[$modulename]['version']));
		}

		// run query
		$results = $db->query($sql);
		if(DB::IsError($results)) {
			return array(sprintf(_("Error updating database. Command was: %s; error was: %s "), $sql, $results->getMessage()));
		}

		// If module is framework then update the framework version
		// normally this is done inside of the funky upgrade script runner but we are changing this now as
		// framework and freepbx versions are the same
		if($modulename == 'framework' && !empty($modules[$modulename]['version']) && (getVersion() != $modules[$modulename]['version'])) {
			out(sprintf(_("Framework Detected, Setting FreePBX Version to %s"),$modules[$modulename]['version']));
			$sql = "UPDATE admin SET value = '".$db->escapeSimple($modules[$modulename]['version'])."' WHERE variable = 'version'";
			$result = $db->query($sql);
			if(DB::IsError($result)) {
				die($result->getMessage());
			}
			if(getVersion() != $modules[$modulename]['version']) {
				die(_('Internal Error. Function getVersion did not match the Framework version, even after it was suppose to be applied'));
			}
		}

		// module is now installed & enabled, invalidate the modulelist class since it is now stale
		$this->getInfoCache = array(); //invalidate local
		$FreePBX->Modulelist->invalidate();
		$this->modXMLCache = array();

		// edit the notification table to list any remaining upgrades available or clear
		// it if none are left. It requres a copy of the most recent module_xml to compare
		// against the installed modules.
		//
		$sql = 'SELECT data FROM module_xml WHERE id = "xml"';
		$data = sql($sql, "getOne");
		$parser = new xml2ModuleArray($data);
		$xmlarray = $parser->parseAdvanced($data);
		$new_modules = array();
		if (count($xmlarray)) {
			foreach ($xmlarray['xml']['module'] as $mod) {
				$new_modules[$mod['rawname']] = $mod;
			}
		}
		$this->upgrade_notifications($new_modules, 'PASSIVE');
		needreload();
		$FreePBX->Config->update("SIGNATURECHECK", true);
		$db->query("DELETE FROM admin WHERE variable = 'unsigned' LIMIT 1");

		//Generate LESS on install
		//http://issues.freepbx.org/browse/FREEPBX-8287
		outn(_("Generating CSS..."));
		try {
			if($modulename == 'framework') {
				$FreePBX->Less->generateMainStyles();
			} else {
				$FreePBX->Less->generateModuleStyles($modulename);
			}
		}catch(\Exception $e) {}
		out(_("Done"));
		return true;
	}

	/**
	* Disable a module, but reqmains installed
	* @param string   The name of the module to disable
	* @param bool     If true, skips status and dependency checks
	* @return mixed   True if succesful, array of error messages if not succesful
	*/
	function disable($modulename, $force = false) { // was disableModule
		global $db;
		$modules = $this->getinfo($modulename);
		if (!isset($modules[$modulename])) {
			return array(_("Specified module not found"));
		}

		if (!$force) {
			if ($modules[$modulename]['status'] != MODULE_STATUS_ENABLED) {
				return array(_("Module not enabled: cannot disable"));
			}

			if ( ($depmods = $this->reversedepends($modulename)) !== false) {
				return array(_("Cannot disable: The following modules depend on this one: ").implode(',',$depmods));
			}
		}

		// run the scripts
		$this->_runscripts($modulename, 'disable', $modules);

		$this->setenabled($modulename, false);
		needreload();
		//invalidate the modulelist class since it is now stale
		$this->getInfoCache = array(); //invalidate local
		\FreePBX::Modulelist()->invalidate();

		//disable all jobs
		\FreePBX::Job()->setEnabledByModule($modulename, false);

		return true;
	}

	private function listdir($directory, $recursive=true) {
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

	private function addslash($dir) {
		return (($dir[ strlen($dir)-1 ] == '/') ? $dir : $dir.'/');
	}

	private function moduleCleanup($modulename) {
		global $amp_conf;
		$symlink_dirs  = array (
			'bin' 	 =>  $amp_conf['AMPBIN'],
		        'etc' 	 =>  $amp_conf['ASTETCDIR'],
		        'images' =>  $amp_conf['AMPWEBROOT'] . "/admin/images"
			);
		$moduledir = $amp_conf['AMPWEBROOT'].'/admin/modules/'.$modulename;
		foreach($symlink_dirs as $subdir => $targetdir) {
			$dir = $this->addslash($moduledir).$subdir;
			if(is_dir($dir)) {
				foreach($this->listdir($dir) as $idx => $file){
					$sourcefile = $file;
					$filesubdir=str_replace($dir.'/', '', $file);
					$targetfile = $this->addslash($targetdir).$filesubdir;
					if (is_link($targetfile)) {
						@unlink($targetfile);
					}
				}
			}
		}
	}

	/**
	* Uninstall a module, but files remain
	* @param string   The name of the module to install
	* @param bool     If true, skips status and dependency checks
	* @return mixed   True if succesful, array of error messages if not succesful
	*/
	function uninstall($modulename, $force = false) {
		global $db;
		global $amp_conf;

		$modules = $this->getinfo($modulename);
		if (!isset($modules[$modulename])) {
			return array(_("Specified module not found"));
		}

		if (!$force) {
			if ($modules[$modulename]['status'] == MODULE_STATUS_NOTINSTALLED) {
				return array(_("Module not installed: cannot uninstall"));
			}

			if ( ($depmods = $this->reversedepends($modulename)) !== false) {
				return array(_("Cannot disable: The following modules depend on this one: ").implode(',',$depmods));
			}
		}

		// Check if another module wants this uninstall to be rejected
		// The module must have a callback: [modulename]_module_uninstall_check_callbak() that takes
		// a single modules array from module_getinfo() about the module to be uninstalled
		// and it must pass back boolean true if the uninstall can proceed, or a message
		// indicating why the uninstall must fail
		//
		$rejects = array();
		foreach (\FreePBX::Modules()->functionIterator('module_uninstall_check_callback', $modules) as $mod => $res) {
			if ($res !== true) {
				$rejects[] = $res;
			}
		}
		if (!empty($rejects)) {
			return $rejects;
		}

		$sql = "DELETE FROM modules WHERE modulename = '".$db->escapeSimple($modulename)."'";
		$results = $db->query($sql);
		if(DB::IsError($results)) {
			return array(_("Error updating database: ").$results->getMessage());
		}

		if (!$this->_runscripts($modulename, 'uninstall', $modules)) {
			return array(_("Failed to run un-installation scripts"));
		}

		$dir = $amp_conf['AMPWEBROOT'].'/admin/modules/'.$modulename;
		if(file_exists($dir.'/module.xml')) {
			$xml = simplexml_load_file($dir.'/module.xml');
			if(!empty($xml->database)) {
				foreach($xml->database->table as $table) {
					$tname = (string)$table->attributes()->name;
					outn(sprintf(_("Dropping table %s..."),$tname));
					$sth = FreePBX::Database()->prepare("DROP TABLE IF EXISTS ".$tname);
					out(_("Done"));
					$sth->execute();
				}
			}
		}

		// Now make sure all feature codes are uninstalled in case the module has not already done it
		//
		require_once(dirname(__FILE__) . '/featurecodes.class.php'); //TODO: do we need this, now that we have bootstrap? -MB
		featurecodes_delModuleFeatures($modulename);

		$freepbx_conf = freepbx_conf::create();
		$freepbx_conf->remove_module_settings($modulename);
		$mod_asset_dir = $amp_conf['AMPWEBROOT'] . "/admin/assets/" . $modulename;
		if (is_link($mod_asset_dir)) {
			@unlink($mod_asset_dir);
		}

		try {
			$mn = \FreePBX::Modules()->cleanModuleName($modulename);
			$bmofile = "$moduledir/$mn.class.php";
			$moduleObject = \FreePBX::create()->$mn;
			if (file_exists($dir) && is_subclass_of($moduleObject,'FreePBX\DB_Helper')) {
				$moduleObject->deleteAll();
			}
		} catch(\Exception $e) {}

		\FreePBX::Job()->removeAllByModule($modulename);

		$this->moduleCleanup($modulename);

		needreload();
		//invalidate the modulelist class since it is now stale
		\FreePBX::Modulelist()->invalidate();
		$this->getInfoCache = array(); //invalidate local

		return true;
	}

	/**
	* Totally deletes a module
	* @param string   The name of the module to install
	* @param bool     If true, skips status and dependency checks
	* @return mixed   True if succesfull, array of error messages if not succesful
	*/
	function delete($modulename, $force = false) {
		global $amp_conf;

		$modules = $this->getinfo($modulename);
		if (!isset($modules[$modulename])) {
			return array(_("Specified module not found"));
		}

		if ($modules[$modulename]['status'] != MODULE_STATUS_NOTINSTALLED) {
			if (is_array($errors = $this->uninstall($modulename, $force))) {
				//TODO: not sure about this...
				return $errors;
			}
		}

		// delete module directory
		//TODO : do this in pure php
		$dir = $amp_conf['AMPWEBROOT'].'/admin/modules/'.$modulename;
		if (!is_dir($dir)) {
			return array(sprintf(_("Cannot delete directory %s"), $dir));
		}
		if (strpos($dir,"..") !== false) {
			die_freepbx("Security problem, denying delete");
		}
		exec("rm -r ".escapeshellarg($dir),$output, $exitcode);
		if ($exitcode != 0) {
			return array(sprintf(_("Error deleting directory %s (code %d)"), $dir, $exitcode));
		}

		// uninstall will have called needreload() if necessary, also invalidate
		return true;
	}

	/** Internal use only */
	private function setenabled($modulename, $enabled) {
		global $db;
		$sql = 'UPDATE modules SET enabled = '.($enabled ? '1' : '0').' WHERE modulename = "'.$db->escapeSimple($modulename).'"';
		$results = $db->query($sql);
		if(DB::IsError($results)) {
			die_freepbx($sql."<br>\n".$results->getMessage());
		}
		\FreePBX::Modulelist()->invalidate();
		$this->getInfoCache = array(); //invalidate local
		$this->modXMLCache = array();
	}

	function _readxml($modulename, $cached = true) {
		global $amp_conf;
		switch ($modulename) {
			case 'builtin': // special handling
				$dir = $amp_conf['AMPWEBROOT'];
				$xmlfile = $dir.'/admin/module-builtin.xml';
			break;
			default:
				$dir = $amp_conf['AMPWEBROOT'].'/admin/modules/'.$modulename;
				$xmlfile = $dir.'/module.xml';
			break;
		}

		if (file_exists($xmlfile)) {
			if(isset($this->modXMLCache[$modulename]) && $cached) {
				return $this->modXMLCache[$modulename];
			}
			ini_set('user_agent','Wget/1.10.2 (Red Hat modified)');
			$data = file_get_contents($xmlfile);
			try {
				$parser = new xml2Array($data);
				$xmlarray = $parser->data;
			} catch(\Exception $e) {
				freepbx_log(FPBX_LOG_ERROR,sprintf(_("Unable to parse %s: %s"),$xmlfile, $e->getMessage()));
				$xmlarray = array();
			}
			if (isset($xmlarray['module'])) {
				// add a couple fields first
				$xmlarray['module']['name'] = str_replace("\n&\n","&",$xmlarray['module']['name']);
				$xmlarray['module']['displayname'] = $xmlarray['module']['name'];
				if (isset($xmlarray['module']['description'])) {
					if(is_array($xmlarray['module']['description'])) {
							$xmlarray['module']['description'] = _("Invalid description");
					} else {
						$xmlarray['module']['description'] = trim(str_replace("\n","",$xmlarray['module']['description']));
					}

				}
				if (isset($xmlarray['module']['methods'])) {
					$defpri = 300;
					$mod_meths = $xmlarray['module']['methods'];
					unset($xmlarray['module']['methods']);
					foreach ($mod_meths as $type => $methods) {
						if (is_array($methods)) {
							foreach ($methods as $i => $method) {
								$path = '/module/methods/' . $type . '/' .$i;
								$pri = isset($parser->attributes[$path]['pri'])
									? $parser->attributes[$path]['pri']
									: $defpri;
								$pri = ctype_digit($pri) && $pri > 0
									? $pri
									: $defpri;
								$xmlarray['module']['methods'][$type][$pri][]
									= $method;
							}
						} else {
							$path = '/module/methods/' . $type;
							$pri = isset($parser->attributes[$path]['pri'])
								? $parser->attributes[$path]['pri']
								: $defpri;
							$pri = ctype_digit($pri) && $pri > 0
								? $pri
								: $defpri;

							$xmlarray['module']['methods'][$type][$pri][]
								= $methods;
						}
					}
				}
				if (!empty($xmlarray['module']['menuitems'])) {
					foreach ($xmlarray['module']['menuitems'] as $item=>$displayname) {
						$displayname = str_replace("\n&\n","&",$displayname);
						$xmlarray['module']['menuitems'][$item] = $displayname;
						$path = '/module/menuitems/'.$item;

						// find category
						if (isset($parser->attributes[$path]['category'])) {
							$category = str_replace("\n&\n","&",$parser->attributes[$path]['category']);
						} else if (isset($xmlarray['module']['category'])) {
							$category = str_replace("\n&\n","&",$xmlarray['module']['category']);
						} else {
							$category = 'Basic';
						}

						// find type
						if (isset($parser->attributes[$path]['type'])) {
							$type = $parser->attributes[$path]['type'];
						} else if (isset($xmlarray['module']['type'])) {
							$type = $xmlarray['module']['type'];
						} else {
							$type = 'setup';
						}

						// sort priority
						if (isset($parser->attributes[$path]['sort'])) {
							// limit to -10 to 10
							if ($parser->attributes[$path]['sort'] > 10) {
								$sort = 10;
							} else if ($parser->attributes[$path]['sort'] < -10) {
								$sort = -10;
							} else {
								$sort = $parser->attributes[$path]['sort'];
							}
						} else {
							$sort = 0;
						}

						// setup basic items array
						$xmlarray['module']['items'][$item] = array(
							'name' => $displayname,
							'type' => $type,
							'category' => $category,
							'sort' => $sort,
						);

						// add optional values:
						$optional_attribs = array(
							'href', // custom href
							'target', // custom target frame
							'display', // display= override
							'needsenginedb', // set to true if engine db access required (e.g. astman access)
							'needsenginerunning', // set to true if required to run
							'access', // set to all if all users should always have access
							'hidden', //keep hidden from the gui at all times - but accesable if you kknow how...
							'requires_auth', //option todisable auth check
							'beta', //option to display Beta warning on display page
						);
						foreach ($optional_attribs as $attrib) {
							if (isset($parser->attributes[$path][ $attrib ])) {
								$xmlarray['module']['items'][$item][ $attrib ] = $parser->attributes[$path][ $attrib ];
							}
						}
					}
				}
				$this->modXMLCache[$modulename] = $xmlarray['module'];
				return $this->modXMLCache[$modulename];
			}
		}
		return null;
	}

	// This returns the version of a module
	function _getversion($modname) {
		global $db;

		$sql = "SELECT version FROM modules WHERE modulename = '".$db->escapeSimple($modname)."'";
		$results = $db->getRow($sql,DB_FETCHMODE_ASSOC);
		if (isset($results['version']))
			return $results['version'];
		else
			return null;
	}

	/** Include additional files requested in module.xml for install and uninstall
	* @param array   The modulexml array
	* @param string  The action to perform, either 'install' or 'uninstall'
	* @return boolean   If the action was successful, currently TRUE so we don't prevent the install
	*/
	function _runscripts_include($modulexml, $type) {
		global $amp_conf;

		foreach($modulexml as $modulename => $items) {
			$moduledir = $amp_conf["AMPWEBROOT"]."/admin/modules/".$modulename;
			if (isset($items['fileinclude'][$type]) && !empty($items['fileinclude'][$type])) {
				if (!is_array($items['fileinclude'][$type])) {
					$ret = $this->_doinclude($moduledir.'/'.$items['fileinclude'][$type], $modulename);
					if (!$ret) {
						freepbx_log(FPBX_LOG_WARNING,sprintf(_("failed to include %s during %s of the %s module."),$items['fileinclude'][$type],$type,$modulename));
					}
				} else {
					foreach($items['fileinclude'][$type] as $key => $filename) {
						$ret = $this->_doinclude($moduledir.'/'.$filename, $modulename);
						if (!$ret) {
							freepbx_log(FPBX_LOG_WARNING,sprintf(_("failed to include %s during %s of the %s module."),$filename,$type,$modulename));
						}
					}
				}
			}
		}
		return true;
	}

	/** Run the module install/uninstall scripts
	* @param string  The name of the module
	* @param string  The action to perform, either 'install' or 'uninstall'
	* @param array	  The modulexml array
	* @return boolean  If the action was succesful
	*/
	function _runscripts($modulename, $type, $modulexml = false) {
		global $amp_conf;
		$db_engine = $amp_conf["AMPDBENGINE"];

		$moduledir = $amp_conf["AMPWEBROOT"]."/admin/modules/".$modulename;
		if (!is_dir($moduledir)) {
			return false;
		}

		switch ($type) {
			case 'install':
				// install sql files
				$sqlfilename = "install.sql";
				$rc = true;

				if (is_file($moduledir.'/'.$sqlfilename)) {
					$rc = execSQL($moduledir.'/'.$sqlfilename);
				}

				//include additional files developer requested
				if ($modulexml !== false) {
					$this->_runscripts_include($modulexml, $type);
				}

				// If it's a BMO module, manually include the file.
				$mn = \FreePBX::Modules()->cleanModuleName($modulename);
				$bmofile = "$moduledir/$mn.class.php";
				if (file_exists($bmofile)) {
					FreePBX::create()->injectClass($mn, $bmofile);
					$o = FreePBX::create()->$mn->install();
					if($o === false) {
						return false;
					}
				}

				// then run .php scripts
				return ($this->_doinclude($moduledir.'/install.php', $modulename) && $rc);
			break;
			case 'uninstall':
				//include additional files developer requested
				if ($modulexml !== false) {
					$this->_runscripts_include($modulexml, $type);
				}

				// run uninstall .php scripts first
				$rc = $this->_doinclude($moduledir.'/uninstall.php', $modulename);

				$sqlfilename = "uninstall.sql";

				// If it's a BMO module, run uninstall.
				$mn = \FreePBX::Modules()->cleanModuleName($modulename);
				$bmofile = "$moduledir/$mn.class.php";
				if (file_exists($bmofile)) {
					try {
						$o = FreePBX::create()->$mn->uninstall();
						if($o === false) {
							return false;
						}
					} catch(Exception $e) {
						dbug("Error Returned was: ".$e->getMessage());
						return false;
					}
				}

				// then uninstall sql files
				if (is_file($moduledir.'/'.$sqlfilename)) {
					return ($rc && execSQL($moduledir.'/'.$sqlfilename));
				} else {
					return $rc;
				}

			break;
			case 'enable':
				// If it's a BMO module, manually include the file.
				$mn = \FreePBX::Modules()->cleanModuleName($modulename);
				$bmofile = "$moduledir/$mn.class.php";
				if (file_exists($bmofile)) {
					if(\FreePBX::Modules()->moduleHasMethod($modulename, 'enable')) {
						try {
							$o = FreePBX::create()->$mn->enable();
							if($o === false) {
								return false;
							}
						} catch(Exception $e) {
							dbug("Error Returned was: ".$e->getMessage());
							return false;
						}
					}
				}
			break;
			case 'disable':
				// If it's a BMO module, run uninstall.
				$mn = \FreePBX::Modules()->cleanModuleName($modulename);
				$bmofile = "$moduledir/$mn.class.php";
				if (file_exists($bmofile)) {
					if(\FreePBX::Modules()->moduleHasMethod($modulename, 'disable')) {
						try {
							$o = FreePBX::create()->$mn->disable();
							if($o === false) {
								return false;
							}
						} catch(Exception $e) {
							dbug("Error Returned was: ".$e->getMessage());
							return false;
						}
					}
				}
			break;
			default:
				return false;
		}

		return true;
	}

	function _doinclude($filename, $modulename) {
		// we provide the following variables to the included file (as well as $filename and $modulename)
		global $db, $amp_conf, $asterisk_conf;

		if (file_exists($filename) && is_file($filename)) {
			return include_once($filename);
		} else {
			return true;
		}
	}

	/* module_get_annoucements()

		Get's any annoucments, security warnings, etc. that may be related to the current freepbx version. Also
		transmits a uniqueid to help track the number of installations using the online module admin system.
		The uniqueid used is completely anonymous and not trackable.
	*/
	function get_annoucements() {
		global $db;
		global $amp_conf;

		$announcement = $this->get_remote_contents("/version-".getversion().".html");

		if (\FreePBX::Modules()->checkStatus('sysadmin') && \FreePBX::Modules()->moduleHasMethod('sysadmin', 'getAnnouncements')) {
			$announcement = \FreePBX::Sysadmin()->getAnnouncements($announcement);
		}

		return $announcement;
	}

	public function check_first_install() {
		$sth = \FreePBX::Database()->prepare("SELECT data as installid FROM module_xml WHERE id = 'installid'");
		$sth->execute();
		$res = $sth->fetch(PDO::FETCH_ASSOC);
		return (empty($res) || empty($res['installid']));
	}

	public function get_unique_id() {
		$sth = \FreePBX::Database()->prepare("SELECT data as installid FROM module_xml WHERE id = 'installid'");
		$sth->execute();
		$res = $sth->fetch(PDO::FETCH_ASSOC);
		if(empty($res) || empty($res['installid'])) {
			return $this->generate_unique_id();
		}
		return $res['installid'];
	}

	private function get_virtual_machine_type() {
		$ids = array('080027' => 'virtualbox',
								'001C42' => 'parallels',
								'001C14' => 'vmware',
								'005056' => 'vmware',
								'000C29' => 'vmware',
								'000569' => 'vmware',
								'00163E' => 'xensource',
								'000F4B' => 'virtualiron4',
								'0003FF' => 'hyper-v'
								);
		$mac_address = array();
		$chosen_mac = null;

		// TODO: put proper path in places for ifconfig, try various locations where it may be if
		//       non-0 return code.
		//
		//TODO: this needs to check debian as well: ip addr show
		if(is_executable('/sbin/ifconfig')) {
			exec('/sbin/ifconfig',$output, $return);
		} else {
			$return = -1;
		}

		if ($return != '0') {

			// OK try another path
			//
			if(is_executable('ifconfig')) {
				exec('ifconfig',$output, $return);
			} else {
				$return = -1;
			}

			if ($return != '0') {
				$sth = \FreePBX::Database()->prepare("REPLACE INTO module_xml (id,time,data) VALUES ('type',?,?)");
				$sth->execute(array(time(),""));
				return "";
			}
		}

		// parse the output of ifconfig to get list of MACs returned
		//
		foreach ($output as $str) {
			// make sure each line contains a valid MAC and IP address and then
			//
			if (preg_match("/([0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2}){5})/", $str, $mac)) {
				$mac_address[] = strtoupper(preg_replace("/:/","",$mac[0]));
			}
		}

		foreach ($mac_address as $mac) {
			$id = substr($mac,0,6);

			// If we care about this id, then choose it and set the type
			// we only choose the first one we see
			//
			if (array_key_exists($id,$ids)) {
				$chosen_mac = $mac;
				$type = $ids[$id];
				$sth = \FreePBX::Database()->prepare("REPLACE INTO module_xml (id,time,data) VALUES ('type',?,?)");
				$sth->execute(array(time(),$type));
				return $type;
			}
		}
		$sth = \FreePBX::Database()->prepare("REPLACE INTO module_xml (id,time,data) VALUES ('type',?,?)");
		$sth->execute(array(time(),""));
		return "";
	}

	public function get_machine_id() {
		switch(PHP_OS) {
			case 'FreeBSD';
				$result = shell_exec('kenv -q smbios.system.uuid');
				$result = preg_replace("/\r+|\n+|\s+/i", '', $result);
				return strtolower($result);
			break;
			case 'Darwin':
				$result = shell_exec('ioreg -rd1 -c IOPlatformExpertDevice');
				if(preg_match('/IOPlatformUUID.*=.*"(.*)"/i',$result,$matches)) {
					return $matches[1];
				} else {
					return '';
				}
			break;
			case 'Linux':
				$result = shell_exec('cat /var/lib/dbus/machine-id /etc/machine-id 2> /dev/null | head -n 1 || :');
				$result = preg_replace("/\r+|\n+|\s+/i", '', $result);
				return strtolower($result);
			default:
			break;
		}
	}

	private function get_accessed_id() {
		$sql = "SELECT * FROM module_xml WHERE id = 'accessedid'";
		$sth = \FreePBX::Database()->query($sql);
		$value = $sth->fetch(\PDO::FETCH_ASSOC);
		if(empty($value) || empty($value['data'])) {
			return $this->generate_accessed_id();
		} else {
			return $value['data'];
		}
	}

	private function generate_accessed_id() {
		return $this->generateUUID4();
	}

	private function update_accessed_id($uuid) {
		$sql = "REPLACE INTO module_xml (id,time,data) VALUES ('accessedid',?,?)";
		$sth = \FreePBX::Database()->prepare($sql);
		$sth->execute(array(time(),$uuid));
		return $uuid;
	}

	private function generate_unique_id() {
		$uuid = $this->generateUUID4();
		$sql = "REPLACE INTO module_xml (id,time,data) VALUES ('installid',?,?)";
		$sth = \FreePBX::Database()->prepare($sql);
		$sth->execute(array(time(),$uuid));
		return $uuid;
	}

	private function generateUUID4() {
		try {
			$uuid4 = Uuid::uuid4();
			return $uuid4->toString();
		} catch(\Exception $e) {
			return bin2hex(openssl_random_pseudo_bytes(32));
		}

	}

	function run_notification_checks() {
		global $db;
		$modules_needup = $this->getinfo(false, MODULE_STATUS_NEEDUPGRADE);
		$modules_broken = $this->getinfo(false, MODULE_STATUS_BROKEN);

		$notifications = notifications::create($db);
		if ($cnt = count($modules_needup)) {
			$text = (($cnt > 1) ? sprintf(_('You have %s disabled modules'), $cnt) : _('You have a disabled module'));
			$desc = _('The following modules are disabled because they need to be upgraded:')."\n".implode(", ",array_keys($modules_needup));
			$desc .= "\n\n"._('You should go to the module admin page to fix these.');
			$notifications->add_error('freepbx', 'modules_disabled', $text, $desc, '?type=tool&display=modules');
		} else {
			$notifications->delete('freepbx', 'modules_disabled');
		}
		if ($cnt = count($modules_broken)) {
			$text = (($cnt > 1) ? sprintf(_('You have %s broken modules'), $cnt) : _('You have a broken module'));
			$desc = _('The following modules are disabled because they are broken:')."\n".implode(", ",array_keys($modules_broken));
			$desc .= "\n\n"._('You should go to the module admin page to fix these.');
			$notifications->add_critical('freepbx', 'modules_broken', $text, $desc, '?type=tool&display=modules', false);
		} else {
			$notifications->delete('freepbx', 'modules_broken');
		}
		if (\FreePBX::Modules()->checkStatus("restapi")) {
			$text = _('You have a deprecated module');
			$desc = _('The restapi module is deprecated and functionality is moving to the api module starting in PBX version 16. If not being used, the restapi module should be removed.');
			$notifications->add_warning('freepbx', 'modules_deprecated', $text, $desc, '?type=tool&display=modules');
		} else {
			$notifications->delete('freepbx', 'modules_deprecated');
		}
	}

	/** Replaces variables in a string with the values from ampconf
	* eg, "%AMPWEBROOT%/admin" => "/var/www/html/admin"
	*/
	function _ampconf_string_replace($string) {
		$freepbx_conf = freepbx_conf::create();

		$target = array();
		$replace = array();

		foreach ($freepbx_conf->conf as $key=>$value) {
			$target[] = '%'.$key.'%';
			$replace[] = $value;
		}

		return str_replace($target, $replace, $string);
	}

	/* Get the brand from the pbx-brand file. This can be overriden
	* by setting it first, used primarily to modify current brand
	* to get a specific version that may exist online
	*/
	function _brandid($brand_override=false) {
		static $brand;
		if ($brand_override) {
			$brand = strtolower(trim($brand_override));
		}
		if (!empty($brand)) {
			return $brand;
		}

		$brandfile = "/etc/schmooze/pbx-brand";
		// TODO: log error if file is un-readable or blank?
		if (file_exists($brandfile)) {
			return strtolower(trim(file_get_contents($brandfile)));
		} else {
			return false;
		}
	}

	/* Get the deploymentid from zend.
	*/
	function _deploymentid() {
		if (function_exists('sysadmin_get_license')) {
			$lic = sysadmin_get_license();
			if (isset($lic['deploymentid'])) {
				return trim($lic['deploymentid']);
			}
		}
		return false;
	}


	function _distro_id() {
		static $pbx_type;
		static $pbx_version;

		if (isset($pbx_type)) {
			return array('pbx_type' => $pbx_type, 'pbx_version' => $pbx_version);
		}


		exec('lscpu 2>&1',$out,$ret);
		$cpu_arch = '';
		if(!$ret && !empty($out)) {
			foreach($out as $line) {
				if(preg_match('/architecture:(.*)/i',$line,$matches)) {
					$cpu_arch = trim($matches[1]);
					break;
				}
			}
		}

		// FreePBX Distro
		if (file_exists('/etc/schmooze/freepbxdistro-version')) {
			$pbx_type = 'freepbxdistro';
			$pbx_version = trim(file_get_contents('/etc/schmooze/freepbxdistro-version'));
		} elseif (file_exists('/etc/asterisk/freepbxdistro-version')) {
			$pbx_type = 'freepbxdistro';
			$pbx_version = trim(file_get_contents('/etc/asterisk/freepbxdistro-version'));
		} elseif (file_exists('/etc/schmooze/pbx-version')) {
			$pbx_type = 'freepbxdistro';
			$pbx_version = trim(file_get_contents('/etc/schmooze/pbx-version'));
		} elseif (file_exists('/etc/asterisk/pbx-version')) {
			$pbx_type = 'freepbxdistro';
			$pbx_version = trim(file_get_contents('/etc/asterisk/pbx-version'));

			// Trixbox
		} elseif (file_exists('/etc/trixbox/trixbox-version')) {
			$pbx_type = 'trixbox';
			$pbx_version = trim(file_get_contents('/etc/trixbox/trixbox-version'));

			// AsteriskNOW
		} elseif (file_exists('/etc/asterisknow-version')) {
			$pbx_type = 'asterisknow';
			$pbx_version = trim(file_get_contents('/etc/asterisknow-version'));

			// Elastix
		} elseif (is_dir('/usr/share/elastix') || file_exists('/usr/share/elastix/pre_elastix_version.info')) {
			$pbx_type = 'elastix';
			$pbx_version = '';
			if (class_exists('PDO') && file_exists('/var/www/db/settings.db')) {
				$elastix_db = new PDO('sqlite:/var/www/db/settings.db');
				$result = $elastix_db->query("SELECT value FROM settings WHERE key='elastix_version_release'");
				if ($result !== false) foreach ($result as $row) {
					if (isset($row['value'])) {
						$pbx_version = $row['value'];
						break;
					}
				}
			}
			if (!$pbx_version && file_exists('/usr/share/elastix/pre_elastix_version.info')) {
				$pbx_version = trim(file_get_contents('/usr/share/elastix/pre_elastix_version.info'));
			}
			if (!$pbx_version) {
				$pbx_version = '2.X+';
			}

			// PIAF
		} elseif (file_exists('/etc/pbx/.version') || file_exists('/etc/pbx/.color')) {
			$pbx_type = 'piaf';
			$pbx_version = '';
			if (file_exists('/etc/pbx/.version')) {
				$pbx_version = trim(file_get_contents('/etc/pbx/.version'));
			}
			if (file_exists('/etc/pbx/.color')) {
				$pbx_version .= '.' . trim(file_get_contents('/etc/pbx/.color'));
			}
			//this probably wont work correctly for his beaglebone stuff
			if(preg_match('/arm/i',$cpu_arch)) {
				$pbx_type = 'piaf-IncrediblePI';
			}
			if (!$pbx_version) {
				if (file_exists('/etc/pbx/ISO-Version')) {
					$pbx_ver_raw = trim(file_get_contents('/etc/pbx/ISO-Version'));
					$pbx_arr = explode('=',$pbx_ver_raw);
					$pbx_version = $pbx_arr[count($pbx_arr)-1];
				} else {
					$pbx_version = 'unknown';
				}
			}

			//raspbx
		} elseif(file_exists('/etc/raspbx/base_version') || file_exists('/etc/raspbx/installed_version')) {
			$pbx_type = 'raspbx';

			if (file_exists('/etc/raspbx/base_version')) {
				$pbx_version = trim(file_get_contents('/etc/raspbx/base_version'));
			}
			if (file_exists('/etc/raspbx/installed_version')) {
				$pbx_version .= '.' . trim(file_get_contents('/etc/raspbx/installed_version'));
			}
			if (!$pbx_version) {
				$pbx_version = 'unknown';
			}

			// Old PIAF or Fonica
		} elseif (file_exists('/etc/pbx/version') || file_exists('/etc/pbx/ISO-Version')) {
			$pbx_type = 'fonica';
			if (file_exists('/etc/pbx/ISO-Version')) {
				$pbx_ver_raw = trim(file_get_contents('/etc/pbx/ISO-Version'));
				$pbx_arr = explode('=',$pbx_ver_raw);
				$pbx_version = $pbx_arr[count($pbx_arr)-1];
				if (stristr($pbx_arr[0],'foncordiax') !== false) {
					$pbx_version .= '.pro';
				} else {
					$pbx_version = str_replace(' ','.',$pbx_version);
					if ($pbx_version != '1.0.standard') {
						$pbx_type = 'piaf';
					}
				}
			} else {
				$pbx_version = 'unknown';
			}
		} else {
			$pbx_type = 'unknown';
			$pbx_version = 'unknown';
		}
		//Final Check if we are still unknown
		if($pbx_type == 'unknown') {
			exec('uname 2>&1',$kernel,$ret1);
			exec('uname -r 2>&1',$kernelv,$ret2);
			if(!$ret1 && !$ret2 && !empty($kernel) && !empty($kernelv)) {
				$pbx_type = 'unknown-'.$kernel[0];
				$pbx_version = $kernelv[0];
			}
		}
		return array('pbx_type' => $pbx_type, 'pbx_version' => $pbx_version);
	}

	function get_remote_contents($path, $add_options=false, $uuidcheck=false) {
		$mirrors = $this->generate_remote_urls($path,$add_options,$uuidcheck);
		foreach($mirrors['mirrors'] as $url) {
			$o = $this->url_get_contents($url,$mirrors['path'],'post',$mirrors['options']);
			if(!empty($o)) {
				return $o;
			}
		}
		return false;
	}

	/**
	* function generate_module_repo_url
	* short create array of full URLs to get a file from repo
	* use this function to generate an array of URLs for all configured REPOs
	* @author Philippe Lindheimer
	*
	* @pram string
	* @returns string
	*/
	function generate_remote_urls($path, $add_options=false, $uuidcheck=false) {
		global $db;
		global $amp_conf;
		$urls = array();
		$options = array();

		if ($add_options) {
			$firstinstall=false;

			// if not set so this is a first time install
			// get a new hash to account for first time install
			if ($this->check_first_install()) {
				$options['firstinstall'] = 'yes';
			}
			if($uuidcheck) {
				$options['lastaccesseduuid'] = $this->get_accessed_id();
				$options['currentuuid'] = $this->generate_accessed_id();
			}
			$options['machineid'] = hash('sha256',$this->get_machine_id());
			$options['installid'] = $this->get_unique_id();
			$options['type'] = $this->get_virtual_machine_type();

			//Now that we do everything in post format send back module versions
			$modules_local = $this->getinfo(false,false,true);
			foreach($modules_local as $m => $mod) {
				if($mod['status'] != MODULE_STATUS_BROKEN) {
					$options['modules'][$m]['version'] = $mod['version'];
					$options['modules'][$m]['status'] = $mod['status'];
					$options['modules'][$m]['rawname'] = $mod['rawname'];
					$options['modules'][$m]['license'] = !empty($mod['license']) ? $mod['license'] : "unknown";
				}
			}

			// We check specifically for false because evenif blank it means the file
			// was there so we want module.xml to do appropriate actions
			$brandid = $this->_brandid();
			if ($brandid !== false) {
				$options['brandid'] = $brandid;
			}

			$deploymentid = $this->_deploymentid();
			if ($deploymentid !== false) {
				$options['deploymentid'] = $deploymentid;
			}

			$engver=engine_getinfo();
			if ($engver['engine'] == 'asterisk' && trim($engver['engine']) != "") {
				$options['astver'] = $engver['version'];
			} else {
				$options['astver'] = $engver['raw'];
			}
			$options['phpver'] = phpversion();

			$distro_info = $this->_distro_id();
			$options['distro'] = $distro_info['pbx_type'];
			$options['distrover'] = $distro_info['pbx_version'];
			$options['pbxver'] = getversion();
			if (FreePBX::Modules()->moduleHasMethod('Core','listUsers')) {
				$options['ucount'] = count(FreePBX::Core()->listUsers());
			}
			$extras = array();
			$nt = notifications::create($db);
			$output = exec("getent passwd ssh 2>/dev/null");
			if(!empty($output) && preg_match('/ssh:x:0/i',trim($output))) {
				$extras['ssh'] = array("setting" => $output);
				$nt->add_security('freepbx', 'SYSTEMSSH', _("Unauthorized user"), _("An unauthorized system user account called 'ssh' was discovered in /etc/passwd. Please remove any alias lines referencing 'useradd' in /root/.bashrc and also remove the 'ssh' account as soon as possible"), '', false, true);
			} else {
				$nt->delete('freepbx', 'SYSTEMSSH');
			}
			$options['extraid'] = base64_encode(json_encode($extras));

			// Allow third party ioncube and zend modules.
			if (function_exists('zend_get_id')) {
				$options['zend'] = "available";
			}
			if (function_exists("ioncube_loader_version")) {
				$options['ioncube'] = ioncube_loader_version();
			}

			// Other modules may need to add 'get' paramters to the call to the repo. Check and add them
			// here if we are adding paramters. The module should return an array of key/value pairs each of which
			// is to be appended to the GET parameters. The variable name will be prepended with the module name
			// when sent.
			//
			$repo_params = array();
			foreach (\FreePBX::Modules()->functionIterator('module_repo_parameters_callback', $path) as $mod => $res) {
				if (is_array($res)) {
					foreach ($res as $p => $v) {
						$options[$mod.'_'.$p] = $v;
					}
				}
			}
		}
		$repos = explode(',', $amp_conf['MODULE_REPO']);
		return array('mirrors' => $repos, 'path' => $path, 'options' => $options, 'query' => http_build_query($options));
	}

	function url_get_contents($url,$request,$verb='get',$params=array(), $timeout = 30) {
		$params['sv'] = 2;
		global $amp_conf;
		$verb = strtolower($verb);
		$contents = null;

		$enable_mirror_logging = \FreePBX::Config()->get('ENABLE_MIRROR_LOG');

		if ($enable_mirror_logging) {
			dbug('Mirror Request:');
			dbug(array(
				'url' => $url.$request,
				'method' => $verb,
				'params' => $params,
				'timeout' => $timeout,
			));
		}

		$requests = FreePBX::Curl()->requests($url);
		try{
			$response = $requests->$verb($request,array(),$params,array('timeout' => $timeout));
			$contents = $response->body;
			if($enable_mirror_logging) {
				dbug('Mirror Response:');
				dbug($response);
			}
			if(isset($response->headers['x-current-uuid'])) {
				//we connected
				$this->update_accessed_id($response->headers['x-current-uuid']);
			}
			if(isset($response->headers['x-regenerate-id'])) {
				$this->generate_unique_id(true);
			}
			return $contents;
		} catch (Exception $e) {
			freepbx_log(FPBX_LOG_ERROR,sprintf(_('Failed to get remote file, error was: %s'),(string)$e->getMessage()));
			return '';
		}
	}

	function getSignature($modulename,$cached=true) {
		FreePBX::GPG(); //declare class to get constants
		$sql = "SELECT signature FROM `modules` WHERE modulename = ? AND signature is not null";
		$sth = FreePBX::Database()->prepare($sql);
		$sth->execute(array($modulename));
		$res = $sth->fetch(PDO::FETCH_ASSOC);

		$mod = (empty($res) || !$cached) ? $this->updateSignature($modulename) : json_decode($res['signature'],TRUE);
		return $mod;
	}

	/**
	* Get all Cached Signatures, update if it doesnt exist
	* @param {bool} $cached=true Whether to use cached data or not
	*/
	function getAllSignatures($cached=true, $online = false) {
		FreePBX::GPG(); //declare class to get constants
		$sql = "SELECT modulename, signature FROM modules";
		$sth = FreePBX::Database()->prepare($sql);
		$sth->execute();
		$res = $sth->fetchAll(PDO::FETCH_ASSOC);
		$modules = array();
		$globalValidation = true;
		// String below, if i18n'ed, must be identical to that in GPG class.
		// Read the comment there.
		$fwconsole = FreePBX::Config()->get('AMPSBIN')."/fwconsole "._("altered");
		if(!$cached && $online) {
			FreePBX::GPG()->refreshKeys();
			FreePBX::GPG()->trustFreePBX();
		}
		foreach($res as $mod) {
			// Ignore ARI for the moment.
			if($mod['modulename'] == 'fw_ari') {
				continue;
			}
			//TODO: determine if this should be in here or not.
			if(!$cached || empty($mod['signature'])) {
				$mod['signature'] = $this->updateSignature($mod['modulename']);
			} else {
				$mod['signature'] = json_decode($mod['signature'],TRUE);
			}
			$modules['modules'][$mod['modulename']] = $mod;
			if(!is_int($mod['signature']['status'])) {
				$modules['statuses']['unsigned'][] = sprintf(_('Module "%s" is is missing its signature status.'),$modname);
				continue;
			}
			if(~$mod['signature']['status'] & FreePBX\GPG::STATE_GOOD) {
				$globalValidation = false;
			}
			$trusted = $mod['signature']['status'] & FreePBX\GPG::STATE_TRUSTED;
			$tampered = $mod['signature']['status'] & FreePBX\GPG::STATE_TAMPERED;
			$unsigned = $mod['signature']['status'] & FreePBX\GPG::STATE_UNSIGNED;
			$invalid = $mod['signature']['status'] & FreePBX\GPG::STATE_INVALID;
			$revoked = $mod['signature']['status'] & FreePBX\GPG::STATE_REVOKED;
			//if revoked then disable
			$md = $this->getInfo();
			$modname = !empty($md[$mod['modulename']]['name']) ? $md[$mod['modulename']]['name'] : sprintf(_('%s [not enabled]'),$mod['modulename']);
			if ($invalid) {
				$modules['statuses']['tampered'][] = sprintf(_('Module "%s" signed by an invalid key.' ), $modname);
			}
			if ($unsigned) {
				if ($mod['modulename'] == "framework" || $mod['modulename'] == "core") {
					// Unsigned framework or core is extremely terribly bad.
					$modules['statuses']['tampered'][] = sprintf(_('Critical Module "%s" is unsigned, re-download immediately' ), $modname);
				} else {
					$modules['statuses']['unsigned'][] = sprintf(_('Module "%s" is unsigned and should be re-downloaded'),$modname);
				}
			} else {
				if ($tampered) {
					foreach($mod['signature']['details'] as $d) {
						if ($d == $fwconsole) {
							$modules['statuses']['tampered'][] = sprintf(_("Module: '%s', File: '%s' (If you just updated FreePBX, you'll need to run 'fwconsole chown' and then 'fwconsole reload' to clear this message. If you did not just update FreePBX, your system may have been compromised)"),$modname,$d);
						} else {
							$modules['statuses']['tampered'][] = sprintf(_('Module: "%s", File: "%s"'),$modname,$d);
						}
					}
				}
				if (!$trusted) {
					if($revoked) {
						$modules['statuses']['revoked'][] = sprintf(_('Module: "%s"\'s signature has been revoked. Module has been automatically disabled'),$modname);
					}
				}
			}
		}

		$statuses = array(
			'untrusted' => _('untrusted'),
			'unsigned' => _('unsigned'),
			'tampered' => _('tampered'),
			'unknown' => _('unknown'),
			'revoked' => _('revoked'),
		);
		$nt = notifications::create();
		foreach($statuses as $type => $name) {
			if(!empty($modules['statuses'][$type]) && FreePBX::Config()->get('SIGNATURECHECK')) {
				switch($type) {
					case 'unsigned':
						//TODO: check the hash
						$hash = md5(json_encode($modules['statuses'][$type]));
						$sth = FreePBX::Database()->prepare("SELECT value FROM admin WHERE variable = 'unsigned' LIMIT 1");
						$sth->execute();
						$o = $sth->fetch();
						if(empty($o)) {
							$nt->add_signature_unsigned('freepbx', 'FW_'.strtoupper($type), sprintf(_('You have %s unsigned modules'),count($modules['statuses'][$type])), implode("<br>",$modules['statuses'][$type]),'',true,true);
							sql("INSERT INTO admin (variable, value) VALUE ('unsigned', '$hash')");
						} elseif($o['value'] != $hash) {
							$nt->add_signature_unsigned('freepbx', 'FW_'.strtoupper($type), sprintf(_('You have %s unsigned modules'),count($modules['statuses'][$type])), implode("<br>",$modules['statuses'][$type]),'',true,true);
							$sth = FreePBX::Database()->prepare("UPDATE admin SET value = ? WHERE variable = 'unsigned'");
							$sth->execute(array($hash));
						}
					break;
					case 'tampered':
						$nt->add_security('freepbx', 'FW_'.strtoupper($type), sprintf(_('You have %s tampered files'),count($modules['statuses'][$type])), implode("<br>",$modules['statuses'][$type]));
					break;
					default:
						$nt->add_security('freepbx', 'FW_'.strtoupper($type), sprintf(_('You have %s %s modules'),count($modules['statuses'][$type]),$name), implode("<br>",$modules['statuses'][$type]));
					break;
				}
			} else {
				$nt->delete('freepbx', 'FW_'.strtoupper($type));
			}
		}
		$modules['validation'] = $globalValidation;
		return $modules;
	}

	/**
	* Update gpg Signature check for a single module
	* @param {string} $modulename Raw Module Name
	*/
	public function updateSignature($modulename) {
		$mod = FreePBX::GPG()->verifyModule($modulename);

		$revoked = $mod['status'] & FreePBX\GPG::STATE_REVOKED;
		//if revoked then disable
		if($revoked) {
			$this->disable($modulename);
		}

		$sql = "UPDATE `modules` SET signature = ? WHERE modulename = ?";
		$sth = FreePBX::Database()->prepare($sql);
		$sth->execute(array(json_encode($mod),$modulename));
		return $mod;
	}

	/**
	 * Get the module xml for a specific module version and generate a download link
	 * @param string $modulename The module rawname
	 * @param string $moduleversion The module release version we want to download and install
	 * @return array
	 */
	function getModuleDownloadByModuleNameAndVersion($modulename, $moduleversion) {
		// We need to know the freepbx major version we have running (ie: 12.0.1 is 12.0)
		$fw_version = getversion();
		preg_match('/(\d+\.\d+)/',$fw_version,$matches);
		$base_version = $matches[1];

		$options = array(
			"deploymentid" => $this->_deploymentid(),
			'phpver' => phpversion(),
			'rawname' => $modulename,
			'tag' => $moduleversion,
			'framework' => $base_version
		);

		$distro_info = $this->_distro_id();
		$options['distro'] = $distro_info['pbx_type'];
		$options['distrover'] = $distro_info['pbx_version'];
		$options['pbxver'] = getversion();

		// Allow third party ioncube and zend modules.
		if (function_exists('zend_get_id')) {
			$options['zend'] = "available";
		}
		if (function_exists("ioncube_loader_version")) {
			$options['ioncube'] = ioncube_loader_version();
		}

		$repos = explode(',', \FreePBX::Config()->get('MODULE_REPO'));
		foreach($repos as $url) {
			//TODO: This is a placeholder URL and should be changed
			$o = $this->url_get_contents($url, '/mversion.php', 'post', $options, 10);
			$o = json_decode($o,true);
			if(json_last_error() == JSON_ERROR_NONE && !empty($o)) {
				//Append a download url to the module xml array
				$o['downloadurl'] = $url.'/modules/'.$o['location'];
				return $o;
			}
		}
		return array();
	}
}
