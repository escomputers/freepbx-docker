<?php
// This is a global to be used by gettabindex()
//
$_guielement_tabindex = 1;
$_guielement_formfields = 0;

class component {
	protected $compname; // Component name (e.g. users, devices, etc.)

	protected $guielems_top; // Array of guielements
	protected $guielems_middle; // Array of guielements
	protected $guielems_bottom; // Array of guielements

	protected $jsfuncs; // Array of JavaScript functions
	protected $guifuncs; // Array of gui functions
	protected $processfuncs; // Array of process functions

	protected $sorted_guielems;
	protected $sorted_jsfuncs;
	protected $sorted_guifuncs;
	protected $sorted_processfuncs;

	private $generated = false; //Did we already generate the output?
	private $redirecturl = null; //Do we need to do a reidrect after we finish processing

	protected $lists; // Array of lists

	protected $opts; //array of configurable options

	private $translations = array();

	public function __construct($compname) {
		$this->compname = $compname;

		$this->sorted_guielems = true;
		$this->sorted_jsfuncs = true;
		$this->sorted_guifuncs = true;
		$this->sorted_processfuncs = true;

		//set section to hidden if requested by user
		$user_hidden = isset($_COOKIE['guielToggle']) ? json_decode($_COOKIE['guielToggle']) : array();
		foreach($user_hidden as $k => $v) {
			list($page, $section) = explode('#', $k);
			if ($page == $compname) {
				$this->opts[$section]['guielToggle'] = $v ? true :false;
			}
		}

		$this->tabtranslations = array(
			"general" => _("General"),
			"voicemail" => _("Voicemail"),
			"advanced" => _("Advanced"),
			"endpoint" => _("Endpoint"),
			"pinsets" => _("Pin Sets"),
			"other" => _("Other")
		);
	}

	public function setRedirectURL($url) {
		$this->redirecturl = $url;
	}

	public function addTabTranslation($category,$translation) {
		$this->tabtranslations[$category] = $translation;
	}

	/*
	 * Toggle open state
	 * true = open, false = closed
	 * wont over write a users settings
	 */
	public function sectionToggle($section, $state = false) {
		$section = preg_replace('/[^A-Za-z]/', '' ,$section);
		if (!isset($this->opts[$section]['guielToggle'])) {
			$this->opts[$section]['guielToggle'] = $state;
		}
	}

	/*
	 * add GUI element.
	 * $mode = In 'basic mode', we will not display 'advanced' mode requested settings. Default is 'basic' to compatible with current behaviour.
	 */
	public function addguielem($section, $guielem, $sortorder = 5, $placement = null, $category="other", $mode='basic') {
		$current_opmode = FreePBX::Config()->get('FPBXOPMODE');
		if ($current_opmode == 'basic') {
			if (($mode == 'advanced')) {
				// do not display 'advanced' mode section while we are on 'basic mode'
				return;
			}
		}

		if(!ctype_digit($sortorder) && is_string($sortorder)) {
			$category = $sortorder;
			$sortorder = 5;
		}
		if(!isset($this->tabtranslations[$category])) {
			$this->tabtranslations[$category] = $category;
		}
		$category = strtolower(trim($category));
		// Note that placement is only used in 'middle', eg, a named module
		if ( $sortorder < 0 || $sortorder > 9 ) {
			trigger_error('$sortorder must be between 0 and 9 in component->addguielem()');
			return;
		}

		switch ($section) {
			case '_top':
				$this->guielems_top[$sortorder][] = $guielem;
				break;
			case '_bottom':
				$this->guielems_bottom[$sortorder][] = $guielem;
				break;
			default:
				$this->guielems_middle[$category][$section][$sortorder][] = $guielem;
				if (!isset($this->guielems_middle[$category][$section]['placement'])) {
					if ($placement === null) {
						$this->guielems_middle[$category][$section]['placement'] = $sortorder;
					} else {
						$this->guielems_middle[$category][$section]['placement'] = $placement;
					}
				}
				break;
		}

		$this->sorted_guielems = false;
	}

	public function delguielem($section, $elemname, $category="other") {
		$category = strtolower(trim($category));
		switch ($section) {
			case '_top':
				foreach ($this->guielems_top as $index1 => $elements) {
					foreach ($elements as $index2 => $element) {
						if ($element->_elemname == $elemname) {
							unset($this->guielems_top[$index1][$index2]);
							return true;
						}
					}
				}
				break;
			case '_bottom':
				foreach ($this->guielems_bottom[$category] as $index1 => $elements) {
					foreach ($elements as $index2 => $element) {
						if ($element->_elemname == $elemname) {
							unset($this->guielems_bottom[$category][$index1][$index2]);
							return true;
						}
					}
				}
				break;
			default:
				if (isset($this->guielems_middle[$section])) {
					foreach ($this->guielems_middle[$section] as $index1 => $elements) {
						foreach ($elements as $index2 => $element) {
							if ($element->_elemname == $elemname) {
								unset($this->guielems_bottom[$index1][$index2]);
								return true;
							}
						}
					}
				}
		}
		return false;
	}

	public function addjsfunc($function, $jstext, $sortorder = 5) {
		if ( $sortorder < 0 || $sortorder > 9 ) {
			trigger_error('$sortorder must be between 0 and 9 in component->addjsfunc()');
			return;
		}

		$this->jsfuncs[$function][$sortorder][] = $jstext;

		$this->sorted_jsfuncs = false;
	}

	public function addguifunc($function, $sortorder = 5) {
		if ( $sortorder < 0 || $sortorder > 9 ) {
			trigger_error('$sortorder must be between 0 and 9 in component->addguifunc()');
			return;
		}
		if ( !function_exists($function) ) {
			trigger_error("$function does not exist");
			return;
		}

		$this->guifuncs[$sortorder][] = $function;

		$this->sorted_guifuncs = false;
	}

