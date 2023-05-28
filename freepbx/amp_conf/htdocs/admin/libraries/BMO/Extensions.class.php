<?php
namespace FreePBX;
use modgettext;
use fwmsg;
class Extensions {
	private $extmap = [];

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Need to be instantiated with a FreePBX Object");
		}

		$this->FreePBX = $freepbx;
	}

	/**
	 * Check if a specific extension is being used, or get a list of all
	 * extensions that are being used
	 *
	 * Upon passing in an array of extension numbers, this api will query all
	 * modules to determine if any are using those extension numbers. If so,
	 * it will return an array with the usage information as described below,
	 * otherwise an empty array. If passed boolean true, it will return an array
	 * of the same format with all extensions on the system that are being used.
	 *
	 * @method checkUsage
	 * @param  mixed      $exten            an array of extension numbers to
	 * check against, or if boolean true then return list of all extensions
	 * @param  array      $report_conflicts [description]
	 * @return array                        returns an empty array if exten not
	 * in use, or any array with usage info, or of all usage if exten is boolean true
	 *
	 * @example $exten_usage[$module][$exten]['description'] // description of the extension
	 *                                               ['edit_url']    // a url that could be invoked to edit extension
	 *                                               ['status']      // Status: INUSE, RESERVED, RESTRICTED
	 */
	public function checkUsage($exten=true, $report_conflicts=true) {
		$exten_usage = array();

		$module_hash = $this->FreePBX->Modules->getActiveModules();

		if (!is_array($exten) && $exten !== true) {
			$exten = array($exten);
		}

		foreach(array_keys($module_hash) as $mod) {
			$function = $mod."_check_extensions";
			if (function_exists($function)) {
				modgettext::push_textdomain($mod);
				$module_usage = $function($exten);
				if (!empty($module_usage)) {
					$exten_usage[$mod] = $module_usage;
				}
				modgettext::pop_textdomain();
			}
		}
		if ($exten === true) {
			return $exten_usage;
		} else {
	    $exten_matches = array();
			foreach (array_keys($exten_usage) as $mod) {
				foreach ($exten as $test_exten) {
					if (isset($exten_usage[$mod][$test_exten])) {
						$exten_matches[$mod][$test_exten] = $exten_usage[$mod][$test_exten];
					}
				}
			}
		}
		if (!empty($exten_matches) && $report_conflicts) {
			fwmsg::set_error(_("Extension Numbering Duplicate Conflict Detected"));
		}
		return $exten_matches;
	}

	/**
	 * Returns a hash of all extensions used on the system
	 *
	 * returns a full extension map where the index is the extension number and the
	 * value is what extension is using it. If there are duplicates defined, it will
	 * only show one of the extensions as duplicates is an unacceptable error condition
	 *
	 * @method getExtmap
	 * @param  boolean      $force  Force extension checking
	 * @return array                returns a hash of all extensions on system as array
	 */
	public function getExtmap($force = false) {
		// If aggresive mode, we get it each time
		if (!$this->FreePBX->Config->get('AGGRESSIVE_DUPLICATE_CHECK') && !$force) {
			$sth = $this->FreePBX->Database->query("SELECT `data` FROM `module_xml` WHERE `id` = 'extmap_serialized'", \PDO::FETCH_COLUMN , 0);
			$extmap_serialized = $sth->fetch();
			// Now make sure there was something there
			if ($extmap_serialized) {
				$this->extmap = unserialize($extmap_serialized);
			}
		}

		// At this point in aggresive mode we haven't gotten it, if not aggressive but
		// not found in the DB then we still don't have it so try again.
		if (!empty($this->extmap)) {
			return $this->extmap;
		} else {
			$this->extmap = []; //reset
			$full_list = $this->checkUsage(true);
			foreach ($full_list as $module => $entries) {
				foreach ($entries as $exten => $stuff) {
					$this->extmap[$exten] = $stuff['description'];
				}
			}
			return $this->extmap;
		}
	}

	/**
	 * Creates the extmap and puts it into the db
	 *
	 * this calculates the extension map and stores it into the database, primarily
	 * used by retrieve_conf
	 *
	 * @method setExtmap
	 */
	public function setExtmap() {
		$extmap = $this->getExtmap(true);
		$sth = $this->FreePBX->Database->prepare("REPLACE INTO `module_xml` (`id`, `time`, `data`) VALUES ('extmap_serialized', ?,?)");
		return $sth->execute(array(
			time(),
			serialize($extmap)
		));
	}

	/**
	 * Create a comprehensive list of all extensions conflicts
	 *
	 * This returns an array structure with information about all
	 * extension numbers that are in conflict. This means the same number
	 * is being used by 2 or more modules and the results will be ambiguous
	 * which one will be ignored when dialed. See the code for the
	 * structure of the retured array.
	 *
	 * @method listExtensionConflicts
	 * @param  boolean  $module_hash   a hash of module names to search for callbacks
	 * @return array                   an array of the destinations that are empty, orphaned or custom
	 */
	public function listExtensionConflicts() {
		$module_hash = $this->FreePBX->Modules->getActiveModules();

		$exten_list = $this->checkUsage(true);

		/** Bookkeeping hashes
	 	*  full_hash[]     will contain the first extension encountered
	 	*  conflict_hash[] will contain any subsequent extensions if conflicts
	 	*
	 	*  If there are conflicts, the full set is what is in conflict_hash + the
	 	*  first extension encoutnered in full_hash[]
	 	*/
		$full_hash = array();
		$conflict_hash = array();

		foreach ($exten_list as $mod => $mod_extens) {
			foreach ($mod_extens as $exten => $details) {
				if (!isset($full_hash[$exten])) {
					$full_hash[$exten] = $details;
				} else {
					$conflict_hash[] = array($exten => $details);
				}
			}
		}

		// extract conflicting remaining extension from full_hash but needs to be unique
		//
		if (!empty($conflict_hash)) {
			$other_conflicts = array();
			foreach ($conflict_hash as $item)  {
				foreach (array_keys($item) as $exten) {
					$other_conflicts[$exten] = $full_hash[$exten];
				}
			}
			foreach ($other_conflicts as $exten => $details) {
				$conflict_hash[] = array($exten => $details);
			}
			usort($conflict_hash, function($a, $b) {
				$a_key = array_keys($a);
				$a_key = $a_key[0];
				$b_key = array_keys($b);
				$b_key = $b_key[0];
				if ($a_key == $b_key) {
					return 0;
				} else {
					return ($a_key < $b_key) ? -1 : 1;
				}
			});
			return $conflict_hash;
		}
	}
}
