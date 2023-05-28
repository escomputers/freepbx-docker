<?php
namespace FreePBX;
use modgettext;
class Destinations {
	private $dest_cache = [];
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Need to be instantiated with a FreePBX Object");
		}

		$this->FreePBX = $freepbx;
	}

	/**
	 * Get All destinations and popovers for all modules
	 *
	 * @method getAll
	 * @param  string            $index the destination set number (used when drawing multiple destination sets in a single form ie: digital receptionist)
	 * @return array Array of modules and destinations
	 */
	public function getAll($index = '') {
		$destinations = array();

		$modules = $this->FreePBX->Modules->getActiveModules();

		$destinations = array();
		foreach($modules as $rawname => $data) {
			$destArray = $this->getDestinationsByModule($rawname, $index);
			if(!empty($destArray)) {
				foreach($destArray as $dest) {
					list($cat, $id) = $this->getCategoryAndId($dest, $data);
					$destinations[$rawname][$id]['destinations'][$dest['destination']] = array_merge($dest, array(
						'name' => $data['name'],
						'edit_url' => isset($data['edit_url']) ? $data['edit_url'] : ""
					));
					if(!isset($destinations[$rawname][$id]['popover'])) {
						$destinations[$rawname][$id]['popover'] = [];
					}


					$destinations[$rawname][$id]['name'] = \modgettext::_($cat, $rawname);
					$destinations[$rawname][$id]['raw'] = $cat;
				}
			}
			$pops = $this->getPopoversByModule($rawname);
			if(!empty($pops)) {
				foreach($pops as $id => $info) {
					if(!isset($destinations[$rawname][$id]['destinations'])) {
						$destinations[$rawname][$id]['destinations'] = [];
					}
					$destinations[$rawname][$id]['popover'] = $info;
					$destinations[$rawname][$id]['name'] = \modgettext::_($info['name'], $rawname);
					$destinations[$rawname][$id]['raw'] = $info['name'];
				}
			}
		}
		return $destinations;
	}

	/**
	 * Check if a specific destination is being used, or get a list of all destinations that are being used
	 *
	 * Upon passing in an array of destinations, this api will query all modules to determine if any
	 * are using that destination. If so, it will return an array with the usage information
	 * as described below, otherwise an empty array. If passed boolean true, it will return an array
	 * of the same format with all destinations on the system that are being used.
	 *
	 * @method getAllInUseDestinations
	 * @param  boolean                 $destination an array of destinations to check against, or if boolean true then return list of all destinations in use
	 * @return array                               returns an empty array if destination not in use, or any array with usage info, or of all usage if dest is boolean true
	 * @example                               $dest_usage[$module][]['dest']        // The destination being used
	 *                                        ['description'] // Description of who is using it
	 *                                        ['edit_url']    // a url that could be invoked to edit the using entity
	 */
	public function getAllInUseDestinations($destination=true) {
		$dest_usage = $this->getModuleCheckDestinations($destination);
		if ($destination === true) {
			return $dest_usage;
		} else {
			/*
			$destlist[] = array(
				'dest' => $thisdest,
				'description' => 'Annoucement: '.$result['description'],
				'edit_url' => 'config.php?display=announcement&type='.$type.'&extdisplay='.urlencode($thisid),
			);
			*/
			$dest_matches = array();
			foreach (array_keys($dest_usage) as $mod) {
				foreach ($destination as $test_dest) {
					foreach ($dest_usage[$mod] as $dest_item) {
						if ($dest_item['dest'] == $test_dest) {
							$dest_matches[$mod][] = $dest_item;
						}
					}
				}
			}
			return $dest_matches;
		}
	}

	/**
	 * Get Popovers by Module
	 * @method getPopoversByModule
	 * @param  string              $rawname The module rawname
	 * @return array                       Array of modules
	 */
	public function getPopoversByModule($rawname) {
		if(! isset($this->FreePBX->Modules->getInfo($rawname)[$rawname]['popovers'])) {
			return false;
		}
		$popoverInfo = $this->FreePBX->Modules->getInfo($rawname)[$rawname]['popovers'];
		$protos = $this->getModuleDestinationPopovers($rawname);
		if (!empty($protos)) {
			$final = [];
			foreach($protos as $id => $info) {
				$final[$id] = [
					"name" => $info,
					"args" => []
				];
				if(!empty($popoverInfo[$id])) {
					$final[$id]['args'] = $popoverInfo[$id];
				}
			}
			return $final;
		} else {
			$destinations = $this->getDestinationsByModule($rawname);
			$module = $this->FreePBX->Modules->getInfo($rawname)[$rawname];
			//setting back the popover new value  to array
			if (isset($popoverInfo) && is_array($popoverInfo)) {
				$popupnew = $popoverInfo;
			} else {
				$popupnew = [];
			}
			if(empty($destinations)) {
				// We have popovers in XML, there were no destinations, and no mod_destination_popovers()
				// funciton so generate the Add a new selection.
				//
				return [
					$rawname => [
						"name" => $module['name'],
						"args" => $popupnew
					]
				];
			} else {
				foreach($destinations as $dest) {
					list($cat, $ds_id) = $this->getCategoryAndId($dest, $module);
				}
				return [
					$ds_id => [
						"name" => $cat,
						"args" => $popupnew
					]
				];
			}
		}
	}

	/**
	 * Get all destinations from all modules
	 * @method getAllDestinations
	 * @param  string            $index the destination set number (used when drawing multiple destination sets in a single form ie: digital receptionist)
	 * @return array             Array of destinations
	 */
	public function getAllDestinations($index = '') {
		$modules = $this->FreePBX->Modules->getActiveModules();
		$final = [];
		foreach($modules as $rawname => $data) {
			$out = $this->getDestinationsByModule($rawname, $index);
			if(!is_array($out)) {
				continue;
			}
			$final = array_merge($final,$out);
		}
		return $final;
	}

	/**
	 * Get All Destinations by Module Name
	 * @method getDestinationsByModule
	 * @param  string                  $rawname The module rawname
	 * @param  string                  $index   Goto Index
	 * @return array                           Array of destinations
	 */
	public function getDestinationsByModule($rawname, $index = '') {
		if(!$this->FreePBX->Modules->checkStatus($rawname)) {
			return false;
		}
		try {
			$destArray = $this->getModuleDestinations($rawname, $index);
			$destinations = [];
			if(!empty($destArray)) {
				foreach($destArray as $dest) {
					$module = $this->FreePBX->Modules->getInfo($rawname)[$rawname];
					list($cat, $id) = $this->getCategoryAndId($dest, $module);
					$destinations[$dest['destination']] = $dest;
					$destinations[$dest['destination']]['module'] = $rawname;
					$destinations[$dest['destination']]['name'] = $cat;

					// if the edit_url is provided already, there is no need to
					// lookup the destination info, which is costly to execute
					if (empty($dest['edit_url'])) {
						$info = $this->getDestinationInfoByModule($dest['destination'],$rawname);
						$destinations[$dest['destination']]['edit_url'] = !empty($info['edit_url']) ? $info['edit_url'] : '';
					}

				}
			}
			return $destinations;
		} catch(\Exception $e) {
			dbug($e->getMessage());
			dbug($e->getTraceAsString());
			return []; //there was an error
		}
	}

	/**
	 * Get information about a destination from its module
	 * @method getDestinationInfoByModule
	 * @param  string                     $destination The destination context,ext,pri
	 * @param  string                     $rawname     The module rawname to query
	 * @return array                                  Destination Information
	 */
	public function getDestinationInfoByModule($destination,$rawname) {
		if(!$this->FreePBX->Modules->checkStatus($rawname)) {
			return false;
		}
		$info = $this->getModuleDestinationInfo($rawname,$destination);
		if(empty($info)) {
			return false;
		}
		$info['edit_url'] = !empty($info['edit_url']) ? $info['edit_url'] : '';
		return $info;
	}

	/**
	 * Check if a specific destination is being used, or get a list of all destinations that are being used
	 *
	 * This is called to generate a label and tooltip which summarized the usage of this
	 * destination and a tooltip listing all the places that use it
	 *
	 * @method destinationUsageArray
	 * @param  mixed                $dest        an array of destinations to check against
	 * @return array                             array with a message and tooltip to display usage of this destination
	 */
	public function destinationUsageArray($dest) {
		if (!is_array($dest)) {
			$dest = array($dest);
		}
		$usage_list = $this->getAllInUseDestinations($dest);
		if (!empty($usage_list)) {
			$usage_count = 0;
			$str = null;
			foreach ($usage_list as $mod_list) {
				foreach ($mod_list as $details) {
					$usage_count++;
					$str .= $details['description']."<br />";
				}
			}
			$object = $usage_count > 1 ? _("Objects"):_("Object");
			return array(
				'text' => '&nbsp;'.sprintf(dgettext('amp',"Used as Destination by %s %s"),$usage_count, dgettext('amp',$object)),
				'tooltip' => $str,
			);
		} else {
			return array();
		}
	}

	/**
	 * Check if a specific destination is being used, or get a list of all destinations that are being used
	 *
	 * has each module replace their destination information with another one, used if you are
	 * assigning a new number to something such as a conference room that may be used as a destination
	 *
	 * @method changeDestination
	 * @param  string            $old_dest    the old destination that is being changed
	 * @param  string            $new_dest    the new destination that is replacing the old
	 * @return integer                         returns the number of records that were updated
	 */
	public function changeDestination($old_dest, $new_dest) {

		$old_dest = $this->FreePBX->Database->escapeSimple($old_dest);
		$new_dest = $this->FreePBX->Database->escapeSimple($new_dest);

		return $this->changeModuleDestination($old_dest, $new_dest);
	}

	/**
	 * Determines which module a list of destinations belongs to, and if the destination object exists
	 *
	 * Mainly used by framework_list_problem_destinations. This function will find the module
	 * that a destination belongs to and determine if the object still exits. This allow it to
	 * either obtain the identify, identify it as an object that has been deleted, or identify
	 * it as an unknown destination, usually a custom destination.
	 *
	 * @method identifyDestinations
	 * @param  mixed               $dest        an array of destinations to check against
	 * @return array                            an array structure with informaiton about the destinations (see code)
	 */
	public function identifyDestinations($dest) {
		$dest_results = array();

		$dest_usage = array();
		$dest_matches = array();

		$module_hash =  $this->FreePBX->Modules->getActiveModules();

		if (!is_array($dest)) {
			$dest = array($dest);
		}

		$data = $this->FreePBX->Hooks->processHooks($dest);
		foreach($data as $mod => $return_modulo) {
			if (! empty($return_modulo)) {
				foreach($return_modulo as $target => $check_module) {
					if (isset($this->dest_cache[$target])) {
						$dest_results[$target] = $this->dest_cache[$target];
					} else {
						if(!empty($check_module)) {
							$this->dest_cache[$target] = array(strtolower($mod) => $check_module);
							$dest_results[$target] = $this->dest_cache[$target];
						}
					}
				}
			}
		}

		$this->FreePBX->Modules->loadAllFunctionsInc();
		foreach ($dest as $target) {
			if (isset($this->dest_cache[$target])) {
				$dest_results[$target] = $this->dest_cache[$target];
			} else {
				$found_owner = false;
				foreach(array_keys($module_hash) as $mod) {
					$function = $mod."_getdestinfo";
					if (function_exists($function)) {
						modgettext::push_textdomain($mod);
						$check_module = $function($target);
						modgettext::pop_textdomain();
						if(!empty($check_module)) {
							$found_owner = true;
							$this->dest_cache[$target] = array($mod => $check_module);
							$dest_results[$target] = $this->dest_cache[$target];
							break;
						}
					}
				}
				if (! $found_owner) {
					//echo "Not Found: $target\n";
					$this->dest_cache[$target] = false;
					$dest_results[$target] = $this->dest_cache[$target];
				}
			}
		}
		return $dest_results;
	}

	public function getDestination($destination) {
		$info = $this->identifyDestinations($destination);
		if(isset($info[$destination])) {
			$module = key($info[$destination]);
			$info[$destination][$module]['module'] = $module;
			return $info[$destination][$module];
		}
	}

	/**
	 * Create a comprehensive list of all destinations that are problematic
	 *
	 * This function will scan the entire system and identify destinations
	 * that are problematic. Either empty, orphaned or an unknow custom
	 * destinations. An orphaned destination is one that should belong
	 * to a module but the object it would have pointed to does not exist
	 * because it was probably deleted.
	 *
	 * @method listProblemDestinations
	 * @param  boolean                 $ignore_custom set to true if custom (unknown) destinations should be reported
	 * @return array                                 an array of the destinations that are empty, orphaned or custom
	 */
	public function listProblemDestinations($ignore_custom=false) {
		$module_hash =  $this->FreePBX->Modules->getActiveModules();

		$my_dest_arr = array();
		$problem_dests = array();

		$all_dests = $this->getAllInUseDestinations(true);

		foreach ($all_dests as $dests) {
			foreach ($dests as $adest) {
				if (!empty($adest['dest'])) {
					$my_dest_arr[] = $adest['dest'];
				}
			}
		}
		$my_dest_arr = array_unique($my_dest_arr);

		$identities = $this->identifyDestinations($my_dest_arr);

		foreach ($all_dests as $dests) {
			foreach ($dests as $adest) {
				if (empty($adest['dest']) && empty($adest['allow_empty'])) {
					$problem_dests[] = array(
						'status' => 'EMPTY',
						'dest' => $adest['dest'],
						'description' => $adest['description'],
						'edit_url' => $adest['edit_url'],
					);
				} else if (!empty($adest['dest']) && $identities[$adest['dest']] === false){
					if ($ignore_custom) {
						continue;
					}
					$problem_dests[] = array(
						'status' => 'CUSTOM',
						'dest' => $adest['dest'],
						'description' => $adest['description'],
						'edit_url' => $adest['edit_url'],
					);
				} else if (!empty($adest['dest']) && is_array($identities[$adest['dest']])){
					foreach ($identities[$adest['dest']] as $details) {
						if (empty($details)) {
							$problem_dests[] = array(
								'status' => 'ORPHAN',
								'dest' => $adest['dest'],
								'description' => $adest['description'],
								'edit_url' => $adest['edit_url'],
							);
						}
						break; // there is only one set per array
					}
				} else if(empty($adest['dest']) && $adest['allow_empty']){
					continue;
				} else {
					echo "ERROR?\n";
					var_dump($adest);
				}
			}
		}
		return $problem_dests;
	}

	//$rawname.'_destinations'
	public function getModuleDestinations($module, $index = '') {
		$info = $this->FreePBX->Hooks->processHooksByModule($module, $index);
		if(!empty($info)) {
			return $info;
		}

		$this->FreePBX->Modules->loadFunctionsInc($module);
		$funct = strtolower($module.'_destinations');
		if(function_exists($funct)) {
			return $funct($index);
		}
		return false;
	}

	//$rawname.'_destination_popovers'
	public function getModuleDestinationPopovers($module) {
		$info = $this->FreePBX->Hooks->processHooksByModule($module);
		if(!empty($info)) {
			return $info;
		}

		$this->FreePBX->Modules->loadFunctionsInc($module);
		$funct = strtolower($module.'_destination_popovers');
		if(function_exists($funct)) {
			return $funct();
		}
		return false;
	}

	//$rawname."_check_destinations";
	public function getModuleCheckDestinations($destination) {
		$dest_usage = [];

		$data = $this->FreePBX->Hooks->processHooks($destination);
		foreach($data as $rawname => $module_usage) {
			if (!empty($module_usage)) {
				$dest_usage[$rawname] = $module_usage;
			}
		}

		$module_hash =  $this->FreePBX->Modules->getActiveModules();
		$this->FreePBX->Modules->loadAllFunctionsInc();
		foreach(array_keys($module_hash) as $rawname) {
			$function = $rawname."_check_destinations";
			if (function_exists($function)) {
				modgettext::push_textdomain($rawname);
				$module_usage = $function($destination);
				if (!empty($module_usage)) {
					$dest_usage[$rawname] = $module_usage;
				}
				modgettext::pop_textdomain();
			}
		}
		return $dest_usage;
	}

	//$rawname.'_getdestinfo'
	public function getModuleDestinationInfo($module,$destination) {
		$info = $this->FreePBX->Hooks->processHooksByModule($module,$destination);
		if(!empty($info)) {
			return $info;
		}

		$this->FreePBX->Modules->loadFunctionsInc($module);
		$funct2 = strtolower($module.'_getdestinfo');
		if (!function_exists($funct2)) {
			return false;
		}
		modgettext::push_textdomain($module);
		$info = $funct2($destination);
		modgettext::pop_textdomain();
		return $info;
	}

	//$rawname."_change_destination";
	public function changeModuleDestination($old_dest, $new_dest) {
		$total_updated = 0;

		$data = $this->FreePBX->Hooks->processHooks($old_dest, $new_dest);
		$total_updated = count($data);

		$module_hash =  $this->FreePBX->Modules->getActiveModules();

		$mods = array_keys($module_hash);
		unset($mods[array_search('framework',$mods)]);

		$this->FreePBX->Modules->loadAllFunctionsInc();
		foreach($mods as $mod) {
			$function = $mod."_change_destination";
			if (function_exists($function)) {
				$total_updated += $function($old_dest, $new_dest);
			}
		}

		return $total_updated;
	}

	/**
	 * Get Category name and ID from $destination,$module
	 * @method getCategoryAndId
	 * @param  array           $destination Destination information
	 * @param  array           $module      Module Information
	 * @return array                        Finalized output
	 */
	private function getCategoryAndId($destination, $module) {
		$cat=(isset($destination['category'])?$destination['category']:$module['displayname']);
		$cat = str_replace(array("|"),"",$cat);
		$cat = str_replace("&",_("and"),$cat);
		$id = (isset($destination['id']) ? $destination['id'] : $module['rawname']);
		return [
			$cat,
			$id
		];
	}
}