	public function addprocessfunc($function, $sortorder = 5) {
		if ( $sortorder < 0 || $sortorder > 9 ) {
			trigger_error('$sortorder must be between 0 and 9 in component->addprocessfunc()');
			return;
		}
		if ( !function_exists($function) ) {
			trigger_error("$function does not exist");
			return;
		}

		$this->processfuncs[$sortorder][] = $function;

		$this->sorted_processfuncs = false;
	}

	public function addoptlist($listname, $sort = true) {
		if ( (isset($listname) ? $listname : '') == '') {
			trigger_error('missing $listname in component->addoptlist()');
			return;
		} elseif (isset($this->lists[$listname]) && is_array($this->lists[$listname]) ) {
			trigger_error("list $listname already exists");
		}

		// does this list need sorting ?
		$this->lists[$listname]['sort'] = $sort;
		// nothing really, but an array will be here after addlistitem
		$this->lists[$listname]['array'] = array();
	}

	public function setoptlistopts($listname, $opt, $val) {
		$this->lists[$opt] = $val;
	}

	public function addoptlistitem($listname, $value, $text, $uselang = true) {
		// must add the list before using it
		if ( !isset($this->lists[$listname]) ) {
			$this->addoptlist($listname, false);
		}

		// add the item
		$this->lists[$listname]['array'][] = array('text' => $text, 'value' => $value);
	}

	public function getoptlist($listname) {
		if ( isset($this->lists[$listname]['array']) ) {
			// sort the array by text
			if ( $this->lists[$listname]['sort'] ) {
				asort($this->lists[$listname]['array']);
			}

			// and return it!
			return $this->lists[$listname]['array'];
		} else {
			trigger_error("'$listname' does not exist in component->getoptlist()");
			return null;
		}
	}

	public function addgeneralarray($arrayname) {
		if ( (isset($arrayname) ? $arrayname : '') == '') {
			trigger_error('missing $arrayname in component->addarray()');
			return;
		} elseif ( isset($this->lists[$arrayname]) && is_array($this->lists[$arrayname]) ) {
			trigger_error("array $arrayname already exists");
		}

		// nothing really, but an array will be here after addlistitem
		$this->lists[$arrayname] = array();
	}

	public function addgeneralarrayitem($arrayname, $arraykey, $item) {
		if ( !isset($this->lists[$arrayname]) ) {
			$this->addgeneralarray($arrayname);
		}

		$this->lists[$arrayname][$arraykey] = $item;
	}

	public function getgeneralarray($arrayname) {
		if ( isset($this->lists[$arrayname]) ) {
			return $this->lists[$arrayname];
		} else {
			trigger_error("'$arrayname' does not exist in component->getgeneralarray()");
			return null;
		}
	}

	public function getgeneralarrayitem($arrayname, $arraykey) {
		if ( isset($this->lists[$arrayname][$arraykey]) ) {
			return $this->lists[$arrayname][$arraykey];
		} else {
			trigger_error("'$arraykey' does not exist in array '$arrayname'");
			return null;
		}
	}

	public function sortguielems() {
		// sort top gui elements

		if (is_array($this->guielems_top)) {
			core_collator::ksort($this->guielems_top,core_collator::SORT_NATURAL);
		}

		// sort middle gui elements
		if (is_array($this->guielems_middle)) {
			$final = array();
			foreach(array_keys($this->guielems_middle) as $category) {
				foreach ( array_keys($this->guielems_middle[$category]) as $section ) {
					core_collator::ksort($this->guielems_middle[$category][$section],core_collator::SORT_NATURAL);
					for ($placement = 0; $placement < 10; $placement++) {
						if($this->guielems_middle[$category][$section]['placement'] == $placement) {
							$final[$category][$placement][$section] = $this->guielems_middle[$category][$section];
						}
					}
				}
				ksort($final[$category]);
			}
			$this->guielems_middle = $final;
			core_collator::ksort($this->guielems_middle,core_collator::SORT_NATURAL);
			uksort($this->guielems_middle, function($a,$b) {
				$a = strtolower($a);
				$b = strtolower($b);
				$categories = array(
					"general" => 1,
					"voicemail" => 2,
					"findmefollow" => 3,
					"advanced" => 4,
					"other" => 999
				);
				$aOrder = isset($categories[$a]) ? $categories[$a] : 5;
				$bOrder = isset($categories[$b]) ? $categories[$b] : 5;
				return ($aOrder < $bOrder) ? -1 : 1;
			});
		}

		// sort bottom gui elements
		if (is_array($this->guielems_bottom)) {
			core_collator::ksort($this->guielems_bottom,core_collator::SORT_NATURAL);
		}

		$this->sorted_guielems = true;
	}

	public function sortjsfuncts() {
		// sort js funcs
		if ( is_array($this->jsfuncs) ) {
			foreach ( array_keys($this->jsfuncs) as $function ) {
				ksort($this->jsfuncs[$function]);
			}
			ksort($this->jsfuncs);
		}

		$this->sorted_jsfuncs = true;
	}

	public function sortguifuncs() {
		// sort process functions
		if ( is_array($this->guifuncs) ) {
			ksort($this->guifuncs);
		}

		$this->sorted_guifuncs = true;
	}

	public function sortprocessfuncs() {
		// sort process functions
		if ( is_array($this->processfuncs) ) {
			ksort($this->processfuncs);
		}

		$this->sorted_processfuncs = true;
	}

