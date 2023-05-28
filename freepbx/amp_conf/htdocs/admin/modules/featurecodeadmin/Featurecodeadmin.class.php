<?PHP
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright (C) 2014 Schmooze Com Inc.
namespace FreePBX\modules;
class Featurecodeadmin implements \BMO {

	const ASTERISK_SECTION = 'ext-featurecodes';

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Not given a FreePBX Object");
		}
		global $freepbx_conf;

		$this->FreePBX = $freepbx;
		$this->freepbx_conf = &$freepbx_conf;
	}

	public function install() {}
	public function uninstall() {}
	public function backup() {}
	public function restore($backup) {}

	public function doConfigPageInit($page) {
		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] :  '';

		//if submitting form, update database
		switch ($action) {
			case "save":
				if(!empty($_POST['fc'])) {
					$this->update($_POST['fc']);
				}
			break;
		}
	}

	public function showPage($page, $params = array())
	{
		$data = array(
			"featurecodeadmin"	=> $this,
			'request'			=> $_REQUEST,
			'page' 				=> $page,
		);
		$data = array_merge($data, $params);
		switch ($page) 
		{
			case 'main':
				$data_return = load_view(__DIR__."/views/page.main.php", $data);
			break;
			
			case "view.main.toolbar":
				$data_return = load_view(__DIR__."/views/view.main.toolbar.php", $data);
			break;

			case "view.main.list":
				$data_extra = array(
					'conflict'					=> $this->getConflictInfo(),
					'modules' 					=> $this->getAllModulesInfo(),
					'moduleCustomFeaturecodes' 	=> $this->hookModuleCustomFeaturecodesview(),
				);
				$data = array_merge($data, $data_extra);
				$data_return = load_view(__DIR__."/views/view.main.list.php", $data);
			break;

			default:
				$data_return = sprintf(_("Page Not Found (%s)!!!!"), $page);
		}
		return $data_return;
	}

	public function getActionBar($request) {
		$buttons = array();
		switch($request['display']) {
			case 'featurecodeadmin':
				$buttons = array(
					'reset' => array(
						'name' => 'reset',
						'id' => 'reset',
						'value' => _('Reset')
					),
					'submit' => array(
						'name' => 'submit',
						'id' => 'submit',
						'value' => _('Submit')
					)
				);
			break;
		}
		return $buttons;
	}

	public function ajaxRequest($req, &$setting)
	{
		// ** Allow remote consultation with Postman **
		// ********************************************
		// $setting['authenticate'] = false;
		// $setting['allowremote'] = true;
		// return true;
		// ********************************************
		switch($req) {
			// case 'fc_list':
				// return true;
			default:
				return false;
		}
	}

	public function ajaxHandler() {
		$command = isset($_REQUEST['command']) ? trim($_REQUEST['command']) : '';
		$data_return = array("status" => false, "message" => sprintf(_("Command [%s] not valid!"), $command));

		switch ($command)
		{
			case 'fc_list':
				$data_return = $this->getAllFeaturesDetailed();
				break;
		}
		return $data_return;
	}



	public function update($codes=array()) {
		if(!empty($codes)) {
			foreach($codes as $module => $features) {
				foreach($features as $name => $data) {
					$fcc = new \featurecode($module, $name);
					if(!empty($data['enable'])) {
						$fcc->setEnabled(true);
					} else {
						$fcc->setEnabled(false);
					}

					if(empty($data['customize']) || ($data['code'] == $fcc->getDefault())) {
						$fcc->setCode('');
					} else {
						$fcc->setCode($data['code']);
					}
					$fcc->update();
				}
			}
			needreload();
		}
	}

	public function search($query, &$results) {
		$fcs = $this->printExtensions();
		foreach ($fcs['items'] as $fc) {
			$results[] = array(
				"text" => sprintf("%s %s", $fc[0], $fc[1]),
				"type" => "get",
				"dest" => "?display=featurecodeadmin"
			);
		}
	}
	


	public function getAllModulesInfo()
	{
		$currentmodule = _("(none)");
		$modules = array();

		foreach($this->getAllFeaturesDetailed() as $item)
		{
			$moduledesc = isset($item['moduledescription']) ? \modgettext::_($item['moduledescription'], $item['modulename']) : null;
			// just in case the translator put the translation in featurcodes module:
			if (($moduledesc !== null) && !empty($moduledesc) && ($moduledesc == $item['moduledescription'])) {
				$moduledesc = _($moduledesc);
			}
			$featuredesc = !empty($item['featuredescription']) ? \modgettext::_($item['featuredescription'], $item['modulename']) : "";
			// just in case the translator put the translation in featurcodes module:
			if (!empty($item['featuredescription']) && ($featuredesc == $item['featuredescription'])) {
				$featuredesc = _($featuredesc);
			}
			$help = !empty($item['featurehelptext']) ? \modgettext::_($item['featurehelptext'], $item['modulename']) : "";
			if (!empty($item['featurehelptext']) && ($help == $item['featurehelptext'])) {
				$help = _($help);
			}

			//TODO: What did we do here before when the module was disabled?
			//bueller, bueller, bueller
			$moduleena = ($item['moduleenabled'] == 1 ? true : false);
				
			$default = (isset($item['defaultcode']) ? $item['defaultcode'] : '');
			$custom = (isset($item['customcode']) ? $item['customcode'] : '');
			$code = ($custom != '') ? $custom : $default;
				
			$thismodule = $item['modulename'];
			if($thismodule != $currentmodule){
				$currentmodule = $thismodule;
				$title = ucfirst($thismodule);
				$modules[$thismodule]['title'] = $title;
			}
			$id = sprintf("%s_%s", $item['modulename'], $item['featurename']);

			$item_data = array(
				'title' 	=> $featuredesc,
				'id' 		=> $id,
				'module' 	=> $item['modulename'],
				'feature' 	=> $item['featurename'],
				'default' 	=> $default,
				'iscustom' 	=> ($custom != ''),
				'code' 		=> $code,
				'isenabled' => ($item['featureenabled'] == 1 ? true : false),
				'custom' 	=> $custom,
				'help' 		=> $help,
				'depend' 	=> $item['depend'],
			);

			if (empty($item_data['depend']))
			{
				$modules[$thismodule]['items'][$item_data['feature']] = $item_data;
			}
			else
			{
				$modules[$thismodule]['items'][$item_data['depend']]['subitems'][$item_data['feature']] = $item_data;
			}
		}

		$conf_mode = $this->freepbx_conf->get_conf_setting('AMPEXTENSIONS');
		if($conf_mode == 'extensions')
		{
			$featurecode_settings = $this->freepbx_conf->get_conf_setting('EXPOSE_ALL_FEATURE_CODES');
			if(!$featurecode_settings)
			{
				if(isset($modules['core']))
				{
					$new_items = array();
					if (! empty($modules['core']['items']))
					{
						foreach($modules['core']['items'] as $tmp_item)
						{
							if($tmp_item['id'] == 'core_userlogoff' || $tmp_item['id'] == 'core_userlogon')
							{
								continue;
							}
							$new_items[] = $tmp_item;
						}
					}
					$modules['core']['items'] = $new_items;
				}
			}
		}
		return $modules;
	}

	public function getConflictInfo()
	{
		$exten_conflict_arr = array();
		$conflict_url 		= array();
		$exten_arr 			= array();
		$conflicterror 		= '';

		foreach ($this->getAllFeaturesDetailed() as $result)
		{
			/* if the feature code starts with "In-Call Asterisk" then it is not conflicting with normal feature codes. This would be featuremap and future
			* application map type codes. This is a real kludge and instead there should be a category associated with these codes when the feature code
			* is created. However, the logic would be the same, thus my willingness to put in such a kludge for now. When the schema changes to add this
			* then this can be updated to reflect that
			*/
			if (($result['featureenabled'] == 1) && ($result['moduleenabled'] == 1) && substr($result['featuredescription'],0,16) != 'In-Call Asterisk')
			{
				$exten_arr[] = ($result['customcode'] != '')?$result['customcode']:$result['defaultcode'];
			}
		}

		$usage_arr = \framework_check_extension_usage($exten_arr);
		unset($usage_arr['featurecodeadmin']);
		if (!empty($usage_arr))
		{
			$conflict_url = \framework_display_extension_usage_alert($usage_arr,false,false);
		}

		if (!empty($conflict_url))
		{
			// Remove code HTML, JS the class. Generate code HTML, JS in view file.
			$conflicterror = $conflict_url;

			// Create hash of conflicting extensions
			foreach ($usage_arr as $details)
			{
				foreach (array_keys($details) as $exten_conflict)
				{
					$exten_conflict_arr[$exten_conflict] = true;
				}
			}

			// Now check for conflicts within featurecodes page
			$unique_exten_arr = array_unique($exten_arr);
			$feature_conflict_arr = array_diff_assoc($exten_arr, $unique_exten_arr);
			foreach ($feature_conflict_arr as $value)
			{
				$exten_conflict_arr[$value] = true;
			}
		}

		return array(
			'conflicterror'		 => $conflicterror,
			'exten_conflict_arr' => $exten_conflict_arr,
		);
	}

	/**
	 * Method hookTabs
	 *
	 * @return void
	 */
	public function hookModuleCustomFeaturecodesview(){
		$module_hook = \moduleHook::create();
		$mods = $this->FreePBX->Hooks->processHooks();
		foreach($mods as $module){
			return $module;
		}
		return array();
	}

	/**
	 * Hook for the module printExtensions
	 * 
	 */
	public function printExtensions(){
		$ret = array();
    	foreach ($this->getAllFeaturesDetailed() as $fc)
		{
    		if($fc['featureenabled'] && $fc['moduleenabled'])
			{
    			$code = $fc['customcode'] ? $fc['customcode'] : $fc['defaultcode'];
				$featuredesc = !empty($fc['featuredescription']) ? \modgettext::_($fc['featuredescription'], $fc['modulename']) : "";

				// just in case the translator put the translation in featurcodes module:
				if (!empty($fc['featuredescription']) && ($featuredesc == $fc['featuredescription'])) {
					$featuredesc = _($featuredesc);
				}
    			$ret[] = array($featuredesc, $code);
    		}
    	}
		return array(
			'title'    => _("Feature Codes"),
			'textdesc' => _('Description'),
			'numdesc'  => _("Code"),
			'items'    => $ret,
		);
	}

	/**
	 * Dialplan hooks
	 * 
	 */
	public function myDialplanHooks() {
		return true;
	}
	public function doDialplanHook(&$ext, $engine, $priority) {
		if ($engine != "asterisk") { return; }

		$section = self::ASTERISK_SECTION;
		foreach ($this->getAllFeaturesDetailed() as $result) {
			// Ignore disabled codes, and modules, and ones not providing destinations
			if ($result['featureenabled'] == 1 && $result['moduleenabled'] == 1 && $result['providedest'] == 1)
			{
				$thisexten = ($result['customcode'] != '') ? $result['customcode'] : $result['defaultcode'];
				$ext->add($section, $result['defaultcode'], '', new \ext_goto('1', $thisexten, 'from-internal'));
			}
		}
	}

	/**
	 * Destinations hooks 
	 * 
	 */
	public function getDest($exten) {
		return sprintf('%s,%s,1', self::ASTERISK_SECTION, $exten);
	}

	public function destinations()
	{
		$extens = array();
		$featurecodes = $this->getAllFeaturesDetailed();
		if (! empty($featurecodes))
		{
		  	foreach ($featurecodes as $result)
			{
				// Ignore disabled codes, and modules, and ones not providing destinations
				//
				if ($result['featureenabled'] == 1 && $result['moduleenabled'] == 1 && $result['providedest'] == 1)
				{
			  		$modulename = $result['modulename'];
		  			//FREEPBX-21227 Remove Feature Code Admin -> Contact Mgr Speeddial as destination
		  			if($modulename == 'contactmanager') {
			  			continue;
		  			}
					$description = \modgettext::_($result['featuredescription'], $modulename);
					// Just in case the translation was not found in either the module or amp, we will try to see
					// if they put it in the featurecode module i18n
			  		if ($description == $result['featuredescription']) {
				  		$description = _($description);
			  		}
			  		$thisexten = ($result['customcode'] != '') ? $result['customcode'] : $result['defaultcode'];
					$extens[] = array(
						'destination' => $this->getDest($result['defaultcode']),
						'description' => sprintf("%s <%s>", $description, $thisexten)
					);
				}
			}
		}

		if (! empty($extens)) { return $extens; }
		else 				  { return null; }
	}

	public function destinations_check($dest=true)
	{
		$destlist = array();
		if (is_array($dest) && empty($dest)) { return $destlist; }

		$fcs = $this->destinations();
		$fcs = is_array($fcs) ? $fcs : array();
	
		$results = array();
		if ($dest === true)
		{
			$results = $fcs;
		}
		else
		{
			foreach ($fcs as $fc) {
				if (in_array($fc['destination'], $dest)) {
					$results[] = $fc;
				}
			}
		}
	
		foreach ($results as $result) {
			$destlist[] = array(
				'dest' => $result['destination'],
				'description' => $result['description'],
				'edit_url' => 'config.php?display=featurecodeadmin',
			);
		}
		return $destlist;
	}

	public function destinations_getdestinfo($dest)
	{
		$srt_section = sprintf("%s,", self::ASTERISK_SECTION);
		if (substr(trim($dest),0, strlen($srt_section)) == $srt_section)
		{
			$dest = explode(',', $dest);
			$exten = $dest[1];
			foreach ($this->getAllFeaturesDetailed() as $fc)
			{
				if ($fc['defaultcode'] == $exten) {
					return array(
						'description' => $fc['featuredescription'],
						'edit_url' => 'config.php?display=featurecodeadmin',
					);
				}
			}
			return array();
		}
		return false;
	}

	function destinations_check_extensions($exten=true) {
		$extenlist = array();
		if (is_array($exten) && empty($exten)) {
			return $extenlist;
		}
		foreach ($this->getAllFeaturesDetailed() as $result) {
			$thisexten = ($result['customcode'] != '') ? $result['customcode'] : $result['defaultcode'];
	
			// Ignore disabled codes, and modules, and any exten not being requested unless all (true)
			//
			if (($result['featureenabled'] == 1) && ($result['moduleenabled'] == 1) && ($exten === true || in_array($thisexten, $exten))) {
				$extenlist[$thisexten] = array(
					'description' => sprintf(_("Featurecode: %s (%s:%s)"), $result['featurename'], $result['modulename'], $result['featuredescription']),
					'status' 	  => 'INUSE',
					'edit_url' 	  => 'config.php?type=setup&display=featurecodeadmin',
				);
			}
		}
		return $extenlist;
	}

	public function destinations_identif($dests)
	{
		if (! is_array($dests)) {
			$dests = array($dests);
		}
		$return_data = array();
		foreach ($dests as $target)
		{
			$info = $this->destinations_getdestinfo($target);
			if (!empty($info))
			{
				$return_data[$target] = $info;
			}
		}
		return $return_data;
	}

	/**
	 * Hook's functions global's.
	 * 
	 */
	public function getAllFeaturesDetailed($sort_module=true) {
		return \featurecodes_getAllFeaturesDetailed($sort_module);
	}
}