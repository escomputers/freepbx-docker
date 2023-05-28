<?php
// vim: set ai ts=4 sw=4 ft=php:
//
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//	Copyright 2016-2019 Sangoma Technology Corp.
//
/**
 * Controls if online module and install/uninstall options are available.
 * This is meant for when using external packaging systems (eg, deb or rpm) to manage
 * modules. Package maintainers should set AMPEXTERNPACKAGES to true in /etc/amportal.conf.
 * Optionally, the other way is to remove the below lines, and instead just define
 * EXTERNAL_PACKAGE_MANAGEMENT as 1. This prevents changing the setting from amportal.conf.
 */
if (!isset($amp_conf['AMPEXTERNPACKAGES']) || ($amp_conf['AMPEXTERNPACKAGES'] != 'true')) {
	define('EXTERNAL_PACKAGE_MANAGEMENT', 0);
} else {
	define('EXTERNAL_PACKAGE_MANAGEMENT', 1);
}

//moved functions out to their own file to try and be more MVCish
include __DIR__. '/libraries/moduleAdminFunctions.php';
$modulef = module_functions::create();

$REQUEST = freepbxGetSanitizedRequest();
$action = isset($REQUEST['action'])?$REQUEST['action']:'';
$FreePBX = FreePBX::Create();
global $active_repos;
$loc_domain = 'amp';
if (isset($REQUEST['check_online'])) {
	$online = 1;
} else {
	$online = (isset($REQUEST['online']) && $REQUEST['online'] && !EXTERNAL_PACKAGE_MANAGEMENT) ? 1 : 0;
}
$active_repos = $modulef->get_active_repos();
// fix php errors from undefined variable. Not sure if we can just change the reference below to use
// online since it changes values so just setting to what we decided it is here.

$trackaction = isset($REQUEST['trackaction'])?$REQUEST['trackaction']:false;
$moduleaction = isset($REQUEST['moduleaction'])?$REQUEST['moduleaction']:false;
/*
	moduleaction is an array with the key as the module name, and possible values:

	downloadinstall - download and install (used when a module is not locally installed)
	upgrade - download and install (used when a module is locally installed)
	install - install/upgrade locally available module
	enable - enable local module
	disable - disable local module
	uninstall - uninstall local module
 */

$freepbx_version = get_framework_version();
$freepbx_version = $freepbx_version ? $freepbx_version : getversion();
$freepbx_help_url = "https://www.freepbx.org/freepbx-help-system?freepbx_version=".urlencode($freepbx_version);
$displayvars = array();

$displayvars['freepbx_help_url'] = $freepbx_help_url;
$displayvars['online'] = $online;
$displayvars['httpdRestart'] = "";

if (!empty($REQUEST['httpdRestart']) && $REQUEST['httpdRestart'] == "check") {
	$displayvars['httpdRestart'] = $REQUEST['httpdRestart'];
}

if ($quietmode) {
	if($action == 'process') {
		header('Content-type: application/octet-stream');
		// Turn off output buffering
		ini_set('output_buffering', 'off');
		// Turn off PHP output compression
		ini_set('zlib.output_compression', false);
		// Implicitly flush the buffer(s)
		ini_set('implicit_flush', true);
		ob_implicit_flush(true);
		// Clear, and turn off output buffering
		while (ob_get_level() > 0) {
			// Get the curent level
			$level = ob_get_level();
			// End the buffering
			ob_end_clean();
			// If the current level has not changed, abort
			if (ob_get_level() == $level) break;
		}
		// Disable apache output buffering/compression
		if (function_exists('apache_setenv')) {
			apache_setenv('no-gzip', '1');
			apache_setenv('dont-vary', '1');
		}
	}
}

$modules_local = $modulef->getinfo(false,false,true);

if ($online) {
	$security_issues_to_report = array();
	$modules_online = $modulef->getonlinexml();
	$security_array = !empty($modulef->security_array) ? $modulef->security_array : array();

	// $module_getonlinexml_error is a global set by module_getonlinexml()
	if ($module_getonlinexml_error) {
		$displayvars['warning'] = sprintf(_("Warning: Cannot connect to online repository(s) (%s). Online modules are not available."), $amp_conf['MODULE_REPO']);
		$online = 0;
		unset($modules_online);
	} else if (!is_array($modules_online)) {
		$displayvars['warning'] = sprintf(_("Warning: Error retrieving updates from online repository(s) (%s). Online modules are not available."), $amp_conf['MODULE_REPO']);
		$online = 0;
		unset($modules_online);
	} else {
		// check for local-only modules and see if they have online update data
		foreach(array_diff(array_keys($modules_local), array_keys($modules_online)) as $name) {
			if ($name === "builtin" || empty($modules_local[$name]['updateurl'])) {
				continue;
			}
			if (parse_url($modules_local[$name]['updateurl'], PHP_URL_SCHEME) === 'https') {
				$module_update_json = $modulef->url_get_contents($modules_local[$name]['updateurl'], "");
				if ($module_update_json && $module_update_data = json_decode($module_update_json, true)) {
					$modules_online[$name] = $module_update_data;
				}
			}
		}

		// combine online and local modules
		$modules = $modules_online;
		foreach (array_keys($modules) as $name) {
			if (isset($modules_local[$name])) {
				// combine in any other values in _local that aren't in _online
				$modules[$name] += $modules_local[$name];

				// explicitly override these values with the _local ones
				// - should never come from _online anyways, but this is just to be sure
				$modules[$name]['status'] = $modules_local[$name]['status'];
				$modules[$name]['dbversion'] = isset($modules_local[$name]['dbversion'])?$modules_local[$name]['dbversion']:'';
			} else {
				// not local, so it's not installed
				$modules[$name]['status'] = MODULE_STATUS_NOTINSTALLED;
			}
		}
		// add any remaining local-only modules
		$modules += $modules_local;

		// use online categories
		foreach (array_keys($modules) as $modname) {
			if (isset($modules_online[$modname]['category'])) {
				$modules[$modname]['category'] = $modules_online[$modname]['category'];
			}
		}
	}
}

if (!$quietmode) {
	show_view('views/module_admin/header.php',$displayvars);
}

if (!isset($modules)) {
	$modules = & $modules_local;
}