	public function generateconfigpage($loadView=null, $variables = array()) {
		if(empty($loadView)) {
			$loadView = dirname(__DIR__) . "/views/currentcomponent.php";
		}
		if($this->generated) {
			return '';
		}
		$this->generated = true;

		$htmlout = '';
		$formname = "frm_$this->compname";
		$hasoutput = false;

		if ( !$this->sorted_guielems ) {
			$this->sortguielems();
		}

		$crossSections = array(
			"top",
			"middle",
			"bottom"
		);

		$html = array(
			"top" => array(),
			"middle" => array(),
			"bottom" => array()
		);
		$hiddens = array();
		foreach($crossSections as $pl) {
			$divide = "guielems_".$pl;
			if(!is_array($this->$divide)) {
				continue;
			}
			foreach($this->$divide as $category => $sections) {
				foreach($sections as $order => $sections) {
					if($pl == "middle") {
						foreach($sections as $section => $elements) {
							if(!isset($elements['placement'])) {
								continue;
							}
							$placement = $elements['placement'];
							unset($elements['placement']);
							$elements = array_values($elements);
							$final = array();
							foreach($elements as $el) {
								$final = array_merge($final,$el);
							}

							foreach($final as $elem) {
								$data = $elem->getRawArray();
								if($data['type'] == "hidden") {
									$hiddens[] = $data;
								} else {
									$html[$pl][$category][$section][] = $data;
									$validation = $elem->generatevalidation();
									if(!empty($validation)) {
										$this->addjsfunc('onsubmit()', $validation);
									}
								}
							}
						}
					} else {
						$html[$pl][] = $sections->getRawArray();
					}
				}
			}
		}

		$jsfuncs = array();
		if(!empty($this->jsfuncs)) {
			foreach($this->jsfuncs as $f => $data) {
				foreach($data as $scripts) {
					$jsfuncs[$f] = $scripts;
				}
			}
		}

		if(!empty($html['top']) || !empty($html['middle']) || !empty($html['bottom'])) {
			if(!empty($html['middle'])) {
				reset($html['middle']);
				$active = key($html['middle']);
				reset($html['middle']);
			}
			$action = isset($this->opts['form_action']) ? $this->opts['form_action'] : "";
			$display = !empty($_REQUEST['display']) ? $_REQUEST['display'] : rand(0,10);
			$showTabs = count($html['middle']) > 1;
			$vars = array("tabtranslations" => $this->tabtranslations, "showtabs" => $showTabs, "display" => $display, "active" => $active, "hiddens" => $hiddens, "action" => $action, "html" => $html, "jsfuncs" => $jsfuncs);
			$finalVars = !empty($variables) ? array_merge($variables, $vars) : $vars;
			return load_view($loadView, $finalVars);
		} else {
			return '';
		}

	}

	public function processconfigpage() {
		if ( !$this->sorted_processfuncs ) {
			$this->sortprocessfuncs();
		}

		if ( is_array($this->processfuncs) ) {
			foreach ( array_keys($this->processfuncs) as $sortorder ) {
				foreach ( $this->processfuncs[$sortorder] as $func ) {
					$func($this->compname);
				}
			}
		}
		if(!empty($this->redirecturl)) {
			@header('Location: '.$this->redirecturl);
			exit;
		}
	}

	public function buildconfigpage() {
		if ( !$this->sorted_guifuncs ) {
			$this->sortguifuncs();
		}

		$perf = FreePBX::Performance();

		if ( is_array($this->guifuncs) ) {
			foreach ( array_keys($this->guifuncs) as $sortorder ) {
				foreach ( $this->guifuncs[$sortorder] as $func ) {
					$modparts = explode("_",$func,2);
					$thismod = $modparts[0];

					modgettext::push_textdomain($thismod);
					$perf->Start("buildpager-$func");
					$func($this->compname);
					$perf->Stop("buildpager-$func");
					modgettext::pop_textdomain();
				}
			}
		}
	}

	public function isequal($compname, $type) {
		return $this->compname == $compname;
	}
}

class guielement {
	protected $_elemname;
	protected $_html;
	protected $_javascript;
	protected $_opts;

	public function __construct($elemname, $html = '', $javascript = '') {
		global $CC;
		// name that will be the id tag
		$this->_elemname = $elemname;

		// normally the $html will be the actual page output, obviously here in the base class it's meaningless
		// this does mean, of course, this constructor MUST be called before any child class constructor code
		// otherwise $html will be blanked out
		$this->_html = $html;
		$this->_javascript = $javascript;


		$this->_opts = & $CC->_opts;
	}

	public function get($key) {
		return property_exists($this, $key) ? $this->$key : null;
	}

	public function getRawArray() {
		return array(
			'name' => $this->_elemname,
			'html' => $this->_html,
			'type' => $this->type
		);
	}

	public function generatehtml() {
		return $this->_html;
	}

	public function generatevalidation() {
		return $this->_javascript;
	}
	public function gettabindex() {
		global $_guielement_tabindex;
		return $_guielement_tabindex;
	}
	public function settabindex($new_tab) {
		global $_guielement_tabindex;
		$_guielement_tabindex = $new_tab;
	}
	public function incrementfields() {
		global $_guielement_formfields;
		$_guielement_formfields++;
	}
	public function getformfields() {
		global $_guielement_formfields;
		return $_guielement_formfields;
	}
}

// Hidden field
// Odd ball this one as neither guiinput or guitext !
/**
 *
 * @param $table bool if this element is in a table or not, Default is true.
 */
class gui_hidden extends guielement {
	public function __construct($elemname, $currentvalue = '', $table=true) {
		if(is_array($elemname)) {
			extract($elemname);
		}
		// call parent class contructor
		parent::__construct($elemname, '', '');

		$this->_html = "<input type=\"hidden\" name=\"$this->_elemname\" id=\"$this->_elemname\" value=\"" . htmlentities($currentvalue) . "\">";
		$this->type = "hidden";

		// make it a new row
		if($table) {
			$this->_html = $this->_html;
		}
	}
}

/*
 ************************************************************
 ** guiinput is the base class of all form fields          **
 ************************************************************
 */

