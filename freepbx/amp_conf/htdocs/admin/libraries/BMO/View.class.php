<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
* This is the FreePBX Big Module Object.
*
* License for all code of this FreePBX module can be found in the license file inside the module directory
* Copyright 2006-2014 Schmooze Com Inc.
*/
namespace FreePBX;
use Carbon\Carbon;
class View {
	private $queryString = "";
	private $replaceState = false;
	private $lang = array();
	private $tz = '';
	private $dateformat = '';
	private $timeformat = '';
	private $datetimeformat = '';
	private $drawselect_destinations = [];

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new Exception("Not given a FreePBX Object");
		}
		$this->freepbx = $freepbx;
		$this->nt = $this->freepbx->Notifications;
	}

	public function getScripts() {
		$files = array(
			"moment-with-locales-2.20.1.min.js",
			"script.legacy.js",
			"Sortable-1.10.4.js",
			"autosize-3.0.17.min.js",
			"bootstrap-3.3.7.custom.min.js",
			"bootstrap-multiselect-0.9.13.js",
			"bootstrap-select-1.12.1.min.js",
			"bootstrap-table-dev.min.js",
			"bootstrap-table-extensions-dev/bootstrap-table-cookie.min.js",
			"bootstrap-table-extensions-dev/bootstrap-table-export.min.js",
			"bootstrap-table-extensions-dev/bootstrap-table-mobile.min.js",
			"bootstrap-table-extensions-dev/bootstrap-table-reorder-rows.min.js",
			"bootstrap-table-extensions-dev/bootstrap-table-toolbar.min.js",
			"browser-locale-1.0.0.min.js",
			"browser-support.js",
			"chosen.jquery-1.6.2.min.js",
			"jquery-migrate-3.0.0.js",
			"jquery-ui-1.12.1.min.js",
			"jquery.fileupload-9.12.5.js",
			"jquery.fileupload-process-9.12.5.js",
			"jquery.form-3.51.min.js",
			"jquery.hotkeys-0.2.0.js",
			"jquery.iframe-transport-9.12.5.js",
			"jquery.jplayer-2.9.2.min.js",
			"jquery.numeric-1.4.1.min.js",
			"jquery.smartWizard-3.3.1.js",
			"jquery.tablednd-0.9.1.min.js",
			"js.cookie-2.1.3.min.js",
			"modernizr-3.3.1.min.js",
			"moment-timezone-with-data-2012-2022-0.5.14-2017c.min.js",
			"moment-duration-format-2.2.1.js",
			"notie-3.9.4.min.js",
			"recorder.js",
			"recorderWorker.js",
			"search.js",
			"tableexport-1.9.6.js",
			"timeutils.js",
			"typeahead.bundle-0.10.5.min.js",
			"text-editor-1.2.1.js"
		);

		$package = $this->freepbx->Config->get('USE_PACKAGED_JS');

		if($package) {
			$jspath = $this->freepbx->Config->get('AMPWEBROOT') .'/admin/assets/js';
			$sha1 = '';
			foreach($files as $file) {
				$filename = $jspath."/".$file;
				if(file_exists($filename)) {
					$sha1 .= sha1_file($filename);
				}
			}

			$final = sha1($sha1);

			$pbxlibFilename = $jspath."/pbxlib_".$final.".js";
			if(!file_exists($pbxlibFilename)) {
				set_time_limit(0);
				//cleanup
				foreach(glob($jspath.'/pbxlib_*.js') as $f) {
					unlink($f);
				}
				$contents = '';
				foreach($files as $file) {
					$filename = $jspath."/".$file;
					if(file_exists($filename)) {
						$contents .= file_get_contents($filename)."\n";
					}
				}
				$minifiedCode = \JShrink\Minifier::minify($contents);
				file_put_contents($pbxlibFilename,$minifiedCode);
				//JIC for legacy for now
				file_put_contents($jspath."/pbxlib.js",$minifiedCode);
			}
			return array(basename($pbxlibFilename));
		} else {
			return $files;
		}
	}

	/**
	 * This is a replace of the old redirect standard.
	 * It emulates the same functionality but instead using HTML5 pushState
	 * The javascript part of the code is in footer.php
	 * @url https://developer.mozilla.org/en-US/docs/Web/Guide/API/DOM/Manipulating_the_browser_history
	 * @return bool True if the URL will be replaced, false if the URL won't be changed
	 */
	public function redirect_standard() {
		if($this->replaceState) {
			throw new \Exception("Redirect Standard was called twice in the same page load. This is wrong");
		}
		$replace = false;
		$args = func_get_args();
		if(empty($args)) {
			return false;
		}
		parse_str($_SERVER['QUERY_STRING'], $params);
		$vars = array_keys($params);
		foreach($args as $arg) {
			if((!in_array($arg,$vars) && isset($_REQUEST[$arg])) || (in_array($arg,$vars) && isset($_REQUEST[$arg]) && $_REQUEST[$arg] != $params[$arg])) {
				$params[$arg] = $_REQUEST[$arg];
				$replace = true;
			}
		}
		if(!$replace) {
			return false;
		}
		$base = basename($_SERVER['PHP_SELF']);
		$this->queryString = $base."?".http_build_query($params);
		$this->replaceState = true;
		return true;
	}

	/**
	 * To run the javascript or not? A simple method to check the state
	 */
	public function replaceState() {
		return $this->replaceState;
	}

	/**
	 * Get the finalized Query String for replacement
	 */
	public function getQueryString() {
		return $this->queryString;
	}

	/**
	 * Get Moment Date/Time Format
	 *
	 * Tests the format before returning it
	 *
	 * @return string Format
	 */
	public function getDateTimeFormat() {
		$this->getDateTime();
		return $this->datetimeformat;
	}

	/**
	 * Get Moment Date/Time Format
	 *
	 * Tests the format before returning it
	 *
	 * @return string Format
	 */
	public function getTimeFormat() {
		$this->getTime();
		return $this->timeformat;
	}

	/**
	 * Get Moment Date/Time Format
	 *
	 * Tests the format before returning it
	 *
	 * @return string Format
	 */
	public function getDateFormat() {
		$this->getDate();
		return $this->dateformat;
	}

	/**
	 * Set Date/Time Format
	 * @param string $format The Format to use
	 */
	public function setDateTimeFormat($format) {
		$this->datetimeformat = $format;
	}

	/**
	 * Set Date/Time Format
	 * @param string $format The Format to use
	 */
	public function setTimeFormat($format) {
		$this->timeformat = $format;
	}

	/**
	 * Set Date/Time Format
	 * @param string $format The Format to use
	 */
	public function setDateFormat($format) {
		$this->dateformat = $format;
	}

	/**
	 * Set System Timezone
	 */
	public function setTimezone($timezone=null) {
		if(empty($timezone) && !empty($this->tz)) {
			return $this->tz;
		}
		date_default_timezone_set('UTC');
		$freepbxtimezone = $this->freepbx->Config->get('PHPTIMEZONE');
		$phptimezone = !empty($timezone) ? $timezone : $freepbxtimezone;
		$phptimezone = (trim($phptimezone) != "/") ? trim($phptimezone) : '';
		$invalidtimezone = false;
		if(!empty($phptimezone)) {
			$tzi = \DateTimeZone::listIdentifiers();
			if(!in_array($phptimezone,$tzi)) {
				$invalidtimezone = $phptimezone;
				$phptimezone = 'UTC';
			}
			date_default_timezone_set($phptimezone);
		}
		if(!empty($invalidtimezone)) {
			//$this->nt->add_warning("framework", "TIMEZONE", _("Unable to set timezone"), sprintf(_("Unable to set timezone to %s because PHP does not support that timezone, the timezone has been temporarily changed to UTC. Please set the timezone in Advanced Settings."),$invalidtimezone), "config.php?display=advancedsettings", true, true);
		} else {
			//$this->nt->delete("framework", "TIMEZONE");
		}
		$this->tz = date_default_timezone_get();
		return $this->tz;
	}

	/**
	 * Get User or System Timezone
	 * @param  int $userid The User Manager ID, if not supplied try to infere it
	 * @return string         The Timezone
	 */
	public function getTimezone() {
		if(empty($this->tz)) {
			$this->setTimezone();
		}
		$tz = $this->tz;
		return $tz;
	}

	/**
	 * Get Formatted Date String
	 * @param  integer $timestamp Unix Timestamp, if empty then NOW
	 * @return string            The date string
	 */
	public function getDate($timestamp=null) {
		$m = $this->getMoment($timestamp);

		try{
			//$format = (php_sapi_name() !== 'cli' && !empty($_SESSION['AMP_user']->dateformat)) ? $_SESSION['AMP_user']->dateformat : $this->freepbx->Config->get("MDATEFORMAT");
			$format = !empty($this->dateformat) ? $this->dateformat : $this->freepbx->Config->get("MDATEFORMAT");
			$out = $m->format($format, new \Moment\CustomFormats\MomentJs());
		} catch(\Exception $e) {
			$format = "l";
			$out = $m->format($format, new \Moment\CustomFormats\MomentJs());
		}
		$this->dateformat = $format;

		return $out;
	}

	/**
	 * Get Formatted Time String
	 * @param  integer $timestamp Unix Timestamp, if empty then NOW
	 * @return string            The time string
	 */
	public function getTime($timestamp=null) {
		$m = $this->getMoment($timestamp);

		try{
			//$format = (php_sapi_name() !== 'cli' && !empty($_SESSION['AMP_user']->timeformat)) ? $_SESSION['AMP_user']->timeformat : $this->freepbx->Config->get("MTIMEFORMAT");
			$format = !empty($this->timeformat) ? $this->timeformat : $this->freepbx->Config->get("MTIMEFORMAT");
			$out = $m->format($format, new \Moment\CustomFormats\MomentJs());
		} catch(\Exception $e) {
			$format = "LT";
			$out = $m->format($format, new \Moment\CustomFormats\MomentJs());
		}
		$this->timeformat = $format;

		return $out;
	}

	/**
	 * Get Formatted Date/Time String
	 * @param  integer $timestamp Unix Timestamp, if empty then NOW
	 * @return string            The formatted date/time string
	 */
	public function getDateTime($timestamp=null) {
		$m = $this->getMoment($timestamp);

		try{
			//$format = (php_sapi_name() !== 'cli' && !empty($_SESSION['AMP_user']->datetimeformat)) ? $_SESSION['AMP_user']->datetimeformat : $this->freepbx->Config->get("MDATETIMEFORMAT");
			$format = !empty($this->datetimeformat) ? $this->datetimeformat : $this->freepbx->Config->get("MDATETIMEFORMAT");
			$out = $m->format($format, new \Moment\CustomFormats\MomentJs());
		} catch(\Exception $e) {
			$format = "llll";
			$out = $m->format($format, new \Moment\CustomFormats\MomentJs());
		}
		$this->datetimeformat = $format;

		return $out;
	}

	/**
	 * Get the moment object at time
	 * @param  integer $timestamp Unix Timestamp, if empty then NOW
	 * @return object            The moment object
	 */
	public function getMoment($timestamp=null) {
		$timestamp = !empty($timestamp) ? $timestamp : time();
		$m = new \Moment\Moment('@'.$timestamp, $this->getTimezone());
		return $m;
	}

	/**
	 * Get Locale
	 * @return string            The locale  (en_US)
	 */
	public function getLocale() {
		if(empty($this->lang)) {
			$this->setLanguage();
		}
		return $this->lang['name'];
	}

	/**
	 * Set System Language
	 * @param boolean $details If we should return details or just the name
	 */
	public function setLanguage($language=null,$details=false) {
		if(empty($language) && !empty($this->lang)) {
			return $details ? $this->lang : $this->lang['name'];
		}

		$UIDEFAULTLANG = $this->freepbx->Config->get("UIDEFAULTLANG");
		$lang = !empty($language) ? $language : $UIDEFAULTLANG;
		$expression = '/^([a-z]*(?:_[A-Z]{2})?)(?:\.([a-zA-Z1-9\-]*))?(?:@([a-z1-9]*))?$/';
		$default = "en_US";
		$defaultParts = array(
			'en_US',
			'en_US'
		);

		$nt = $this->freepbx->Notifications;
		if (!extension_loaded('gettext')) {
			$this->lang = array("full" => $default, "name" => $default, "charmap" => "", "modifiers" => "");
			//$nt->add_warning('core', 'GETTEXT', _("Gettext is not installed"), _("Please install gettext so that the PBX can properly translate itself"),'https://www.gnu.org/software/gettext/');
			return $details ? $this->lang : $this->lang['name'];
		}
		$nt->delete('core', 'GETTEXT');

		//Break Locales apart for processing
		if(!preg_match($expression, $lang, $langParts)) {
			$this->lang = array("full" => $default, "name" => $default, "charmap" => "", "modifiers" => "");
			//$nt->add_warning('framework', 'LANG_INVALID1', _("Invalid Language"), sprintf(_("You have selected an invalid language '%s' this has been automatically switched back to '%s' please resolve this in advanced settings [%s]"),$lang,$default, "Expression Failure"), "?display=advancedsettings");
			$lang = $default;
			$langParts = $defaultParts;
		} else {
			//$nt->delete('framework', 'LANG_INVALID1');
		}

		//Get locale list
		exec('locale -a',$locales, $out);
		if($out != 0) { //could not execute locale -a
			$this->lang = array("full" => $default, "name" => $default, "charmap" => "", "modifiers" => "");
			//$nt->add_warning('framework', 'LANG_MISSING', _("Language Support Unknown"), _("Unable to find the Locale binary. Your system may not support language changes!"), "?display=advancedsettings");
			return $details ? $this->lang : $this->lang['name'];
		} else {
			//$nt->delete('framework', 'LANG_MISSING');
		}
		$locales = is_array($locales) ? $locales : array();

		if(php_sapi_name() !== 'cli') {
			//Adjust for RTL languages
			$rtl_locales = array( 'ar', 'ckb', 'fa_IR', 'he_IL', 'ug_CN' );
			$_SESSION['langdirection'] = in_array($langParts[1],$rtl_locales) ? 'rtl' : 'ltr';
		}

		//Lets see if utf8 codeset exists if not previously defined
		if(empty($langParts[2])) {
			$testString = !empty($langParts[3]) ? $langParts[1].".utf8@".$langParts[3] : $langParts[1].".utf8";
			if(in_array($testString,$locales)) {
				$langParts[2] = 'utf8';
				$lang = $testString;
			} else {
				$testString = !empty($langParts[3]) ? $langParts[1].".UTF8@".$langParts[3] : $langParts[1].".UTF8";
				if(in_array($testString,$locales)) {
					$langParts[2] = 'UTF8';
					$lang = $testString;
				} else {
					$testString = !empty($langParts[3]) ? $langParts[1].".UTF-8@".$langParts[3] : $langParts[1].".UTF-8";
					if(in_array($testString,$locales)) {
						$langParts[2] = 'UTF-8';
						$lang = $testString;
					} else {
						$langParts[2] = '';
					}
				}
			}
		}

		if(!empty($locales) && !in_array($lang,$locales)) {
			if(in_array($default,$locales)) { //found en_US in the array!
				$elang = $lang;
				$lang = $default;
				$langParts = $defaultParts;
				$this->lang = array("full" => $default, "name" => $default, "charmap" => "", "modifiers" => "");
				//$nt->add_warning('framework', 'LANG_INVALID2', _("Invalid Language"), sprintf(_("You have selected an invalid language '%s' this has been automatically switched back to '%s' please resolve this in advanced settings [%s]"),$elang,$default, "Nonexistent in Locale"), "?display=advancedsettings");
			} elseif($lang == $default) {
				$this->lang = array("full" => $default, "name" => $default, "charmap" => "", "modifiers" => "");
				//$nt->add_warning('framework', 'LANG_INVALID2', _("Invalid Language"), sprintf(_("The default language of '%s' or '%s' was not found on this system. Please resolve this in advanced settings by changing the system language or installing the default locales [%s]"),$default,$default.".utf8", "Nonexistent in Locale, Missing ".$default), "?display=advancedsettings");
				return $details ? $this->lang : $this->lang['name'];
			} else {
				$this->lang = array("full" => $default, "name" => $default, "charmap" => "", "modifiers" => "");
				//$nt->add_warning('framework', 'LANG_INVALID2', _("Invalid Language"), sprintf(_("You have selected an invalid language '%s' and we were unable to fallback to '%s' or '%s' please resolve this in advanced settings [%s]"),$lang,$default,$default.".utf8", "Nonexistent in Locale, Missing ".$default), "?display=advancedsettings");
				return $details ? $this->lang : $this->lang['name'];
			}
		} else {
			//$nt->delete('framework', 'LANG_INVALID2');
		}

		if(empty($langParts[3])) {
			$langParts[3] = '';
		}

		putenv('LC_ALL='.$lang);
		putenv('LANG='.$lang);
		putenv('LANGUAGE='.$lang);
		setlocale(LC_ALL, $lang);

		//Set Carbon Locale
		if(!Carbon::setLocale($lang)) {
			$ps = explode("_",$lang);
			if(!Carbon::setLocale($ps[0])) {
				Carbon::setLocale('en');
			}
		}

		// Moment does not support UTF8 locale parts, so let's remove that from
		// the locale string if it exists
		$baseLocale = $this->getBaseLocale($lang);
		try {
			\Moment\Moment::setLocale($baseLocale);
		} catch(\Exception $e) {
			//invalid locale. Not all locales are supported though
			\Moment\Moment::setLocale('en_US');
		}

		bindtextdomain('amp',$this->freepbx->Config->get("AMPWEBROOT").'/admin/i18n');
		bind_textdomain_codeset('amp', 'utf8');
		textdomain('amp');

		$this->lang = array("full" => $lang, "name" => (isset($langParts[1]) ? $langParts[1] : ''), "charmap" => (isset($langParts[2]) ? $langParts[2] : ''), "modifiers" => (isset($langParts[3]) ? $langParts[3] : ''));

		return $details ? $this->lang : $this->lang['name'];
	}

	/**
	 * Set Locales for the Admin interface
	 */
	public function setAdminLocales() {
		// set the language so local module languages take
		$lang = '';
		if(php_sapi_name() !== 'cli') {
			if(!empty($_SESSION['AMP_user']->lang)) {
				$lang = $_SESSION['AMP_user']->lang;
			} elseif (!empty($_COOKIE['lang'])) {
				$lang = $_COOKIE['lang'];
			}
		}
		$lang = $this->setLanguage($lang);
		if(php_sapi_name() !== 'cli') {
			setcookie("lang", $lang);
			$_COOKIE['lang'] = $lang;
		}
		$language = $lang;
		//set this before we run date functions
		if(php_sapi_name() !== 'cli' && !empty($_SESSION['AMP_user']->tz)) {
			//userman mode
			$phptimezone = $_SESSION['AMP_user']->tz;
		} else {
			$phptimezone = '';
		}
		$timezone = $this->setTimezone($phptimezone);

		if(php_sapi_name() !== 'cli' && !empty($_SESSION['AMP_user']->datetimeformat)) {
			$this->setDateTimeFormat($_SESSION['AMP_user']->datetimeformat);
		}

		if(php_sapi_name() !== 'cli' && !empty($_SESSION['AMP_user']->timeformat)) {
			$this->setTimeFormat($_SESSION['AMP_user']->timeformat);
		}

		if(php_sapi_name() !== 'cli' && !empty($_SESSION['AMP_user']->dateformat)) {
			$this->setDateFormat($_SESSION['AMP_user']->dateformat);
		}

		return array("timezone" => $timezone, "language" => $language, "datetimeformat" => "", "timeformat" => "", "dateformat" => "");
	}

	/**
	 * Alert Info Hookable Draw Select
	 * @param  sting $id        The id of the select box and javascripts
	 * @param  string $value     The selected value
	 * @param  string $class     Additional classes to add
	 * @param  bool $allowNone Allow none to be a selectable item
	 * @param  bool $disable   Disable the element
	 * @param  bool $required  Is this a required element
	 */
	public function alertInfoDrawSelect($id, $value = '', $class = '', $allowNone = true, $disable = false, $required = false) {
		$display = !empty($_REQUEST['display']) ? $_REQUEST['display'] : '';
		$optionshtml = '';

		$value = trim($value);

		$hooks = $this->freepbx->Hooks->returnHooks();
		$selected = false;
		$hooks = is_array($hooks) ? $hooks : array();
		$optionshtml .= '<option value="">'._('None').'</option>';
		foreach($hooks as $hook) {
			$mod = $hook['module'];
			$meth = $hook['method'];
			$items = $this->freepbx->$mod->$meth($display);
			if(!is_array($items)) {
				continue;
			}
			foreach($items as $key => $item) {
				if($item['value'] == $value) {
					$selected = true;
				}
				$optionshtml .= '<option value="'.htmlentities($item['value']).'" data-id="'.$mod.'-'.$key.'" data-playback="'.(!empty($item['playback']) ? 'true' : 'false').'" '.(($item['value'] == $value) ? "selected" : "").'>'.htmlentities($item['name']).'</option>';
			}
		}
		if(trim($value) != "" && !$selected) {
			$optionshtml .= '<option value="'.htmlentities($value).'" selected>'.htmlentities($value).'</option>';
		}

		$optionshtml .= '<option value="custom">['._("Custom").']</option>';

		return '<select id="'.$id.'" name="'.$id.'" class="form-control '.$class.' custom-select" '.($required ? 'required' : '').' '.($disable ? 'disabled' : '').'>'.$optionshtml.'</select>';
	}

	public function mediaControls($id, $title='', $class='', $hidden=false, $record=false) {
		$class .= ($hidden) ? " hidden" : "";
		if(empty($title) && $record) {
			$title = _("Hit the red record button to start recording from your browser");
		} elseif(empty($title) && !$record) {
			$title = _("Hit the play symbol to listen");
		}
		$html = '';
		$type = (!$record) ? "player" : "recorder";
		$html .= '<div id="'.$id.'-media-container" class="media-'.$type.'-container '.$class.'">';
			$html .= '<div id="'.$id.'-media-controls" class="controls">';
				$html .= '<div id="'.$id.'-player" class="jp-jplayer"></div>';
				$html .= '<div id="'.$id.'-player-container" data-player="'.$id.'-player" class="jp-audio-freepbx" role="application" aria-label="media player">';
					$html .= '<div class="jp-type-single">';
						$html .= '<div class="jp-gui jp-interface">';
							$html .= '<div class="jp-controls">';
								$html .= '<i class="fa fa-play jp-play"></i>';
								if($record) {
									$html .= '<i id="record" class="fa fa-circle"></i>';
								} else {
									$html .= '<i class="fa fa-undo"></i>';
								}
							$html .= '</div>';
						$html .= '</div>';
						$html .= '<div class="jp-progress">';
							$html .= '<div class="jp-seek-bar progress">';
								$html .= '<div class="jp-current-time" role="timer" aria-label="time">&nbsp;</div>';
								$html .= '<div class="progress-bar progress-bar-striped active" style="width: 100%;"></div>';
								$html .= '<div class="jp-play-bar progress-bar"></div><div class="jp-ball"></div></div>';
								$html .= '<div class="jp-duration" role="timer" aria-label="duration">&nbsp;</div>';
							$html .= '</div>';
						$html .= '</div>';
						$html .= '<div class="jp-volume-controls">';
							$html .= '<i class="fa fa-volume-up jp-mute"></i>';
							$html .= '<i class="fa fa-volume-off jp-unmute"></i>';
						$html .= '</div>';
						$html .= '<div class="jp-details">';
							$html .= '<div class="jp-title" aria-label="title">'.$title.'</div>';
						$html .= '</div>';
						$html .= '<div class="jp-no-solution">';
							$html .= '<span>'._("Update Required").'</span>';
							$html .= sprintf(_("To play the media you will need to either update your browser to a recent version or update your %s"),'<a href="http://get.adobe.com/flashplayer/" target="_blank">Flash plugin</a>');
						$html .= '</div>';
					$html .= '</div>';
				$html .= '</div>';
			$html .= '</div>';
			if($record) {
				$html .= '<div id="'.$id.'-media-progress" class="progress fade hidden">';
					$html .= '<div class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">';
					$html .= '</div>';
				$html .= '</div>';
			}
		$html .= '</div>';
		return $html;
	}

	/**
	 * This is used to generate a timezone select field using the timezones available
	 * on the system. This will only show PHP supported timezones.
	 * @param  string $id   The name and id of the form field
	 * @param  string $value   The current value
	 * @param  string $nonelabel  What you want shown if nothing is chosen
	 * @return html input containing timezones
	 */

	function timezoneDrawSelect($id, $value='',$nonelabel=''){
		$nonelabel = !empty($nonelabel)?$nonelabel:_("Select a Timezone");
		$tzlist = array();
		$timezones = \DateTimeZone::listIdentifiers();
		foreach($timezones as $tz){
		  $parts = explode('/', $tz,2);
		  if(sizeof($parts) == 2){
		    $tzlist[$parts[0]][$parts[1]] = $tz;
		  }
		}
		$input = '<select name="'.$id.'" id="'.$id.'" class="form-control">';
		$input .= '<option value="">'.$nonelabel.'</option>';
		$selected = ('UTC' == $value)?'SELECTED':'';
		$input .= '<option value="UTC" '.$selected.'>'._("UTC").'</option>';
		foreach ($tzlist as $key => $val){
		  $input .= '<optgroup label="'.$key.'">';
		  foreach($val as $tzk => $tzv){
				$selected = ($tzv == $value)?'SELECTED':'';
		    $input .= '<option value = "'.$tzv.'" '.$selected.'>'.$tzk.'</option>';
		  }
		}
		$input .= '</select>';
		$input .= '<script type="text/javascript">';
		$input .= '$(document).ready(function() {';
	  $input .= '$("#'.$id.'").multiselect({enableCaseInsensitiveFiltering: true, inheritClass: true, onChange: function(element, checked) { $("#'.$id.'").trigger("onchange",[element, checked]) }});';
		$input .= '});';
		$input .= '</script>';
		return $input;
	}

	/**
	 * This is used to generate a timezone select field using the timezones available
	 * on the system. This will only show PHP supported timezones.
	 * @param  string $id   The name and id of the form field
	 * @param  string $value   The current value
	 * @param  string $nonelabel  What you want shown if nothing is chosen
	 * @return html input containing timezones
	 */

	function languageDrawSelect($id, $value='',$nonelabel=''){
		$nonelabel = !empty($nonelabel)?$nonelabel:_("Select a Language");
		$langlist = array();
		$langlist['en_US'] = function_exists('locale_get_display_name') ? locale_get_display_name('en_US', $this->getLocale()) : 'en_US';
		foreach(glob($this->freepbx->Config->get("AMPWEBROOT")."/admin/i18n/*",GLOB_ONLYDIR) as $langDir) {
			$lang = basename($langDir);
			$langlist[$lang] = function_exists('locale_get_display_name') ? locale_get_display_name($lang, $this->getLocale()) : $lang;
		}

		$input = '<select name="'.$id.'" id="'.$id.'" class="form-control">';
		$input .= '<option value="">'.$nonelabel.'</option>';
		foreach($langlist as $lang => $display) {
			$input .= '<option value="'.$lang.'" '.(($lang == $value) ? 'selected' : '').'>'.$display.'</option>';
		}
		$input .= '</select>';
		$input .= '<script type="text/javascript">';
		$input .= '$(document).ready(function() {';
		$input .= '$("#'.$id.'").multiselect({enableCaseInsensitiveFiltering: true, inheritClass: true, onChange: function(element, checked) { $("#'.$id.'").trigger("onchange",[element, checked]) }});';
		$input .= '});';
		$input .= '</script>';
		return $input;
	}

	/**
	 * Destination drawselects.
	 *
	 * This is where the magic happens. Query all modules for valid destinations
	 * Then build a javascript based multi-select box.
	 * Hide the second select box until the first is selected.
	 * Auto-populate the second based on the first.
	 *
	 * The first is almost always a module name, though it can be custom as well.
	 * The second is the actually destination
	 *
	 * @param  string $goto             The current goto destination setting. EG: ext-local,2000,1
	 * @param  int $i                   the destination set number (used when drawing multiple destination sets in a single form ie: digital receptionist)
	 * @param  array $restrict_modules  Array of modules or array of modules with ids to restrict getting destinations from
	 * @param  bool $table              Wrap this in a table row using <tr> and <td> (deprecated should not be used in 13+)
	 * @param  string $nodest_msg       No Destination selected message
	 * @param  bool $required           Whether the destination is required to be set
	 * @param  bool $output_array       Output an array instead of html (you will need to make sure the html is correct later on for the functionality of this to work correctly)
	 * @param  bool $reset              Reset the drawselect_* globals (useful when using multiple destination dropdowns on a page, each with their own restricted modules)
	 * @param  bool $disable            Set html element to disabled on creation
	 * @param  string $class            String of classes to add to to the html element (class="<string>")
	 * @return mixed                    Array if $output_array is true otherwise a string of html
	 */
	public function destinationDrawSelect($goto, $i, $restrict_modules=false, $table=true, $nodest_msg='', $required=false, $output_array=false, $reset=false, $disable=false, $class='') {
		global $fw_popover,$drawselect_destinations;

		if ($reset) {
			$this->drawselect_destinations = [];
			unset($drawselect_destinations);
		}

		//php session last_dest
		$fw_popover = isset($fw_popover) ? $fw_popover : FALSE;
		$disabled = ($disable) ? "disabled" : "";

		$html=$destmod=$errorclass=$errorstyle='';
		if ($nodest_msg == '') {
			$nodest_msg = '== '.\modgettext::_('choose one','amp').' ==';
		}

		$add_a_new = \modgettext::_('Add new %s &#133','amp');
		if(!isset($drawselect_destinations)){
			$drawselect_destinations = $this->freepbx->Destinations->getAll($i);
		}
		$this->drawselect_destinations[$i] = $drawselect_destinations;
		$flattened = [];
		foreach($this->drawselect_destinations[$i] as $mod => $categories) {
			foreach($categories as $cat => $data) {
				if(is_array($restrict_modules) && ((is_array($restrict_modules[$mod]) && !in_array($cat,$restrict_modules[$mod])) || (!is_array($restrict_modules[$mod]) && !in_array($cat,$restrict_modules)))) {
					continue;
				}
				foreach($data['destinations'] as $destination => $info) {
					$flattened[$destination] = !empty($info['category']) ? $info['category'] : $info['name'];
				}
			}
		}

		//get the destination module name if we have a $goto, add custom if there is an issue
		$destmod = '';
		if(!empty($goto)) {
			if(!isset($flattened[$goto])){ //if we haven't found a match, display error dest
				$destmod='Error';
				$this->drawselect_destinations[$i]['Error']['Error'] = [
					'name' => 'Error',
					'raw' => 'Error',
					'destinations' => [
						$goto => [
							'destination'=>$goto,
							'description'=>'Bad Dest: '.$goto,
							'class'=>'drawselect_error'
						]
					],
					'popover' => []
				];
			} else {
				$destmod = $flattened[$goto];
			}
			//Set 'data-last' values for popover return to last saved values
			$data_last_cat = str_replace(' ', '_', $destmod);
		} else {
			//Set 'data-last' values for popover return to nothing because this is a new 'route'
			$data_last_cat = '';
		}
		$cat_options = [];
		foreach($this->drawselect_destinations[$i] as $mod => $categories) {
			foreach($categories as $cat => $data) {
				if(is_array($restrict_modules) && ((is_array($restrict_modules[$mod]) && !in_array($cat,$restrict_modules[$mod])) || (!is_array($restrict_modules[$mod]) && !in_array($cat,$restrict_modules)))) {
					continue;
				}
				if(empty($cat_options[$data['name']])) {
					$tmp = [
						'selected' => ($data['raw']==$destmod)? true: false,
						'style' => ($mod=='Error')?'background-color:red;':'',
						'mod' => $mod,
						'cat' => $cat,
						'raw' => $data['raw'],
						'name_tag' => str_replace(' ', '_', $data['raw']) . $i,
						'destinations' => $data['destinations']
					];
				} else {
					$tmp = $cat_options[$data['name']];
					$tmp['destinations'] = array_merge($tmp['destinations'], $data['destinations']);
				}

				if(!empty($data['popover'])) {
					$tmp['popover'] = $data['popover'];
					$data['popover']['args']['display'] = $cat;
					$data['popover']['args']['view'] = isset($data['popover']['args']['view'])? $data['popover']['args']['view']:'form';
					$tmp['data_url'] = 'data-url="config.php?'.http_build_query($data['popover']['args']).'"';
				}

				$cat_options[$data['name']] = $tmp;
			}
		}

		if(!$output_array) {
			//wrap in table tags if requested
			if ($table) {
				$html.='<tr><td colspan=2>';
			}
			//draw "parent" select box
			$style=' style="'.(($destmod=='Error')?'background-color:red;':'').'"';
			$cat_html='<select data-last="'.$data_last_cat.'" name="goto' . $i . '" id="goto' . $i . '" class="form-control destdropdown ' . $class . '" ' . $style
					. ($required ? ' required ' : '') //html5 validation
					. ' data-id="' . $i . '" '
					. $disabled .'>';
			$cat_html.='<option value="" style="">'.$nodest_msg.'</option>';

			$dest_html = '';

			ksort($cat_options);
			foreach($cat_options as $name => $data) {
				$cat_html.='<option value="'.str_replace(' ','_',$data['raw']).'"'.($data['selected'] ? ' SELECTED ':'').$data['style'].'>'.$name.'</option>';

				$data_class = 'form-control destdropdown2 '.($data['mod'] === $data['cat'] ? $data['mod'] : $data['mod'].' '.$data['cat']).' '.(!$data['selected'] ? 'hidden':'');
				$dest_html.='<select data-class="'.$data['mod'].'" class="'.$data_class.'" ' . (isset($data['data_url']) ? $data['data_url'] : '') . ' data-mod="'.$data['mod'].'" data-last="'.(!empty($goto) ? $goto : '').'" name="' . $data['name_tag']
					. '" id="' . $data['name_tag'] . '" '. $style . ' data-id="' . $i . '" ' . $disabled . '>';
				foreach($data['destinations'] as $dest) {
					$selected=($goto==$dest['destination'])?'SELECTED ':' ';
					$dest_html.='<option value="'.$dest['destination'].'" '.$selected.$style.'>'.$dest['description'].'</option>';
				}
				if(!empty($data['popover'])) {
					$dest_html.='<option value="popover" style="">'.sprintf($add_a_new,$name).'</option>';
				}
				$dest_html.='</select>';
			}

			$cat_html.='</select>';

			$html.=$cat_html.$dest_html;

			if ($table) {
				$html.='</td></tr>';
			}
			return $html;
		} else {
			$array = [];
			foreach($cat_options as $name => $data) {
				foreach($data['destinations'] as $dest) {
					$array[$name][] = [
						"destination" => $dest['destination'],
						"description" => $dest['description'],
						"category" => $dest['category'],
						"id" => $dest['id'],
						"selected" => ($dest['destination']===$goto)
					];
				}
				if(!empty($data['popover'])) {
					$array[$name][99999] = [
						"destination" => 'popover',
						"description" => sprintf($add_a_new,$name),
						"category" => $data['cat'],
						"selected" => false
					];
				}
			}
			return $array;
		}

	}

	/**
	 * Draw a clock on the page
	 * @method drawClock
	 * @param  [type]    $time     [description]
	 * @param  [type]    $tz       [description]
	 * @param  [type]    $id       [description]
	 * @param  [type]    $label    [description]
	 * @param  [type]    $errormsg [description]
	 * @return [type]              [description]
	 */
	public function drawClock($time = null, $tz = null, $id = null, $label = null, $errormsg = null){
		$thisid = !empty($id)?$id:'clock'.mt_rand();
		$label = !empty($label)?$label:_("Server time:");
		$errormsg = !empty($errormsg)?$errormsg:_("Not received");
		$time = !empty($time)?$time:time();
		$tz = !empty($tz)?$tz:date("e");
		$html = '<span class="btn btn-default disabled">';
		$html .= '<b>'.$label.'</b> <span id="'.$thisid.'" data-time="'.$time.'" data-zone="'.$tz.'">'.$errormsg.'</span>';
		$html .= '</span>';
		$html .= '<script>';
		$html .= 'if($("#'.$thisid.'").length) {';
		$html .=	'var time = $("#'.$thisid.'").data("time");';
		$html .=	'var timezone = $("#'.$thisid.'").data("zone");';
		$html .=	'var updateTime = function() {';
		$html .=		'$("#'.$thisid.'").text(moment.unix(time).tz(timezone).format(\'HH:mm:ss z\'));';
		$html .=		'time = time + 1;';
		$html .=	'};';
		$html .=	'setInterval(updateTime,1000);';
		$html .= '}';
		$html .= '</script>';

		return $html;
	}

	/**
	 * Send this function a timestamp and it will generate:
	 * 		5 months ago
	 * @method humanDiff
	 * @param  string    $timestamp String timestamp
	 * @return string               The date. Ago or before
	 */
	public function humanDiff($timestamp) {
		return Carbon::createFromTimestamp($timestamp)->diffForHumans();
	}

	/**
	 * Send this function a DateTime Object and it will generate:
	 * 		5 months ago
	 * @method humanDiff
	 * @param  object    $ts        DateTime Object
	 * @return string               The date. Ago.
	 */
	public function humanDiffObject(\DateTime $dt) {
		return Carbon::instance($dt)->diffForHumans();
	}

	/**
	 * Generate Destination Usage Panel
	 * @method destinationUsage
	 * @param  mixed           $dest         an array of destinations to check against, or if boolean true then return list of all destinations in use
	 * @param  boolean          $module_hash a hash of module names to search for callbacks, otherwise global $active_modules is used
	 * @return string                        The finalized HTML
	 */
	public function destinationUsage($dest) {
		if (!is_array($dest)) {
			$dest = array($dest);
		}
		$usage_list = \FreePBX::Destinations()->getAllInUseDestinations($dest);
		$usage = array();
		$usage_item = '';
		if (!empty($usage_list)) {
			$usage_count = 0;
			foreach ($usage_list as $mod_list) {
				foreach ($mod_list as $details) {
					$usage_count++;
					$usage_items .= !empty($details['edit_url']) ? sprintf('<a href="%s">%s</a><br/>', htmlspecialchars($details['edit_url']), htmlspecialchars($details['description'])) : sprintf('%s<br/>', htmlspecialchars($details['description']));
				}
			}
		}
		if(!$usage_count) {
			return '';
		}
		$object = $usage_count > 1 ? _("Objects"):_("Object");
		$title = sprintf(dgettext('amp',"Used as Destination by %s %s"),$usage_count, dgettext('amp',$object));
		$title .= " <small>("._("Click to Expand").")</small>";
		$state = !empty($_COOKIE['destinationUsage']) ? 'in' : '';

			$html = <<<HTML
<div class="panel panel-default fpbx-usageinfo">
	<div class="panel-heading">
		<a data-toggle="collapse" data-target="#collapseOne">$title</a>
	</div>
	<div id="collapseOne" class="panel-collapse collapse $state">
		<div class="panel-body">
			$usage_items
		</div>
	</div>
</div>
HTML;
		return $html;
	}

	/** provide optional alert() box and formatted url info for extension conflicts
	 * @param array     an array of extensions that are in conflict obtained from framework_check_extension_usage
	 * @param boolean   default false. True if url and descriptions should be split, false to combine (see return)
	 * @param boolean   default true. True to echo an alert() box, false to bypass the alert box
	 * @return array    returns an array of formatted URLs with descriptions. If $split is true, retuns an array
	 *                  of the URLs with each element an array in the format of array('label' => 'description, 'url' => 'a url')
	 * @description     This is used upon detecting conflicting extension numbers to provide an optional alert box of the issue
	 *                  by a module which should abort the attempt to create the extension. It also returns an array of
	 *                  URLs that can be displayed by the module to show the conflicting extension(s) and links to edit
	 *                  them or further interogate. The resulting URLs are returned in an array either formatted for immediate
	 *                  display or split into a description and the raw URL to provide more fine grained control (or use with guielements).
	 */
	public function displayExtensionUsageAlert($usage_arr=array(),$split=false,$alert=true) {
		$url = array();
		if (!empty($usage_arr)) {
			$conflicts=0;
			foreach($usage_arr as $rawmodule => $properties) {
				foreach($properties as $exten => $details) {
					$conflicts++;
					if ($conflicts == 1) {
						switch ($details['status']) {
							case 'INUSE':
								$str = "Extension $exten not available, it is currently used by ".htmlspecialchars($details['description']).".";
								if ($split) {
									$url[] =  array('label' => "Edit: ".htmlspecialchars($details['description']),
									                 'url'  =>  $details['edit_url'],
									               );
								} else {
									$url[] =  "<a href='".$details['edit_url']."'>Edit: ".htmlspecialchars($details['description'])."</a>";
								}
								break;
							default:
							$str = "This extension is not available: ".htmlspecialchars($details['description']).".";
						}
					} else {
						if ($split) {
							$url[] =  array('label' => "Edit: ".htmlspecialchars($details['description']),
							                 'url'  =>  $details['edit_url'],
														 );
						} else {
							$url[] =  "<a href='".$details['edit_url']."'>Edit: ".htmlspecialchars($details['description'])."</a>";
						}
					}
				}
			}
			if ($conflicts > 1) {
				$str .= sprintf(" There are %s additonal conflicts not listed",$conflicts-1);
			}
		}
		if ($alert) {
			echo "<script>javascript:alert('$str')</script>";
		}
		return($url);
	}

	/**
	 * returns a list of URLs that represent a conflict with the past in extension or null if none
	 * @param string  extension number to check for conflicts
	 * @return mixed  returns a string with one or more URLs to the conflicting extesion(s) or null
	 */
	public function getConflictUrlHelper($account) {

		$usage_arr = FreePBX::Extensions()->checkUsage($account);
		if (!empty($usage_arr)) {
			$conflict_url = $this->displayExtensionUsageAlert($usage_arr, false, false);
			return implode('<br />',$conflict_url);
		} else {
			return null;
		}
	}

	/**
	 * Return the base locale that includes language and region only without
	 * anything else fancy, such as UTF8
	 *
	 * @param  String $locale
	 *
	 * @return String
	 */
	private function getBaseLocale($locale) {
		return str_replace('.utf8', '', $locale);
	}
}