//Hide the only module that would end up confusing people
unset($modules['builtin']);
//--------------------------------------------------------------------------------------------------------
switch ($action) {
		case 'setrepo':
			$repo = str_replace("_repo","",$REQUEST['id']);
			$o = $modulef->set_active_repo($repo,$REQUEST['selected']);
			if($o) {
				echo json_encode(array("status" => true));
			} else {
				echo json_encode(array("status" => false, "message" => "Unable to set ".$repo." as active repo"));
			}
		break;
		case 'restarthttpd':
			if(\FreePBX::Modules()->checkStatus('sysadmin')){
				\FreePBX::Sysadmin()->runHook("update-ports");
			}
			break;
		case 'process':
			touch('/var/spool/asterisk/tmp/GuiUpdate.flag');
			$moduleactions = !empty($REQUEST['modules']) ? $REQUEST['modules'] : array();
			echo "<div id=\"moduleBoxContents\">";
			echo "<h4>"._("Please wait while module actions are performed")."</h4>\n";
			echo "<div id=\"moduleprogress\">";

			// stop output buffering, and send output
			@ ob_flush();
			flush();
			$change_tracks = array();
			foreach ($moduleactions as $modulename => $setting) {
				$didsomething = true; // set to false in default clause of switch() below..
				switch ($setting['action']) {
					case 'trackinstall':
					case 'trackupgrade':
						if (!EXTERNAL_PACKAGE_MANAGEMENT) {
							$track = !empty($setting['track']) ? $setting['track'] : 'stable';
							$trackinfo = ($track == 'stable') ? $modules_online[$modulename] : (!empty($modules_online[$modulename]['releasetracks'][$track]) ? $modules_online[$modulename]['releasetracks'][$track] : array());
							echo '<span class="success">'.sprintf(_("Upgrading %s to %s from track %s"),$modulename,$trackinfo['version'],$track)."</span><br/>";
							echo sprintf(_('Downloading %s'), $modulename).' <span id="downloadprogress_'.$modulename.'"></span><br/><span id="downloadstatus_'.$modulename.'"></span><br/>';
							if (is_array($errors = $modulef->download($trackinfo, false, 'download_progress'))) {
								echo '<span class="error">'.sprintf(_("Error(s) downloading %s"),$modulename).': ';
								echo '<ul><li>'.implode('</li><li>',$errors).'</li></ul>';
								echo '</span>';
							} else {
								echo '<span class="success">'.sprintf(_("Installing %s"),$modulename)."</span><br/>";
								echo '<span id="installstatus_'.$modulename.'"></span>';
								//2nd param of install set to true to force the install as it may not be a detected upgrade
								if (is_array($errors = $modulef->install($modulename,true))) {
									echo '<span class="error">'.sprintf(_("Error(s) installing %s"),$modulename).': ';
									echo '<ul><li>'.implode('</li><li>',$errors).'</li></ul>';
									echo '</span>';
								} else {
									$change_tracks[$modulename] = $setting['track'];
									echo '<span class="success">'.sprintf(_("%s installed successfully"),$modulename).'</span><br/>';
								}
							}
						}
						break;
					case 'rollback':
						if (!EXTERNAL_PACKAGE_MANAGEMENT) {
							$releaseinfo = '';
							foreach($modules_online[$modulename]['previous'] as $release) {
								if($release['version'] == $setting['rollback']) {
									$releaseinfo = $release;
									break;
								}
							}
							echo '<span class="success">'.sprintf(_("Rolling back %s to %s"),$modulename, $setting['rollback'])."</span><br/>";
							echo sprintf(_('Downloading %s'), $modulename).' <span id="downloadprogress_'.$modulename.'"></span><span id="downloadstatus_'.$modulename.'"></span><br/>';
							if (is_array($errors = $modulef->download($releaseinfo, false, 'download_progress'))) {
								echo '<span class="error">'.sprintf(_("Error(s) downloading %s"),$modulename).': ';
								echo '<ul><li>'.implode('</li><li>',$errors).'</li></ul>';
								echo '</span>';
							} else {
								echo '<span class="success">'.sprintf(_("Installing %s"),$modulename)."</span><br/>";
								echo '<span id="installstatus_'.$modulename.'"></span>';
								//2nd param of install set to true to force the install as it may not be a detected upgrade
								if (is_array($errors = $modulef->install($modulename,true))) {
									echo '<span class="error">'.sprintf(_("Error(s) installing %s"),$modulename).': ';
								}
							}
						}
							break;
					case 'trackinstall':
					case 'trackupgrade':
						if (!EXTERNAL_PACKAGE_MANAGEMENT) {
							$track = !empty($setting['track']) ? $setting['track'] : 'stable';
							$trackinfo = ($track == 'stable') ? $modules_online[$modulename] : (!empty($modules_online[$modulename]['releasetracks'][$track]) ? $modules_online[$modulename]['releasetracks'][$track] : array());
							echo '<span class="success">' . sprintf(_("Upgrading %s to %s from track %s"), $modulename, $trackinfo['version'], $track) . "</span><br/>";
							echo sprintf(_('Downloading %s'), $modulename) . ' <span id="downloadprogress_' . $modulename . '"></span><br/><span id="downloadstatus_' . $modulename . '"></span><br/>';
							if (is_array($errors = $modulef->download($trackinfo, false, 'download_progress'))) {
								echo '<span class="error">'.sprintf(_("Error(s) downloading %s"),$modulename).': ';
								echo '<ul><li>'.implode('</li><li>',$errors).'</li></ul>';
								echo '</span>';
							} else {
								echo '<span class="success">'.sprintf(_("Installing %s"),$modulename)."</span><br/>";
								echo '<span id="installstatus_'.$modulename.'"></span>';
								//2nd param of install set to true to force the install as it may not be a detected upgrade
								if (is_array($errors = $modulef->install($modulename,true))) {
									echo '<span class="error">'.sprintf(_("Error(s) installing %s"),$modulename).': ';
									echo '<ul><li>'.implode('</li><li>',$errors).'</li></ul>';
									echo '</span>';
								} else {
									$change_tracks[$modulename] = $setting['track'];
									echo '<span class="success">'.sprintf(_("%s installed successfully"),$modulename).'</span><br/>';
								}
							}
						}
					break;
				case 'force_upgrade':
				case 'upgrade':
				case 'downloadinstall':
				case 'upgradeignoreconflicts':
					if (!EXTERNAL_PACKAGE_MANAGEMENT) {
						$track = $setting['track'];
						$trackinfo = ($track == 'stable') ? $modules_online[$modulename] : (!empty($modules_online[$modulename]['releasetracks'][$track]) ? $modules_online[$modulename]['releasetracks'][$track] : array());
						echo '<span class="success">'.sprintf(_("Downloading and Installing %s"),$modulename)."</span><br/>";
						echo sprintf(_('Downloading %s'), $modulename).' <span id="downloadprogress_'.$modulename.'"></span><span id="downloadstatus_'.$modulename.'"></span><br/>';
						$errors = $modulef->download($trackinfo, false, 'download_progress', ($action == 'upgradeignoreconflicts'));
						if (is_array($errors)) {
							echo '<span class="error">'.sprintf(_("Error(s) downloading %s"),$modulename).': ';
							echo '<ul><li>'.implode('</li><li>',$errors).'</li></ul>';
							echo '</span>';
						} else {
							echo '<span class="success">'.sprintf(_("Installing %s"),$modulename)."</span><br/>";
							echo '<span id="installstatus_'.$modulename.'"></span>';
							if (is_array($errors = $modulef->install($modulename,($setting['action'] == "force_upgrade"), ($action == 'upgradeignoreconflicts')))) {
								echo '<span class="error">'.sprintf(_("Error(s) installing %s"),$modulename).': ';
								echo '<ul><li>'.implode('</li><li>',$errors).'</li></ul>';
								echo '</span>';
							} else {
								echo '<span class="success">'.sprintf(_("%s installed successfully"),$modulename).'</span><br/>';
							}
						}
					}
					break;
				case 'install':
				case 'installignoreconflicts':
					if (!EXTERNAL_PACKAGE_MANAGEMENT) {
						echo '<span class="success">'.sprintf(_("Installing %s"),$modulename)."</span><br/>";
						echo '<span id="installstatus_'.$modulename.'"></span>';
						if (is_array($errors = $modulef->install($modulename, ($action == 'installignoreconflicts')))) {
							echo '<span class="error">'.sprintf(_("Error(s) installing %s"),$modulename).': ';
							echo '<ul><li>'.implode('</li><li>',$errors).'</li></ul>';
							echo '</span>';
						} else {
							echo '<span class="success">'.sprintf(_("%s installed successfully"),$modulename).'</span><br/>';
						}
					}
					break;
				case 'enable':
					echo '<span class="success">'.sprintf(_("Enabling %s"),$modulename)."</span><br/>";
					echo '<span id="installstatus_'.$modulename.'"></span>';
					if (is_array($errors = $modulef->enable($modulename))) {
						echo '<span class="error">'.sprintf(_("Error(s) enabling %s"),$modulename).': ';
						echo '<ul><li>'.implode('</li><li>',$errors).'</li></ul>';
						echo '</span>';
					} else {
						echo '<span class="success">'.sprintf(_("%s enabled successfully"),$modulename).'</span><br/>';
					}
					break;
				case 'disable':
					echo '<span class="success">'.sprintf(_("Disabling %s"),$modulename)."</span><br/>";
					echo '<span id="installstatus_'.$modulename.'"></span>';
					if (is_array($errors = $modulef->disable($modulename))) {
						echo '<span class="error">'.sprintf(_("Error(s) disabling %s"),$modulename).': ';
						echo '<ul><li>'.implode('</li><li>',$errors).'</li></ul>';
						echo '</span>';
					} else {
						echo '<span class="success">'.sprintf(_("%s disabled successfully"),$modulename).'</span><br/>';
					}
					break;
				case 'uninstall':
					echo '<span class="success">'.sprintf(_("Uninstalling %s"),$modulename)."</span><br/>";
					echo '<span id="installstatus_'.$modulename.'"></span>';
					if (!EXTERNAL_PACKAGE_MANAGEMENT) {
						if (is_array($errors = $modulef->uninstall($modulename))) {
							echo '<span class="error">'.sprintf(_("Error(s) uninstalling %s"),$modulename).': ';
							echo '<ul><li>'.implode('</li><li>',$errors).'</li></ul>';
							echo '</span>';
						} else {
							echo '<span class="success">'.sprintf(_("%s uninstalled successfully"),$modulename).'</span><br/>';
						}
					}
					break;
				case 'remove':
					echo '<span class="success">'.sprintf(_("Removing %s"),$modulename)."</span><br/>";
					if (is_array($errors = $modulef->delete($modulename))) {
						echo '<span class="error">'.sprintf(_("Error(s) removing %s"),$modulename).': ';
						echo '<ul><li>'.implode('</li><li>',$errors).'</li></ul>';
						echo '</span>';
					} else {
						echo '<span class="success">'.sprintf(_("%s removed successfully"),$modulename).'</span><br/>';
					}
					break;
				case 'reinstall':
					echo '<span class="success">'.sprintf(_("Uninstalling %s"),$modulename)."</span><br/>";
					if (!EXTERNAL_PACKAGE_MANAGEMENT) {
						if (is_array($errors = $modulef->uninstall($modulename))) {
							echo '<span class="error">'.sprintf(_("Error(s) uninstalling %s"),$modulename).': ';
							echo '<ul><li>'.implode('</li><li>',$errors).'</li></ul>';
							echo '</span>';
						} else {
							echo '<span class="success">'.sprintf(_("%s uninstalled successfully"),$modulename).'</span>';
						}
						echo '<br/>';
						echo '<span class="success">'.sprintf(_("Installing %s"),$modulename)."</span><br/>";
						echo '<span id="installstatus_'.$modulename.'"></span>';
						if (is_array($errors = $modulef->install($modulename))) {
							echo '<span class="error">'.sprintf(_("Error(s) installing %s"),$modulename).': ';
							echo '<ul><li>'.implode('</li><li>',$errors).'</li></ul>';
							echo '</span>';
						} else {
							echo '<span class="success">'.sprintf(_("%s installed successfully"),$modulename).'</span><br/>';
						}
					}
				default:
					// just so we don't send an <hr> and flush()
					$didsomething = false;
			}

			if ($didsomething) {
				if(!empty($change_tracks)) {
					$modulef->set_tracks($change_tracks);
				}
				@ob_flush();
				flush();
			}
		}
		echo _("Updating Hooks...");
		try {
			$FreePBX->Hooks->updateBMOHooks();
		}catch(\Exception $e) {}
			echo _("Done")."<br />";
		echo "</div>";
		echo "<hr /><br />";
		@unlink('/var/spool/asterisk/tmp/GuiUpdate.flag');
		if ($quietmode) {
			echo '<a class="btn" href="#" onclick="parent.close_module_actions(true, true);" >'._("Return").'</a>';
		}
		break;
	case 'confirm':
		if(is_array($trackaction)) {
			ksort($trackaction);
		}
		if(is_array($moduleaction)) {
			ksort($moduleaction);
		}
			/* if updating language packs, make sure they are the last thing to be done so that
			any modules currently being updated at the same time will be done so first and
			language pack updates for those modules will be included.
			*/
		if (isset($moduleaction['fw_langpacks'])) {
			$tmp = $moduleaction['fw_langpacks'];
			unset($moduleaction['fw_langpacks']);
			$moduleaction['fw_langpacks'] = $tmp;
			unset($tmp);
		}

		echo "<form name=\"modulesGUI\" action=\"config.php\" method=\"post\">";
		echo "<input type=\"hidden\" name=\"display\" value=\"".$display."\" />";
		echo "<input type=\"hidden\" name=\"type\" value=\"".$type."\" />";
		echo "<input type=\"hidden\" name=\"online\" value=\"".$online."\" />";
		echo "<input type=\"hidden\" name=\"action\" value=\"process\" />";

		$actionstext = array();
		$force_actionstext = array();
		$errorstext = array();
		$moduleActions = array();
		$moduleaction = is_array($moduleaction) ? $moduleaction : array();
		foreach ($moduleaction as $module => $action) {
			$text = false;
			$skipaction = false;

			// make sure name is set. This is a problem for broken modules
			if (!isset($modules[$module]['name'])) {
				$modules[$module]['name'] = $module;
			}

			switch ($action) {
			case 'rollback':
				if (!EXTERNAL_PACKAGE_MANAGEMENT) {
					if(empty($modules_online[$module]['previous'])) {
						$skipaction = true;
						$errorstext[] = sprintf(_("%s cannot be rolledback, version %s is missing"), "<strong>".$modules[$module]['name']."</strong>", "<strong>".$REQUEST['version']."</strong>");
					}
					$previous_data = null;
					foreach($modules_online[$module]['previous'] as $release) {
						if($release['version'] == $REQUEST['version']) {
							$previous_data = $release;
							break;
						}
					}
					if(empty($previous_data)) {
						$skipaction = true;
						$errorstext[] = sprintf(_("%s cannot be rolledback, version %s is missing"), "<strong>".$modules[$module]['name']."</strong>", "<strong>".$REQUEST['version']."</strong>");
					}
					$dependerrors = $modulef->checkdepends($previous_data);
					$conflicterrors = $FreePBX->Modules->checkConflicts($previous_data);
					$rollbackerrors = false;
					if ($conflicterrors['breaking'] && is_array($conflicterrors['issues'][$modules[$module]['name']]) && !empty($conflicterrors['issues'][$modules[$module]['name']])) {
						$rollbackerrors = true;
						$skipaction = true;
						$errorstext[] = sprintf(_("%s cannot be upgraded: %s Please try again after the conflicts have been resolved."),
							"<strong>".$modules[$module]['name']."</strong>",'<strong><ul><li>'.implode('</li><li>',$rollbackconflicts).'</li></ul></strong>');
					}
					if (is_array($dependerrors)) {
						$rollbackerrors = true;
						$skipaction = true;
						$errorstext[] = sprintf(_("%s cannot be upgraded: %s Please try again after the dependencies have been installed."),
							"<strong>".$modules[$module]['name']."</strong>",'<strong><ul><li>'.implode('</li><li>',$dependerrors).'</li></ul></strong>');
					}
					if(!$rollbackconflicts){
						$actionstext[] =  sprintf(_("%s %s will be downloaded and rolled back to %s"), "<strong>".$modules[$module]['name']."</strong>", "<strong>".$modules[$module]['dbversion']."</strong>", "<strong>".$REQUEST['version']."</strong>");
					}
				}
				break;
			case 'upgradeignoreconflicts':
			case 'upgrade':
			case 'force_upgrade':
				if (!EXTERNAL_PACKAGE_MANAGEMENT) {
					$track = !empty($trackaction[$module]) ? $trackaction[$module] : 'stable';
					if(empty($modules_online)) {
						$modules_online = $modulef->getonlinexml();
					}
					$trackinfo = ($track == 'stable') ? $modules_online[$module] : (!empty($modules_online[$module]['releasetracks'][$track]) ? $modules_online[$module]['releasetracks'][$track] : array());
					if(!empty($modules_online) && $trackaction[$module] != $modules[$module]['track']) {
						$action = 'trackupgrade';
						if(empty($trackinfo)) {
							$skipaction = true;
							$errorstext[] = sprintf(_("%s cannot be upgraded to %s: The release track of %s does not exist for this module"),
								"<strong>".$modules[$module]['name']."</strong>","<strong>".$track."</strong>","<strong>".$track."</strong>");
						} elseif (is_array($errors = $modulef->checkdepends($trackinfo))) {
							$skipaction = true;
							$errorstext[] = sprintf(_("%s cannot be upgraded: %s Please try again after the dependencies have been installed."),
								"<strong>".$modules[$module]['name']."</strong>",'<strong><ul><li>'.implode('</li><li>',$errors).'</li></ul></strong>');
						} else {
							switch (version_compare_freepbx($modules[$module]['dbversion'], $trackinfo['version'])) {
							case '-1':
								$actionstext[] = sprintf(_("%s %s will be upgraded to online version %s and switched to the %s track"), "<strong>".$modules[$module]['name']."</strong>", "<strong>".$modules[$module]['dbversion']."</strong>", "<strong>".$trackinfo['version']."</strong>","<strong>".$track."</strong>");
								break;
							case '0':
								$force_actionstext[] = sprintf(_("%s %s will be re-installed to online version %s and switched to the %s track"), "<strong>".$modules[$module]['name']."</strong>", "<strong>".$modules[$module]['dbversion']."</strong>", "<strong>".$trackinfo['version']."</strong>","<strong>".$track."</strong>");
								break;
							default:
								$force_actionstext[] = sprintf(_("%s %s will be downgraded to online version %s and switched to the %s track"), "<strong>".$modules[$module]['name']."</strong>", "<strong>".$modules[$module]['dbversion']."</strong>", "<strong>".$trackinfo['version']."</strong>","<strong>".$track."</strong>");
							}
						}
					} else {
						$checkdone = false;
						$dependerrors = $modulef->checkdepends($trackinfo);
						$conflicterrors = $FreePBX->Modules->checkConflicts($trackinfo);
						$errorflag = false;
						if (is_array($dependerrors)) {
							$errorflag = true;
							$checkdone = true;
							$skipaction = true;
							$errorstext[] = sprintf(_("%s cannot be upgraded: %s Please try again after the dependencies have been installed."),
								"<strong>".$modules[$module]['name']."</strong>",'<strong><ul><li>'.implode('</li><li>',$dependerrors).'</li></ul></strong>');
						}
						if ($conflicterrors['breaking'] && isset($conflicterrors['issues'][$modules[$module]['name']]) && !empty($conflicterrors['issues'][$modules[$module]['name']]) && $action != 'upgradeignoreconflicts') {
							$errorflag = true;
							$checkdone = true;
							$skipaction = true;
							$errorstext[] = sprintf(_("%s cannot be upgraded: %s Please try again after the conflicts have been corrected."),
								"<strong>".$modules[$module]['name']."</strong>",'<strong><ul><li>'.implode('</li><li>',$conflicterrors['issues'][$modules[$module]['name']]).'</li></ul></strong>');
						}
						if(!$errorflag && !$checkdone){
							switch ( version_compare_freepbx($modules[$module]['dbversion'], $trackinfo['version'])) {
							case '-1':
								$actionstext[] = sprintf(_("%s %s will be upgraded to online version %s"), "<strong>".$modules[$module]['name']."</strong>", "<strong>".$modules[$module]['dbversion']."</strong>", "<strong>".$trackinfo['version']."</strong>");
								break;
							case '0':
								$force_actionstext[] = sprintf(_("%s %s will be re-installed to online version %s"), "<strong>".$modules[$module]['name']."</strong>", "<strong>".$modules[$module]['dbversion']."</strong>", "<strong>".$trackinfo['version']."</strong>");
								break;
							default:
								$force_actionstext[] = sprintf(_("%s %s will be downgraded to online version %s"), "<strong>".$modules[$module]['name']."</strong>", "<strong>".$modules[$module]['dbversion']."</strong>", "<strong>".$trackinfo['version']."</strong>");
							}
						}
					}
				}
				break;
			case 'downloadinstall':
				if (!EXTERNAL_PACKAGE_MANAGEMENT) {
					$track = !empty($trackaction[$module]) ? $trackaction[$module] : 'stable';
					if($track != $modules[$module]['track']) {
						$action = 'trackinstall';
						$trackinfo = ($track == 'stable') ? $modules_online[$module] : (!empty($modules_online[$module]['releasetracks'][$track]) ? $modules_online[$module]['releasetracks'][$track] : array());
					}

					if(!empty($trackinfo)){
						$dependerrors = $modulef->checkdepends($trackinfo);
						$conflicterrors = $FreePBX->Modules->checkConflicts($trackinfo);
					}
					if(empty($trackinfo)) {
						$dependerrors = false;
						$conflicterrors = false;
						$skipaction = true;
						$errorstext[] = sprintf(_("%s cannot be upgraded to %s: The release track of %s does not exist for this module"),
							"<strong>".$modules[$module]['name']."</strong>","<strong>".$track."</strong>","<strong>".$track."</strong>");
					}
					if(is_array($dependerrors)) {
						$skipaction = true;
						$di = true;
						$errorstext[] = sprintf(_("%s cannot be installed: %s Please try again after the dependencies have been installed."),
							"<strong>".$modules[$module]['name']."</strong>",'<strong><ul><li>'.implode('</li><li>',$dependerrors).'</li></ul></strong>');
					}
					if($conflicterrors['breaking'] && is_array($conflicterrors['issues'][$modules[$module]['name']]) && !empty($conflicterrors['issues'][$modules[$module]['name']])) {
						$skipaction = true;
						$di = true;
						$errorstext[] = sprintf(_("%s cannot be installed: %s Please try again after the conflicts have been installed."),
							"<strong>".$modules[$module]['name']."</strong>",'<strong><ul><li>'.implode('</li><li>',$conflicterrors['issues'][$modules[$module]['name']]).'</li></ul></strong>');
					}
					if(!empty($trackinfo) && !$di){
						$actionstext[] =  sprintf(_("%s %s will be downloaded and installed and switched to the %s track"), "<strong>".$modules[$module]['name']."</strong>", "<strong>".$trackinfo['version']."</strong>","<strong>".$track."</strong>");
					}

				}
				break;
			case 'installignoreconflicts':
				if (!EXTERNAL_PACKAGE_MANAGEMENT) {
					$dependerrors = $modulef->checkdepends($trackinfo);
					$issues = false;
					if (is_array($dependerrors)) {
						$skipaction = true;
						$issues = true;
						$errorstext[] = sprintf(
							(($modules[$module]['status'] == MODULE_STATUS_NEEDUPGRADE) ? _("%s cannot be upgraded: %s Please try again after the dependencies have been installed.") : _("%s cannot be installed: %s Please try again after the dependencies have been installed.")),
							"<strong>" . $modules[$module]['name'] . "</strong>",
							'<strong><ul><li>' . implode('</li><li>', $dependerrors) . '</li></ul></strong>'
						);
					}

					if (!$issues) {
						if ($modules[$module]['status'] == MODULE_STATUS_NEEDUPGRADE) {
							$actionstext[] = sprintf(_("Conflicts have been ignored, %s %s will be upgraded to %s"), "<strong>" . $modules[$module]['name'] . "</strong>", "<strong>" . $modules[$module]['dbversion'] . "</strong>", "<strong>" . $modules[$module]['version'] . "</strong>");
						} else {
							$actionstext[] = sprintf(_("Conflicts have been ignored, %s %s will be installed and enabled"), "<strong>" . $modules[$module]['name'] . "</strong>", "<strong>" . $modules[$module]['version'] . "</strong>");
						}
					}
				}
				break;
			case 'install':
				if (!EXTERNAL_PACKAGE_MANAGEMENT) {
					$dependerrors = $modulef->checkdepends($trackinfo);
					$conflicterrors = $FreePBX->Modules->checkConflicts($trackinfo);
					$issues = false;
					if (is_array($dependerrors)) {
						$skipaction = true;
						$issues = true;
						$errorstext[] = sprintf((($modules[$module]['status'] == MODULE_STATUS_NEEDUPGRADE) ?  _("%s cannot be upgraded: %s Please try again after the dependencies have been installed.") : _("%s cannot be installed: %s Please try again after the dependencies have been installed.") ),
							"<strong>".$modules[$module]['name']."</strong>",'<strong><ul><li>'.implode('</li><li>',$dependerrors).'</li></ul></strong>');
					}
					if ($conflicterrors['breaking'] && is_array($conflicterrors['issues'][$modules[$module]['name']]) && !empty($conflicterrors['issues'][$modules[$module]['name']])) {
						$skipaction = true;
						$issues = true;
						$errorstext[] = sprintf(_("%s cannot be upgraded: %s Please try again after the conflicts have been resolved."),
							"<strong>".$modules[$module]['name']."</strong>",'<strong><ul><li>'.implode('</li><li>',$conflicterrors['issues'][$modules[$module]['name']]).'</li></ul></strong>');
					}
					if(!$issues){
						if ($modules[$module]['status'] == MODULE_STATUS_NEEDUPGRADE) {
							$actionstext[] =  sprintf(_("%s %s will be upgraded to %s"), "<strong>".$modules[$module]['name']."</strong>", "<strong>".$modules[$module]['dbversion']."</strong>", "<strong>".$modules[$module]['version']."</strong>");
						} else {
							$actionstext[] =  sprintf(_("%s %s will be installed and enabled"), "<strong>".$modules[$module]['name']."</strong>", "<strong>".$modules[$module]['version']."</strong>");
						}
					}
				}
				break;
			case 'enable':
				$dependerrors = $modulef->checkdepends($modules[$module]);
				$conflicterrors = $FreePBX->Modules->checkConflicts($modules[$module]);
				$issues = false;
				if (is_array($dependerrors)) {
					$skipaction = true;
					$issues = true;
					$errorstext[] = sprintf(_("%s cannot be enabled: %s Please try again after the dependencies have been installed."),
						"<strong>".$modules[$module]['name']."</strong>",'<strong><ul><li>'.implode('</li><li>',$dependserrors).'</li></ul></strong>');
				}
				if ($conflicterrors['breaking'] && is_array($conflicterrors['issues'][$modules[$module]['name']]) && !empty($conflicterrors['issues'][$modules[$module]['name']])) {
					$skipaction = true;
					$issues = true;
					$errorstext[] = sprintf(_("%s cannot be enabled: %s Please try again after the conflicts have been resolved."),
						"<strong>".$modules[$module]['name']."</strong>",'<strong><ul><li>'.implode('</li><li>',$conflicterrors['issues'][$modules[$module]['name']]).'</li></ul></strong>');
				}
				if(!$issues){
					$actionstext[] =  sprintf(_("%s %s will be enabled"), "<strong>".$modules[$module]['name']."</strong>", "<strong>".$modules[$module]['dbversion']."</strong>");
				}
				break;
			case 'disable':
				if (is_array($errors = $modulef->reversedepends($modules[$module]))) {
					$skipaction = true;
					$errorstext[] = sprintf(_("%s cannot be disabled because the following modules depend on it: %s Please disable those modules first then try again."),
						"<strong>".$modules[$module]['name']."</strong>",'<strong><ul><li>'.implode('</li><li>',$errors).'</li></ul></strong>');
				} else {
					$actionstext[] =  sprintf(_("%s %s will be disabled"), "<strong>".$modules[$module]['name']."</strong>", "<strong>".$modules[$module]['dbversion']."</strong>");
				}
				break;
			case 'uninstall':
				if (!EXTERNAL_PACKAGE_MANAGEMENT) {
					if (is_array($errors = $modulef->reversedepends($modules[$module]))) {
						$skipaction = true;
						$errorstext[] = sprintf(_("%s cannot be uninstalled because the following modules depend on it: %s Please disable those modules first then try again."),
							"<strong>".$modules[$module]['name']."</strong>",'<strong><ul><li>'.implode('</li><li>',$errors).'</li></ul></strong>');
					} else {
						$actionstext[] =  sprintf(_("%s %s will be uninstalled"), "<strong>".$modules[$module]['name']."</strong>", "<strong>".$modules[$module]['dbversion']."</strong>");
					}
				}
				break;
			case 'remove':
				if (is_array($errors = $modulef->reversedepends($modules[$module]))) {
					$skipaction = true;
					$errorstext[] = sprintf(_("%s cannot be removed because the following modules depend on it: %s Please disable those modules first then try again."),
						"<strong>".$modules[$module]['name']."</strong>",'<strong><ul><li>'.implode('</li><li>',$errors).'</li></ul></strong>');
				} else {
					$actionstext[] =  sprintf(_("%s will be removed from the hard disk"), "<strong>".$modules[$module]['name']."</strong>");
				}
				break;
			case 'reinstall':
				if (!EXTERNAL_PACKAGE_MANAGEMENT) {
					if (is_array($errors = $modulef->reversedepends($modules[$module]))) {
						$skipaction = true;
						$errorstext[] = sprintf(_("%s cannot be reinstalled because the following modules depend on it: %s Please disable those modules first then try again."),
							"</strong>".$modules[$module]['name']."</strong>",'<strong><ul><li>'.implode('</li><li>',$errors).'</li></ul></strong>');
					} else {
						$actionstext[] =  sprintf(_("%s %s will be reinstalled"), "<strong>".$modules[$module]['name']."</strong>", "<strong>".$modules[$module]['dbversion']."</strong>");
					}
				}
				break;
			}

			// If error above we skip this action so we can proceed with the others
			//
			if (!$skipaction && $action != "0") { //TODO
				$moduleActions[$module]['action'] = $action;
				$moduleActions[$module]['track'] = $trackaction[$module];
				$moduleActions[$module]['version'] = $modules[$module]['version'];
				if($action== 'rollback') {
					$moduleActions[$module]['rollback'] = $REQUEST['version'];
				}
			}
		}
		echo "\t<script type=\"text/javascript\"> var moduleActions = ".json_encode($moduleActions).";</script>\n";

		// Write out the errors, if there are additional actions that can be accomplished list those next with the choice to
		// process which will ignore the ones with errors but process the rest.
		//
		if (count($errorstext) > 0) {
			echo "<h4>"._("Errors with selection:")."</h4>\n";
			echo "<ul>\n";
			foreach ($errorstext as $text) {
				echo "\t<li>".$text."</li>\n";
			}
			echo "</ul>";
		}
		if (count($actionstext) > 0 || count($force_actionstext) > 0) {
			if (count($errorstext) > 0) {
				echo "<h4>"._("You may confirm the remaining selection and then try the again for the listed issues once the required dependencies have been met:")."</h4>\n";
			} else {
				echo "<h4>"._("Please confirm the following actions:")."</h4>\n";
			}
			if (count($actionstext)) {
				echo "<h5>"._("Upgrades, installs, enables and disables:")."</h5>\n";
				echo "<ul>\n";
				foreach ($actionstext as $text) {
					echo "\t<li>".$text."</li>\n";
				}
				echo "</ul>";
			}
			if (count($force_actionstext)) {
				echo "<h5>"._("Forced downgrades and re-installs:")."</h5>\n";
				echo "<ul>\n";
				foreach ($force_actionstext as $text) {
					echo "\t<li>".$text."</li>\n";
				}
				echo "</ul>";
			}
			echo "\t<input type=\"button\" value=\""._("Confirm")."\" name=\"process\" onclick=\"process_module_actions(moduleActions);\" />";
		} else {
			echo "<h4>"._("No actions to perform")."</h4>\n";
			echo "<p>"._("Please select at least one action to perform by clicking on the module, and selecting an action on the \"Action\" tab.")."</p>";
		}

		echo "\t<input type=\"button\" value=\""._("Cancel")."\" onclick=\"location.href = 'config.php?display=modules&amp;type=$type&amp;online=$online';\" />";
		echo "</form>";
		break;
	case 'upload':
		// display links
		$displayvars = array();
		if (!EXTERNAL_PACKAGE_MANAGEMENT) {
			$disp_buttons[] = 'local';
			if (isset($_FILES['uploadmod']) && !empty($_FILES['uploadmod']['name'])) {
				// display upload button, only if they did upload something
				$disp_buttons[] = 'upload';
			}
			$displayvars['repo_select'] = displayRepoSelect($disp_buttons);
		} else {
			$displayvars['repo_select'] = array();
			echo "<a href='config.php?display=modules&amp;type=$type'>"._("Manage local modules")."</a>\n";
		}

		$displayvars['processed'] = false;
		if (isset($REQUEST['upload']) && isset($_FILES['uploadmod']) && !empty($_FILES['uploadmod']['name'])) {
			$displayvars['res'] = $modulef->handleupload($_FILES['uploadmod']);
			$displayvars['processed'] = true;
		} elseif (isset($REQUEST['download']) && !empty($REQUEST['remotemod'])) {
			$displayvars['res'] = $modulef->handledownload($REQUEST['remotemod']);
			$displayvars['processed'] = true;
		} elseif(isset($REQUEST['remotemod'])) {
			$displayvars['res'][] = 'Nothing to download or upload';
			$displayvars['processed'] = true;
		}

		show_view('views/module_admin/upload.php',$displayvars);
		break;
	case 'online':
	default:
		@uasort($modules, function($a, $b) {
			if(is_array($a['category']) || is_array($b['category']) || is_array($a['name']) || is_array($b['name'])) {
				//bad module xml
				return 0;
			}
			if (!isset($a['category']) || !isset($b['category'])) {
				if (!isset($a['name']) || !isset($b['name'])) {
					return 0;
				} else {
					return strcmp($a['name'], $b['name']);
				}
			}
			// sort by category..
			$catcomp = strcmp($a['category'], $b['category']);
			if ($catcomp == 0) {
				// .. then by name
				return strcmp($a['name'], $b['name']);
			} elseif ($a['category'] == 'Basic') {
				return -1;
			} elseif ($b['category'] == 'Basic') {
				return 1;
			} else {
				return $catcomp;
			}
		});

		$local_repo_list = array();
		$remote_repo_list = array();
		$broken_module_list = array();
		$repo_exclude = array('local','broken');
		foreach($modules as $mod => &$module) {
			if(in_array($module['repo'],array('broken'))) {
				$rpo = $module['repo'];
				$broken_module_list[$rpo][] = $mod;
				continue;
			}
			if(empty($module['repo'])) {
				$module['repo'] = 'Unknown';
			}
			if(!in_array($module['repo'],$local_repo_list) && empty($module['raw']['online']) && !in_array($module['repo'],$repo_exclude)) {
				$local_repo_list[] = $module['repo'];
			}
			$raw = $module['rawname'];
			if(!in_array($module['repo'],$remote_repo_list) && !empty($modules_online[$raw]) && !in_array($module['repo'],$repo_exclude)) {
				$remote_repo_list[] = $module['repo'];
			}
		}

		$repo_list = array_unique(array_merge($local_repo_list, $remote_repo_list));

		//Stupidness when people captialize repos.
		foreach($repo_list as &$r) {
			$r = strtolower($r);
		}
		//cheaty hack to move standard to the front :-)
		//and it works because we do array_unique later
		//TODO: Probably do some ordering here maybe?
		array_unshift($repo_list, 'standard');

		if ($online) {

			if(!empty($remote_repo_list)) {
				$modulef->set_remote_repos($remote_repo_list);
			}
			$active_repos = $modulef->get_active_repos();

			// Check for announcements such as security advisories, required updates, etc.
			//
			$displayvars['announcements'] = $modulef->get_annoucements();

			if (!EXTERNAL_PACKAGE_MANAGEMENT) {
				$displayvars['repo_select'] = displayRepoSelect(array(),false,array_unique($repo_list));
			}
		} else {
			$repo_list = array_merge($repo_list,$modulef->get_remote_repos($online));
			if (!EXTERNAL_PACKAGE_MANAGEMENT) {
				$displayvars['repo_select'] = displayRepoSelect(array('upload'),true,array_unique($repo_list));
			}
		}

		$category = false;
		$numdisplayed = 0;
		if ($amp_conf['USE_FREEPBX_MENU_CONF']) {
			$fd = $amp_conf['ASTETCDIR'].'/freepbx_menu.conf';
		} else {
			$fd = $amp_conf['ASTETCDIR'].'/freepbx_module_admin.conf';
		}
		$module_filter = array();
		$module_filter_new = array();
		if (file_exists($fd)) {
			$module_filter = @parse_ini_file($fd,true);
			if(count($module_filter) == 1 && !empty($module_filter['general'])) {
				$module_filter = $module_filter['general'];
			} elseif(count($module_filter) > 1) {
				$module_filter_new = $module_filter;
				$module_filter = array();
			} else {
				$module_filter = array();
			}
		}
		$module_display = array();
		$category = null;
		$sysadmininfo = $modulef->getinfo('sysadmin');
		foreach (array_keys($modules) as $name) {
			if (!isset($modules[$name]['category'])) {
				$modules[$name]['category'] = _("Broken");
				$modules[$name]['name'] = $name;
			}
			if (isset($module_filter[$name]) && strtolower(trim($module_filter[$name])) == 'hidden') {
				continue;
			}

			if (isset($module_filter_new[$name])) {
				if(isset($module_filter_new[$name]['hidden']) && strtolower($module_filter_new[$name]['hidden']) == "yes") {
					continue;
				}
				if(isset($module_filter_new[$name]['category'])) {
					$modules[$name]['category'] = $module_filter_new[$name]['category'];
				}
				if(isset($module_filter_new[$name]['name'])) {
					$modules[$name]['name'] = $module_filter_new[$name]['name'];
				}
			}

			// Theory: module is not in the defined repos, and since it is not local (meaning we loaded it at some point) then we
			//         don't show it. Exception, if the status is BROKEN then we should show it because it was here once.
			//
			if ((!isset($active_repos[$modules[$name]['repo']]) || !$active_repos[$modules[$name]['repo']])
				&& $modules[$name]['status'] != MODULE_STATUS_BROKEN && !isset($modules_local[$name])) {
					continue;
				}

			//block install,uninstall,reinstall
			$modules[$name]['blocked']['status'] = false;
			if(!empty($modules[$name]['depends'])) {
				$depends = $modulef->checkdepends($modules[$name]);
				if($depends !== true && is_array($depends)) {
					$modules[$name]['blocked']['status'] = true;
					$modules[$name]['blocked']['reasons'] = $depends;
				}
			}

			if(isset($modules[$name]['commercial'])) {
				$modules[$name]['commercial']['status'] = true;
				// Has this module got an expiration?
				if (isset($modules[$name]['updatesexpire'])) {
					$modules[$name]['commercial']['updatesexpire'] = $modules[$name]['updatesexpire'];
					if (isset($modules[$name]['unavail'])) {
						$modules[$name]['commercial']['unavail'] = $modules[$name]['unavail'];
					}
				}
				if(function_exists('sysadmin_is_module_licensed')) {
					$modules[$name]['commercial']['sysadmin'] = true;
					$modules[$name]['commercial']['licensed'] = sysadmin_is_module_licensed($name);
				} else {
					//block all commercial installs until sysadmin is installed?
					if(isset($modules[$name]['blocked']['reasons']['sysadmin'])) { unset($modules[$name]['blocked']['reasons']['sysadmin']); }
				$modules[$name]['commercial']['sysadmin'] = false;
				$modules[$name]['commercial']['licensed'] = false;
				$modules[$name]['blocked']['status'] = ($name != 'sysadmin') ? true : false;
				if(!empty($sysadmininfo['sysadmin']['status'])) {
					switch($sysadmininfo['sysadmin']['status']) {
					case MODULE_STATUS_DISABLED:
						$sysstatus = _('is disabled');
						break;
					case MODULE_STATUS_BROKEN:
						$sysstatus = _('is broken');
						break;
					case MODULE_STATUS_NEEDUPGRADE:
						$sysstatus = _('needs to be upgraded');
						break;
					case MODULE_STATUS_CONFLICT:
						$sysstatus = _('has one or more conflicts');
						break;
					default:
						$sysstatus = _('is unknown');
						break;
					}
				} else {
					$sysstatus = _('is not installed');
				}
				$modules[$name]['blocked']['reasons']['sysadmin'] = sprintf(_('Module <strong>%s</strong> is required, yours %s'),'System Admin',$sysstatus);
				}
				$modules[$name]['commercial']['purchaselink'] = !empty($modules[$name]['commercial']['link']) ? $modules[$name]['commercial']['link'] : 'https://www.freepbx.org/add-ons/';
			} else {
				$modules[$name]['commercial']['status'] = false;
			}

			// If versionupgrade module is present then allow it to skip modules that should not be presented
			// because an upgrade is in process. This can help assure only safe modules are present and
			// force the user to upgrade in the proper order.
			//
			if (function_exists('versionupgrade_allowed_modules') && !versionupgrade_allowed_modules($modules[$name])) {
				continue;
			}
			$numdisplayed++;

			if ($category !== $modules[$name]['category']) {
				$category = !empty($modules[$name]['category']) ? $modules[$name]['category'] : "other";
				$module_display[$category]['name'] = $category;
			}

			$module_display[$category]['data'][$name] = $modules[$name];

			$loc_domain = $name;
			$name_text = modgettext::_($modules[$name]['name'], $loc_domain);
			$module_display[$category]['data'][$name]['loc_domain'] = $loc_domain;
			$module_display[$category]['data'][$name]['name_text'] = $name_text;
			$module_display[$category]['data'][$name]['statusClass'] = 'stuff';

			$headerclass = "moduleheader";
			$module_display[$category]['data'][$name]['signature']['message'] = "";
			switch($modules[$name]['status']) {
			case MODULE_STATUS_NOTINSTALLED:
				$headerclass .= ' notinstalled';
				break;
			case MODULE_STATUS_DISABLED:
				$headerclass .= ' disabled';
				break;
			case MODULE_STATUS_ENABLED:
				$headerclass .= ' enabled';
				break;
			case MODULE_STATUS_NEEDUPGRADE:
				$headerclass .= ' needupgrade';
				break;
			case MODULE_STATUS_BROKEN:
			case MODULE_STATUS_CONFLICT:
				$headerclass .= ' broken';
				break;
			}
			if($FreePBX->Config->get('SIGNATURECHECK')) {
				$FreePBX->GPG;
				if(!empty($modules[$name]['signature']) && is_int($modules[$name]['signature']['status']) && (~$modules[$name]['signature']['status'] & \FreePBX\GPG::STATE_GOOD)) {
					switch(true) {
					case $modules[$name]['signature']['status'] & \FreePBX\GPG::STATE_TAMPERED:
						$headerclass .= " tampered";
						$module_display[$category]['data'][$name]['signature']['message'] = _("Module has been tampered. Please redownload");
						break;
					case $modules[$name]['signature']['status'] & \FreePBX\GPG::STATE_UNSIGNED:
						$headerclass .= " unsigned";
						$module_display[$category]['data'][$name]['signature']['message'] = _("Module is Unsigned");
						break;
					case $modules[$name]['signature']['status'] & \FreePBX\GPG::STATE_INVALID:
						$headerclass .= " invalid";
						$module_display[$category]['data'][$name]['signature']['message'] = _("Module has been signed with an invalid key");
						break;
					case $modules[$name]['signature']['status'] & \FreePBX\GPG::STATE_REVOKED:
						$headerclass .= " revoked";
						$module_display[$category]['data'][$name]['signature']['message'] = _("Module has been revoked and can not be enabled");
						break;
					default:
					}
				} else if(!empty($modules[$name]['signature']) && is_int($modules[$name]['signature']['status']) && ($modules[$name]['signature']['status'] & \FreePBX\GPG::STATE_GOOD)) {
					$module_display[$category]['data'][$name]['signature']['message'] = _("Good");
				} else {
					$headerclass = "moduleheader";
					$module_display[$category]['data'][$name]['signature']['message'] = _("Unknown");
				}
			}

			$salert = isset($modules[$name]['vulnerabilities']);
			$module_display[$category]['data'][$name]['mclass'] = $salert ? "moduleheader modulevulnerable" : $headerclass;

			if ($salert) {
				$module_display[$category]['data'][$name]['vulnerabilities'] = $modules[$name]['vulnerabilities'];
				foreach ($modules[$name]['vulnerabilities']['vul'] as $vul) {
					$security_issues_to_report[$vul] = true;
				}
			} else {
				$module_display[$category]['data'][$name]['vulnerabilities'] = array();
			}

			$module_display[$category]['data'][$name]['raw']['online'] = !empty($modules_online[$name]) ? $modules_online[$name] : array();
			$module_display[$category]['data'][$name]['raw']['local'] = !empty($modules_local[$name]) ? $modules_local[$name] : array();
			$module_display[$category]['data'][$name]['name'] = $name;
			$module_display[$category]['data'][$name]['pretty_name'] = !empty($name_text) ? $name_text  : $name;
			$module_display[$category]['data'][$name]['repo'] = $modules[$name]['repo'];
			$module_display[$category]['data'][$name]['dbversion'] = !empty($modules[$name]['dbversion']) ? $modules[$name]['dbversion'] : '';
			$module_display[$category]['data'][$name]['publisher'] = !empty($modules[$name]['publisher']) ? $modules[$name]['publisher'] : '';
			$module_display[$category]['data'][$name]['salert'] = $salert;

			if (!empty($modules_online[$name]['attention'])) {
				$module_display[$category]['data'][$name]['attention'] = nl2br(modgettext::_($modules[$name]['attention'], $loc_domain));
			}

			if (!empty($modules_online[$name]['changelog'])) {
				$module_display[$category]['data'][$name]['changelog'] = format_changelog($modules_online[$name]['changelog']);
			} elseif(!empty($modules_local[$name]['changelog'])) {
				$module_display[$category]['data'][$name]['changelog'] = format_changelog($modules_local[$name]['changelog']);
			}

			if(isset($module_display[$category]['data'][$name]['description'])) {
				if(is_array($module_display[$category]['data'][$name]['description'])) {
					$module_display[$category]['data'][$name]['description'] = _('The description tag of the module is duplicated in module.xml. Please have the maintainer correct it');
				} else {
					$module_display[$category]['data'][$name]['description'] = trim(preg_replace('/\s+/', ' ', $module_display[$category]['data'][$name]['description']));
				}
			} else {
				$module_display[$category]['data'][$name]['description'] = _('No Description');
			}

			if(!empty($module_display[$category]['data'][$name]['previous'])) {
				foreach($module_display[$category]['data'][$name]['previous'] as &$release) {
					if(preg_match("/".$release['version']."[\s|:|\*](.*)/m",$release['changelog'],$matches)) {
						$release['pretty_change'] = !empty($matches[1]) ? format_changelog($matches[1]) : _('No Change Log');
					}
				}
			}

			$track = !empty($modules_local[$name]['track']) ? $modules_local[$name]['track'] : 'stable';
			$module_display[$category]['data'][$name]['track'] = $track;
			if(!empty($module_display[$category]['data'][$name]['releasetracks'])) {
				$module_display[$category]['data'][$name]['tracks']['stable'] = false;
				$module_display[$category]['data'][$name]['tracks'][$track] = true;
				foreach($module_display[$category]['data'][$name]['releasetracks'] as $track => &$release) {
					if(!array_key_exists($track, $module_display[$category]['data'][$name]['tracks'])) {
						$module_display[$category]['data'][$name]['tracks'][$track] = false;
					}

					$release['changelog'] = format_changelog($release['changelog']);
				}
			} else {
				$module_display[$category]['data'][$name]['tracks']['stable'] = false;
				$module_display[$category]['data'][$name]['tracks'][$track] = true;
			}
		}


		$displayvars['end_msg'] = (isset($modules_online) && empty($numdisplayed)) ? (count($modules_online) > 0 ? _("All available modules are up-to-date and installed.") : _("No modules to display.") ) : '';
		$displayvars['finalmods'] = array();
		foreach($module_display as $cat) {
			foreach($cat['data'] as $mod => $info) {
				$displayvars['finalmods'][$mod] = $info;
			}
		}
		$displayvars['module_display'] = $module_display;
		$displayvars['devel'] = $amp_conf['DEVEL'];
		$displayvars['trackenable'] = $amp_conf['AMPTRACKENABLE'];
		$displayvars['brand'] = $FreePBX->Config->get("DASHBOARD_FREEPBX_BRAND");
		$displayvars['broken_module_list'] = $broken_module_list;

		// System Updates are only usable on FreePBX Distro style machines
		$su = new \FreePBX\Builtin\SystemUpdates();

		$summary = array(
			"edgemode" => ($amp_conf['MODULEADMINEDGE'] == 1),
			"totalmodules" => count($modules),
			"pbxversion" => $su->getDistroVersion(),
		);

		// If we're not compatible with SU, don't try to do anything.
		if (!$su->canDoSystemUpdates()) {
			$summary['candosystemupdates'] = false;
			$summary['sustemupdates'] = [];
			$summary['systemupdateavail'] = false;
			$summary['pendingupgradessystem'] = _("Integrated System Updates not available on this platform");
		} else {
			$summary['candosystemupdates'] = true;
			$summary['systemupdates'] = $su->getPendingUpdates();
			$summary['systemupdateavail'] = $summary['systemupdates']['pbxupdateavail'];
			$summary['pendingupgradessystem'] = count($summary['systemupdates']['rpms']);
		}

		// Which tab should be active?
		if ($module_page === "modules") {
			$summary['activetab'] = 'modules';
			$displayvars['activetab'] = 'modules';
		} else {
			$summary['activetab'] = 'summary';
			$displayvars['activetab'] = 'summary';
		}

		// Figure out the counts for our summary page.
		$activemodules = $modulef->getinfo(false, \MODULE_STATUS_ENABLED);
		//Because we do it to total
		unset($activemodules['builtin']);
		$summary["activemodules"] = count($activemodules);
		$summary["disabledmodules"] = count($modulef->getinfo(false, MODULE_STATUS_DISABLED));
		$summary["brokenmodules"] = count($modulef->getinfo(false, MODULE_STATUS_BROKEN));
		$summary["needsupgrademodules"] = count($modulef->getinfo(false, MODULE_STATUS_BROKEN));
		$cachedonline = $FreePBX->Modules->getCachedOnlineData();
		$summary["availupdates"] = $FreePBX->Modules->getUpgradeableModules($cachedonline['modules']);
		$summary["lastonlinecheck"] = $cachedonline['timestamp'];
		$displayvars['breaking'] = $FreePBX->Modules->checkConflicts();
		$summary["httpdRestart"] = ""; 
		if($displayvars['httpdRestart'] == 'check'){
			$summary["httpdRestart"] = show_view(__DIR__.'/views/module_admin/httpdRestart.php', []);
		}
		
		show_view(__DIR__.'/views/module_admin/tabheader.php', $summary);
		show_view(__DIR__.'/views/module_admin/tab-summary.php', $summary);
		$updates = new \FreePBX\Builtin\UpdateManager();
		show_view(__DIR__ . '/views/module_admin/tab-scheduler.php', $updates->getCurrentUpdateSettings());
		show_view(__DIR__ . '/views/module_admin/tab-modules.php',$displayvars);
		if ($summary['candosystemupdates']) {
			$summary['sysupdatepage'] = $su->getSystemUpdatesPage($summary['systemupdates']);
			show_view(__DIR__.'/views/module_admin/tab-systemupdates.php', $summary);
		} else {
			show_view(__DIR__.'/views/module_admin/unable-to-sysupdate.php');
		}
		show_view(__DIR__ . '/views/module_admin/tabfooter.php');
		break;
	}
if (!$quietmode) {
	$displayvars = array("security_issues" => array());
	if (!empty($security_issues_to_report)) {
		foreach (array_keys($security_issues_to_report) as $id) {
			if (!is_array($security_array[$id]['related_urls']['url'])) {
				$security_array[$id]['related_urls']['url'] = array($security_array[$id]['related_urls']['url']);
			}
			$tickets = format_ticket($security_array[$id]['tickets']);
			$displayvars['security_issues'][$id] = $security_array[$id];
			$displayvars['security_issues'][$id]['tickets'] = $tickets;
			$displayvars['security_issues'][$id]['related_urls_text'] = count($security_array[$id]['related_urls']['url']) == 1 ? _("Related URL") : _("Related URLs");
			$displayvars['security_issues'][$id]['related_urls'] = $security_array[$id]['related_urls']['url'];
		}
	}
	show_view('views/module_admin/footer.php',$displayvars);
}