class guiinput extends guielement {
	protected $currentvalue = null;
	protected $prompttext = null;
	protected $helptext = null;
	protected $jsvalidation = null;
	protected $failvalidationmsg = null;
	protected $canbeempty = null;
	protected $type;

	protected $html_input = null;

	public function __construct($elemname, $currentvalue = '', $prompttext = '', $helptext = '', $jsvalidation = '', $failvalidationmsg = '', $canbeempty = true, $jsvalidationtest='') {

		// call parent class contructor
		parent::__construct($elemname, '', '');

		// current valid of the field
		$this->currentvalue = $currentvalue;
		// this will appear on the left column
		$this->prompttext = $prompttext;
		// tooltip over prompttext (optional)
		$this->helptext = $helptext;
		// JavaScript validation field on the element
		$this->jsvalidation = $jsvalidation;
		// JavaScript validation test
		$this->jsvalidationtest = $jsvalidationtest;
		// Msg to use if above validation fails (forced to use gettext language stuff)
		$this->failvalidationmsg = $failvalidationmsg;
		// Can this field be empty ?
		$this->canbeempty = $canbeempty;

		// this will be the html that makes up the input element
		$this->html_input = '';

		$this->type = "input";

		guielement::incrementfields();
	}

	public function getAllRawData() {
		return array(
			"currentvalue" => $this->currentvalue,
			"prompttext" => $this->prompttext,
			"helptext" => $this->helptext,
			"jsvalidation" => $this->jsvalidation,
			"failvalidationmsg" => $this->failvalidationmsg,
			"canbeempty" => $this->canbeempty,
			"html_input" => $this->html_input
		);
	}

	public function get($key) {
		return property_exists($this, $key) ? $this->$key : null;
	}

	public function generatevalidation() {
		$output = '';

		if ($this->jsvalidation != '' ) {
			if(!$this->jsvalidationtest){
				$thefld = "theForm." . $this->_elemname;
				$thefldvalue = $thefld . ".value";
			}else{
				$thefld="theForm." . $this->_elemname;
				$thefldvalue =$this->jsvalidationtest;
			}

			if ($this->canbeempty) {
				$output .= "\tdefaultEmptyOK = true;\n";
			} else {
				$output .= "\tdefaultEmptyOK = false;\n";
			}

			$output .= "\tif (" . str_replace("()", "(" . $thefldvalue . ")", $this->jsvalidation) . ") {\n";
			$output .= "\t\treturn warnInvalid(" . $thefld . ", \"" . $this->failvalidationmsg . "\");\n";
			$output .= "\t}\n";
		}

		return $output;
	}

	public function getRawArray() {
		return array(
			'helptext' => $this->helptext,
			'prompttext' => $this->prompttext,
			'html' => $this->html_input,
			'type' => $this->type,
			'name' => $this->_elemname
		);
	}

	public function generatehtml($section = '') {
		// this effectivly creates the template using the prompttext and html_input
		// we would expect the $html_input to be set by the child class

		$output = '';

		// start new row
		if ($section) {
			$mysec = preg_replace('/[^A-Za-z]/', '' ,$section);
			$output .= '<tr class="' . $section . '" '
				. ((isset($this->_opts[$mysec]['guielToggle']) && $this->_opts[$mysec]['guielToggle'] == false)
						? ' style="display:none" '
						: '')
				.' >' . "\n";
		} else {
			$output .= "\t<tr>\n";
		}


		// prompt in first column
		$output .= "\t\t<td>";
		if ($this->helptext != '') {
			$output .= fpbx_label($this->prompttext,$this->helptext);
		} else {
			$output .= $this->prompttext;
		}
		$output .= "</td>\n";

		// actual input in second row
		$output .= "\t\t<td>";
		$output .= $this->html_input;
		$output .= "</td>\n";

		// end this row
		$output .= "\t</tr>\n";

		return $output;
	}
}

// Textbox
class gui_textbox extends guiinput {

	public function __construct($elemname, $currentvalue = '', $prompttext = '', $helptext = '', $jsvalidation = '', $failvalidationmsg = '', $canbeempty = true, $maxchars = 0, $disable=false, $inputgroup = false, $class = '', $autocomplete = true) {
		if(is_array($elemname)) {
			extract($elemname);
		}
		// call parent class contructor
		parent::__construct($elemname, $currentvalue, $prompttext, $helptext, $jsvalidation, $failvalidationmsg, $canbeempty);

		$maxlength = ($maxchars > 0) ? " maxlength=\"$maxchars\"" : '';
		$autocomplete = !($autocomplete) ? " autocomplete=\"off\"" : '';
		$tabindex = guielement::gettabindex();
		$disable_state = $disable ? 'disabled':'';
		if($inputgroup) {
			$this->html_input = "<div class=\"input-group\"><input type=\"text\" name=\"$this->_elemname\" class=\"form-control ".$class."\" id=\"$this->_elemname\" size=\"35\" $disable_state $maxlength tabindex=\"$tabindex\" $autocomplete value=\"" . htmlspecialchars($this->currentvalue) . "\">";
		} else {
			$this->html_input = "<input type=\"text\" name=\"$this->_elemname\" class=\"form-control ".$class."\" id=\"$this->_elemname\" size=\"35\" $disable_state $maxlength tabindex=\"$tabindex\" $autocomplete value=\"" . htmlspecialchars($this->currentvalue) . "\">";
		}
		$this->type = "textbox";
	}
}

// Textbox with Enable/Disable Check after
class gui_textbox_check extends gui_textbox {

	public function __construct($elemname, $currentvalue = '', $prompttext = '', $helptext = '', $jsvalidation = '', $failvalidationmsg = '', $canbeempty = true, $maxchars = 0, $disable=false, $cblabel='Enable', $disabled_value='DEFAULT', $check_enables='true', $cbdisable = false, $class='', $cbclass='') {
		if(is_array($elemname)) {
			extract($elemname);
		}

		// call parent class contructor
		if ($disable) {
			$currentvalue = $disabled_value;
		}

		parent::__construct($elemname, $currentvalue, $prompttext, $helptext, $jsvalidation, $failvalidationmsg, $canbeempty, $maxchars, $disable, true, $class);

		$cb_disable = $cbdisable ? 'disabled':'';
		if(!isset($cbchecked)) {
			if(is_string($check_enables)) {
				$check_enables = ($check_enables == "true") ? true : false;
			}
			$cb_state = $disable && $check_enables || !$disable && !$check_enables ? '':' CHECKED';
		} else {
			$cb_state = $cbchecked ? 'CHECKED' : '';
		}

		if(is_bool($check_enables)) {
			$check_enables = ($check_enables) ? "true" : "false";
		}

		$cbid = !empty($cbelemname) ? $cbelemname : $this->_elemname . '_cb';
		$this->html_input .= "<span class=\"input-group-addon\"><input type=\"checkbox\" name=\"$cbid\" id=\"$cbid\" data-disabled=\"$disabled_value\" data-enables=\"{$check_enables}\" class=\"{$cbclass}\" value=\"checked\" {$cb_state} $cb_disable> <label for=\"$cbid\">$cblabel</label></span></div>";
		$this->html_input .= "<script>$('#{$cbid}').change(function() {
			var state = ($(this).is(':checked') ? true : false),
					disable = ({$check_enables} !== state),
					val = $('#{$elemname}').val();
			if(disable) {
				$('#{$elemname}').data('orig', val);
				$('#{$elemname}').val($(this).data('disabled'));
			} else {
				$('#{$elemname}').val($('#{$elemname}').data('orig'))
			}
			$('#{$elemname}').prop('disabled', disable);
		})</script>";
		$this->type = "textbox_check";
	}
}

// Password
class gui_password extends guiinput {

	public function __construct($elemname, $currentvalue = '', $prompttext = '', $helptext = '', $jsvalidation = '', $failvalidationmsg = '', $canbeempty = true, $maxchars = 0, $disable=false, $class='',$passwordToggle=false) {
		if(is_array($elemname)) {
			extract($elemname);
		}
		// call parent class contructor
		parent::__construct($elemname, $currentvalue, $prompttext, $helptext, $jsvalidation, $failvalidationmsg, $canbeempty);

		$maxlength = ($maxchars > 0) ? " maxlength=\"$maxchars\"" : '';
		$tabindex = guielement::gettabindex();
		$disable_state = $disable ? ' disabled':'';
		/*
		<div class="input-group">
					<span class="input-group-btn">
						<button class="btn btn-default" type="button">Go!</button>
					</span>
					<input type="text" class="form-control" placeholder="Search for...">
				</div><!-- /input-group -->
		 */
		$this->html_input = "<input type=\"password\" autocomplete=\"new-password\" name=\"$this->_elemname\" class=\"form-control ".$class."\" id=\"$this->_elemname\" $disable_state $maxlength tabindex=\"$tabindex\" value=\"" . htmlentities($this->currentvalue) . "\">";
		if($passwordToggle) {
			$input = $this->html_input;
			$this->html_input = "<div class=\"input-group\">".$input."<span class=\"input-group-btn\"><button data-id=\"$this->_elemname\" class=\"btn btn-default toggle-password ".$class."\" type=\"button\" $disable_state><i class=\"fa fa-eye fa-2x\" style=\"margin-top: -2px;\"></i></button></span></div>";
		}
		$this->type = "password";
	}
}

class gui_multiselectbox extends guiinput {
	public function __construct($elemname, $valarray = array(), $currentvalue = array(), $prompttext = '', $helptext = '', $canbeempty = true, $onchange = '', $disable=false, $class = '') {
		if(is_array($elemname)) {
			extract($elemname);
		}
		if (!is_array($valarray)) {
			$valarray = array();
		}

		// currently no validation fucntions availble for select boxes
		// using the normal $canbeempty to flag if a blank option is provided
		parent::__construct($elemname, $currentvalue, $prompttext, $helptext);

		$this->html_input = $this->buildselectbox($valarray, $currentvalue, $canbeempty, $onchange, $disable, $class);
		$this->type = "selectbox";
	}

	// Build select box
	public function buildselectbox($valarray, $currentvalue, $canbeempty, $onchange, $disable, $class='') {
		$output = '';
		$onchange = ($onchange != '') ? " onchange=\"$onchange\"" : '';

		$tabindex = guielement::gettabindex();
		$disable_state = $disable ? ' disabled':'';
		$output .= "\n\t\t\t<select name=\"".$this->_elemname."[]\" class=\"form-control ".$class."\" id=\"$this->_elemname\" tabindex=\"$tabindex\" $disable_state $onchange multiple>\n";
		// include blank option if required
		if ($canbeempty)
			$output .= "<option value=\"\">&nbsp;</option>";

		// build the options
		foreach ($valarray as $item) {
			$itemvalue = (isset($item['value']) ? $item['value'] : '');
			$itemtext = (isset($item['text']) ? $item['text'] : '');
			$itemselected = in_array($itemvalue,$currentvalue) ? ' selected' : '';

			$output .= "\t\t\t\t<option value=\"$itemvalue\"$itemselected>$itemtext</option>\n";
		}
		$output .= "\t\t\t</select>\n\t\t";

		return $output;
	}
}

// Select box
class gui_selectbox extends guiinput {

	public function __construct($elemname, $valarray = array(), $currentvalue = '', $prompttext = '', $helptext = '', $canbeempty = true, $onchange = '', $disable=false, $class = '') {
		if(is_array($elemname)) {
			extract($elemname);
		}
		if (!is_array($valarray)) {
			$valarray = array();
		}

		// currently no validation fucntions availble for select boxes
		// using the normal $canbeempty to flag if a blank option is provided
		parent::__construct($elemname, $currentvalue, $prompttext, $helptext);

		$this->html_input = $this->buildselectbox($valarray, $currentvalue, $canbeempty, $onchange, $disable, $class);
		$this->type = "selectbox";
	}

	// Build select box
	public function buildselectbox($valarray, $currentvalue, $canbeempty, $onchange, $disable, $class='') {
		$output = '';
		$onchange = ($onchange != '') ? " onchange=\"$onchange\"" : '';

		$tabindex = guielement::gettabindex();
		$disable_state = $disable ? ' disabled':'';
		$output .= "\n\t\t\t<select name=\"$this->_elemname\" class=\"form-control ".$class."\" id=\"$this->_elemname\" tabindex=\"$tabindex\" $disable_state $onchange >\n";
		// include blank option if required
		if ($canbeempty)
			$output .= "<option value=\"\">&nbsp;</option>";

		// build the options
		foreach ($valarray as $item) {
			$itemvalue = (isset($item['value']) ? $item['value'] : '');
			$itemtext = (isset($item['text']) ? $item['text'] : '');
			$itemselected = ((string) $currentvalue == (string) $itemvalue) ? ' selected' : '';

			$output .= "\t\t\t\t<option value=\"$itemvalue\"$itemselected>$itemtext</option>\n";
		}
		$output .= "\t\t\t</select>\n\t\t";

		return $output;
	}
}

class gui_checkbox extends guiinput {
	public function __construct($elemname, $checked=false, $prompttext='', $helptext='', $value='on', $post_text = '', $jsonclick = '', $disable=false, $class = '') {
		if(is_array($elemname)) {
			extract($elemname);
		}
		parent::__construct($elemname, '', $prompttext, $helptext);

		$itemchecked = $checked ? 'checked' : '';
		$disable_state = $disable ? ' disabled' : '';
		$js_onclick_include = ($jsonclick != '') ? 'onclick="' . $jsonclick. '"' : '';
		$tabindex = guielement::gettabindex();

		$this->html_input = "<input type=\"checkbox\" name=\"$this->_elemname\" class=\"form-control ".$class."\" id=\"$this->_elemname\" $disable_state tabindex=\"$tabindex\" value=\"$value\" $js_onclick_include $itemchecked/>$post_text\n";
		$this->type = "checkbox";
	}
}

class gui_checkset extends guiinput {
	public function __construct($elemname, $valarray = array(), $currentvalue = '', $prompttext = '', $helptext = '', $disable=false, $jsonclick = '', $class = '') {
		if(is_array($elemname)) {
			extract($elemname);
		}
		if (!is_array($valarray) || empty($valarray)) {
			trigger_error('$valarray must be a valid array in gui_checkset');
			return;
		}

		parent::__construct($elemname, $currentvalue, $prompttext, $helptext);

		$this->html_input = $this->buildcheckset($valarray, $currentvalue, $disable, $class);
		$this->type = "radio";
	}

	private function buildcheckset($valarray, $currentvalue, $disable=false, $class='') {
		$output = '';
		$output .= '<span class="radioset">';

		$count = 0;
		foreach ($valarray as $item) {
			$itemvalue = (isset($item['value']) ? $item['value'] : '');
			$itemtext = (isset($item['text']) ? $item['text'] : '');
			$itemchecked = (in_array((string)$itemvalue,$currentvalue)) ? ' checked' : '';

			$tabindex = guielement::gettabindex();
			$disable_state = $disable ? ' disabled':'';
			$output .= "<input type=\"checkbox\" name=\"$itemvalue\"  class=\"form-control ".$class."\" id=\"$this->_elemname$count\" $disable_state tabindex=\"$tabindex\" value=\"checked\" $itemchecked/><label for=\"$this->_elemname$count\" value=\"checked\">$itemtext</label>\n";
			$count++;
		}
		$output .= '</span>';
		return $output;
	}
}

class gui_radio extends guiinput {
	public function __construct($elemname, $valarray = array(), $currentvalue = '', $prompttext = '', $helptext = '', $disable=false, $jsonclick = '', $class = '', $pairedvalues = true) {
		if(is_array($elemname)) {
			extract($elemname);
		}
		if (!is_array($valarray) || empty($valarray)) {
			trigger_error('$valarray must be a valid array in gui_radio');
			return;
		}

		parent::__construct($elemname, $currentvalue, $prompttext, $helptext);

		$this->html_input = $this->buildradiobuttons($valarray, $currentvalue, $disable, $jsonclick, $class, $pairedvalues);
		$this->type = "radio";
	}

	private function buildradiobuttons($valarray, $currentvalue, $disable=false, $jsonclick='', $class='', $pairedvalues = true) {
		$output = '';
		$output .= '<span class="radioset">';
		$pairedvalues = ($pairedvalues) ? true : false;
		$count = 0;
		foreach ($valarray as $item) {
			$itemvalue = (isset($item['value']) ? $item['value'] : '');
			$itemtext = (isset($item['text']) ? $item['text'] : '');
			$itemchecked = ((string) $currentvalue == (string) $itemvalue) ? ' checked' : '';

			$tabindex = guielement::gettabindex();
			$disable_state = $disable ? ' disabled':'';
			$value = ($pairedvalues) ? $this->_elemname."=".$itemvalue : $itemvalue;
			$output .= "<input type=\"radio\" name=\"$this->_elemname\"  onclick=\"$jsonclick\" class=\"form-control ".$class."\" id=\"$this->_elemname$count\" $disable_state tabindex=\"$tabindex\" value=\"{$value}\" $itemchecked/><label for=\"$this->_elemname$count\">$itemtext</label>\n";
			$count++;
		}
		$output .= '</span>';
		return $output;
	}
}

class gui_button extends guiinput {

	public function __construct($elemname, $value, $prompttext = '', $helptext = '', $post_text = '', $jsonclick = '', $disable=false, $class = '') {
		if(is_array($elemname)) {
			extract($elemname);
		}
		parent::__construct($elemname, '', $prompttext, $helptext);

		$disable_state = $disable ? ' disabled' : '';
		$js_onclick_include = ($jsonclick != '') ? 'onclick="' . $jsonclick. '"' : '';
		$tabindex = guielement::gettabindex();
		$this->html_input = "<button type=\"button\" name=\"$this->_elemname\" class=\"btn form-control ".$class."\" id=\"$this->_elemname\" $disable_state tabindex=\"$tabindex\" value=\"$value\" $js_onclick_include/>$post_text</button>\n";
		$this->type = "button";
	}
}

class gui_alertinfodrawselects extends guiinput {

	public function __construct($elemname, $currentvalue = '', $prompttext = '', $helptext = '', $canbeempty = true, $onchange = '', $disable=false, $class = '') {
		if(is_array($elemname)) {
			extract($elemname);
		}

		// currently no validation fucntions availble for select boxes
		// using the normal $canbeempty to flag if a blank option is provided
		parent::__construct($elemname, $currentvalue, $prompttext, $helptext);

		$this->html_input = $this->buildselectbox($currentvalue, $canbeempty, $onchange, $disable, $class);
		$this->type = "selectbox";
	}

	// Build select box
	public function buildselectbox($currentvalue, $canbeempty, $onchange, $disable, $class='') {
		$output = '';

		//TODO someone needs to fix this. Seems sort of irrelevant right now though
		$onchange = ($onchange != '') ? " onchange=\"$onchange\"" : '';

		$output = \FreePBX::View()->alertInfoDrawSelect($this->_elemname, $currentvalue, $class, $canbeempty, $disable);

		return $output;
	}
}

class gui_drawselects extends guiinput {

	public function __construct($elemname, $index = '', $dest = '', $prompttext = '', $helptext = '', $required = false, $failvalidationmsg='', $nodest_msg='', $disable=false, $class='') {
		if(is_array($elemname)) {
			extract($elemname);
		}
		if(trim($index) == '') {
			trigger_error('$index can not be blank');
			return;
		}
		global $currentcomponent;
		$jsvalidation = isset($jsvalidation) ? $jsvalidation : '';
		$jsvalidationtest = isset($jsvalidationtest) ? $jsvalidationtest : '';
		parent::__construct($elemname, '', $prompttext, $helptext, $jsvalidation, $failvalidationmsg, '', $jsvalidationtest);

		$reset = isset($reset) && $reset ? true : false;
		$this->html_input=drawselects($dest, $index, false, false, $nodest_msg, $required, false,$reset,$disable,$class);

		$hidden =  new gui_hidden($elemname,'goto'.$index,false);
		$this->html_input .= $hidden->_html;
		$this->type = "select";
	}
}

class gui_textarea extends guiinput {
	public function __construct($elemname, $currentvalue = '', $prompttext = '', $helptext = '', $jsvalidation = '', $failvalidationmsg = '', $canbeempty = true, $maxchars = 0, $class='', $select = array()) {
		if(is_array($elemname)) {
			extract($elemname);
		}
		parent::__construct($elemname, $currentvalue, $prompttext, $helptext, $jsvalidation, $failvalidationmsg, $canbeempty);

		$maxlength = ($maxchars > 0) ? " maxlength=\"$maxchars\"" : '';

		$disable_state = isset($disable) && $disable ? ' disabled' : '';
		$list = explode("\n",$this->currentvalue);
		$rows = count($list);
		$rows = (($rows > 20) ? 20 : $rows);
		$rows++;

		$this->html_input = "<textarea rows=\"$rows\" name=\"$this->_elemname\" class=\"form-control autosize ".$class."\" id=\"$this->_elemname\" $maxlength $disable_state>" . htmlentities($this->currentvalue) . "</textarea>";
		$this->type = "textarea";
	}
}

class gui_textarea_select extends gui_textarea {
	public function __construct($elemname, $currentvalue = '', $prompttext = '', $helptext = '', $jsvalidation = '', $failvalidationmsg = '', $canbeempty = true, $maxchars = 0, $class='',$select=array()) {
		if(is_array($elemname)) {
			extract($elemname);
		}
		parent::__construct($elemname, $currentvalue, $prompttext, $helptext, $jsvalidation, $failvalidationmsg, $canbeempty, $maxchars, $class);

		$select = is_array($select) ? $select : array();
		$disable_state = isset($disable) && $disable ? ' disabled' : '';
		$maxlength = ($maxchars > 0) ? " maxlength=\"$maxchars\"" : '';
		$list = explode("\n",$this->currentvalue);
		$rows = count($list);
		$rows = (($rows > 20) ? 20 : $rows);
		$rows++;

		$this->html_input = '<div class="input-group">';
		$this->html_input .= '<textarea id="'.$this->_elemname.'" class="form-control autosize '.$class.'" rows="'.$rows.'" name="'.$this->_elemname.'" '.$maxlength.' '.$disable_state.'>'.htmlentities($this->currentvalue).'</textarea>';
		$this->html_input .= '<span class="input-group-addon">';
		$this->html_input .= '<select id="'.$this->_elemname.'_select" class="form-control" data-for="'.$this->_elemname.'" style="width:170px;">';
		$this->html_input .= '<option selected="" value="">'._("Quick Select").'</option>';
		foreach($select as $data) {
			$this->html_input .= '<option value="'.$data['value'].'">'.$data['text'].'</option>';
		}
		$this->html_input .= '</select></span></div>';
		$this->html_input .= "<script>$('#{$this->_elemname}_select').change(function() {
			var taelm = $(this).data('for');
			var cval = $('#'+taelm).val();
			if(cval.length === 0){
				$('#'+taelm).val($(this).val());
				$(this).children('option[value=\"'+$(this).val()+'\"]').remove();
			}else{
				$('#'+taelm).val(cval+\"\\n\"+$(this).val());
				$(this).children('option[value=\"'+$(this).val()+'\"]').remove();
			}
			var ta = document.querySelector('textarea.autosize');
			autosize.update(ta);
		})</script>";
		$this->type = "textarea_select";
	}
}

/*
 ************************************************************
 ** guitext is the base class of all text fields (e.g. h1) **
 ************************************************************
 */

class guitext extends guielement {
	protected $html_text;
	protected $helptext;
	protected $prompttext;
	protected $type;
	protected $_elemname;

	public function __construct($elemname, $html_text = '') {
		// call parent class contructor
		parent::__construct($elemname, '', '');

		$this->html_text = $html_text;
	}

	public function getRawArray() {
		return array(
			'helptext' => $this->helptext,
			'prompttext' => $this->prompttext,
			'html' => $this->html_text,
			'type' => $this->type,
			'name' => $this->_elemname
		);
	}

	public function generatehtml($section = '') {
		// this effectivly creates the template using the html_text
		// we would expect the $html_text to be set by the child class

		$output = '';

		// start new row
		if ($section) {
			$mysec = preg_replace('/[^A-Za-z]/', '' ,$section);
			$output .= '<tr class="' . $section . '" '
				. ((isset($this->_opts[$mysec]['guielToggle']) && $this->_opts[$mysec]['guielToggle'] == false)
						? ' style="display:none" '
						: '')
				.' >' . "\n";
		} else {
			$output .= "\t<tr>\n";
		}


		// actual input in second row
		$output .= "\t\t<td colspan=\"2\">";
		$output .= $this->html_text;
		$output .= "</td>\n";

		// end this row
		$output .= "\t</tr>\n";

		return $output;
	}
}

/**
 * Generate a bootstrap table
 */
class gui_table extends guitext {
	public function __construct($elemname, $headers, $rows=array(), $options=array()) {
		// call parent class contructor
		parent::__construct($elemname, '');

		$default = array(
			"cache" => "false",
			"cookie" => "true",
			"cookie-id-table" => $this->_elemname,
			"maintain-selected" => "true",
			"show-columns" => "true",
			"show-toggle" => "true",
			"pagination" => "true",
			"search" => "true"
		);
		foreach($default as $key => $value) {
			if(!isset($options[$key])) {
				$options[$key] = $value;
			}
		}

		$html = '<table id="'.$this->_elemname.'" data-toggle="table" ';
		foreach($options as $key => $value) {
			$html .= "data-".$key."=".$value." ";
		}
		$html .= ' class="table table-striped">';
		$html .= '<thead><tr>';
		foreach($headers as $data) {
			$html .= '<th ';
			if(!empty($data['options'])) {
				foreach($data['options'] as $key => $value) {
					$html .= "data-".$key."=".$value." ";
				}
			}
			$html .= '>'.$data['name'].'</th>';
		}
		$html .= '</tr></thead>';
		if(!empty($rows)) {
			$html .= '<tbody>';
			foreach($rows as $row) {
				$html .= '<tr>';
				foreach($row as $cel) {
					$html .= '<td>'.$cel.'</td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody>';
		}
		$html .= '</table>';
		$this->html_text = $html;
		$this->type = "table";
	}
}

/**
 * Send HTML down the line directly into the component
 */
class gui_html extends guitext {
	public function __construct($elemname, $html) {
		// call parent class contructor
		parent::__construct($elemname, $html);

		// nothing really needed here as it's just whatever html was passed
		// but suppose we should do something with the element name
		$this->html_text = $html;
		$this->type = "html";
	}
}

// Label -- just text basically!
class gui_label extends guitext {
	public function __construct($elemname, $text, $uselang = true, $class='') {
		// call parent class contructor
		parent::__construct($elemname, $text);

		// nothing really needed here as it's just whatever text was passed
		// but suppose we should do something with the element name
		$this->html_text = "<span id=\"$this->_elemname\" class=\"".$class."\">$text</span>";
		$this->type = "label";
	}
}

// Main page header
class gui_pageheading extends guitext {
	public function __construct($elemname, $text, $uselang = true, $class='') {
		// call parent class contructor
		parent::__construct($elemname, $text);

		// H2
		$this->html_text = "<h2 id=\"$this->_elemname\" class=\"".$class."\">$text</h2>";
		$this->type = "heading";
	}
}

// Second level / sub header
class gui_subheading extends guitext {
	public function __construct($elemname, $text, $uselang = true, $class='') {
		// call parent class contructor
		parent::__construct($elemname, $text);

		// H3
		$this->html_text = "<h3 id=\"$this->_elemname\" class=\"".$class."\">$text</h3>";
		$this->type = "subheading";
	}
}

// URL / Link
class gui_link extends guitext {
	public function __construct($elemname, $text, $url, $uselang = true, $class='') {
		// call parent class contructor
		parent::__construct($elemname, $text);

		// A tag
		$this->html_text = "<a href=\"$url\" id=\"$this->_elemname\" class=\"".$class."\">$text</a>";
		$this->type = "link";
	}
}
class gui_link_label extends guitext {
	public function __construct($elemname, $text, $tooltip, $uselang = true, $class='') {
		// call parent class contructor
		parent::__construct($elemname, $text);

		// A tag
		$this->html_text = "<a href=\"#\" class=\"info ".$class."\" id=\"$this->_elemname\">$text:<span>$tooltip</span></a>";
		$this->type = "linklabel";
	}
}
