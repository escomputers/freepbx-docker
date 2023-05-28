<?php

function frameworkPasswordCheck() {
	global $amp_conf, $db;

  $nt = notifications::create($db);

  // Moved most of the other checks to retrieve_conf to avoid running every page load. These have been left
  // here becuase both of these settings could be affected differently in the php apache related settings vs.
  // what retrieve_conf would see running the CLI version of php
  //

}

// setup locale
// old... don't use!!
function set_language($details=false) {
	return \FreePBX::View()->setLanguage(null,$details);
}

//
function fileRequestHandler($handler, $module = false, $file = false){
	if (empty($_SESSION['AMP_user']) || !is_object($_SESSION['AMP_user'])) {
		header("HTTP/1.0 500 Internal Server Error");
		die();
	}

	global $amp_conf;
	if (empty($_SESSION['AMP_user']) || !is_object($_SESSION['AMP_user'])) {
		header("HTTP/1.0 500 Internal Server Error");
		die();
	}

	switch ($handler) {
		case 'file':
			/** Handler to pass-through file requests
			 * Looks for "module" and "file" variables, strips .. and only allows normal filename characters.
			 * Accepts only files of the type listed in $allowed_exts below, and sends the corresponding mime-type,
			 * and always interprets files through the PHP interpreter. (Most of?) the freepbx environment is available,
			 * including $db and $astman, and the user is authenticated.
			 */
			if (!$module || !$file) {
				die_freepbx("unknown");
			}
			//TODO: this could probably be more efficient
			$module = str_replace('..','.', preg_replace('/[^a-zA-Z0-9-\_\.]/','',$module));
			$file = str_replace('..','.', preg_replace('/[^a-zA-Z0-9-\_\.]/','',$file));

			$allowed_exts = array(
				'.js'		=> 'text/javascript',
				'.js.php'	=> 'text/javascript',
				'.css'		=> 'text/css',
				'.css.php'	=> 'text/css',
				'.html.php'	=> 'text/html',
				'.php'		=> 'text/html',
				'.jpg.php'	=> 'image/jpeg',
				'.jpeg.php'	=> 'image/jpeg',
				'.png.php'	=> 'image/png',
				'.gif.php'	=> 'image/gif',
			);
			foreach ($allowed_exts as $ext=>$mimetype) {
				if (substr($file, -1*strlen($ext)) == $ext) {
					$fullpath = 'modules/'.$module.'/'.$file;
					if (file_exists($fullpath)) {
						// file exists, and is allowed extension

						// image, css, js types - set Expires to 24hrs in advance so the client does
						// not keep checking for them. Replace from header.php
						if (!$amp_conf['DEVEL']) {
							header('Expires: '.gmdate('D, d M Y H:i:s', time() + 86400).' GMT', true);
							header('Cache-Control: max-age=86400, public, must-revalidate',true);
						}
						header("Content-type: ".$mimetype);
						ob_start();
						include($fullpath);
						ob_end_flush();
						exit();
					} else {
						header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
						exit();
					}
					break;
				}
			}
			die_freepbx("../view/not allowed","Trying to access file: ".'modules/'.$module.'/'.$file);
		break;
    case 'api':
			// Any functions you want to access using the api handler, needs to be added to the array below
			// NOTE: If you add any functions to this, check to make sure we don't have to do some
			//       safety check on the arg to avoid an indirect inejection
			// Re: FREEPBX-7117
			$allowed_functions = array(
				'framework_get_conflict_url_helper' => 'framework_get_conflict_url_helper',
			);

			$function_key = $_REQUEST['function'];

			//FREEPBX-7117 - Allow certain php functions and verify we have auth prior
			if (!isset($allowed_functions[$function_key]) || !defined('FREEPBX_IS_AUTH')) {
				die_freepbx("../view/not allowed","Trying to access function: ".$function_key);
			}

      if (function_exists($allowed_functions[$function_key])) {

				$args = isset($_REQUEST['args'])?$_REQUEST['args']:'';

        //currently works for one arg functions, eventually need to clean this up to except more args
				$result = call_user_func($allowed_functions[$function_key], $args);

        $jr = json_encode($result);
      } else {
        $jr = json_encode(null);
      }
      header("Content-type: application/json");
      echo $jr;
    break;
	}
	exit();
}

/**
 * Load View
 *
 * This function is used to load a "view" file. It has two parameters:
 *
 * 1. The name of the "view" file to be included.
 * 2. An associative array of data to be extracted for use in the view.
 *
 * NOTE: you cannot use the variable $view_filename_protected in your views!
 *
 * @param	string
 * @param	array
 * @return	string
 *
 */
function load_view($view_filename_protected, $vars = array()) {

	//return false if we cant find the file or if we cant open it
	if (!$view_filename_protected || !file_exists($view_filename_protected) || !is_readable($view_filename_protected) ) {
		dbug('load_view failed to load view for inclusion:', $view_filename_protected);
		return false;
	}

	// Import the view variables to local namespace
	extract( (array) $vars, EXTR_SKIP);

	// Capture the view output
	ob_start();

	// Load the view within the current scope
	include($view_filename_protected);

	// Get the captured output
	$buffer = ob_get_contents();

	//Flush & close the buffer
	ob_end_clean();

	//Return captured output
	return $buffer;
}

/**
 * Show View
 *
 * This function is used to show a "view" file. It has two parameters:
 *
 * 1. The name of the "view" file to be included.
 * 2. An associative array of data to be extracted for use in the view.
 *
 * This simply echos the output of load_view() if not false.
 *
 * NOTE: you cannot use the variable $view_filename_protected in your views!
 *
 * @param	string
 * @param	array
 * @return	string
 *
 */
function show_view($view_filename_protected, $vars = array()) {
  $buffer = load_view($view_filename_protected, $vars);
  if ($buffer !== false) {
    echo $buffer;
  }
}

/** Abort all output, and redirect the browser's location.
 *
 * Useful for returning to the user to a GET location immediately after doing
 * a successful POST operation. This avoids the "this page was sent via POST, resubmit?"
 * message in the users browser, and also overwrites the POST page as a location in
 * the browser's URL history (eg, they can't press the back button and end up re-submitting
 * the page).
 *
 * @param string   The url to go to
 * @param bool     If execution should stop after the function. Defaults to true
 */
function redirect($url, $stop_processing = true) {
	// TODO: If I don't call ob_end_clean() then is output buffering still on? Do I need to run it off still?
	//       (note ob_end_flush() results in the same php NOTICE so not sure how to turn it off. (?ob_implicit_flush(true)?)
	//
	if (!empty($res)) {
		@ob_end_clean();
	}
	//ob_end_clean();
	//
	if ($stop_processing) {
		@header('Location: '.$url);
		exit;
	} else {
		global $currentcomponent;
		$currentcomponent->setRedirectURL($url);
	}
}

/** Abort all output, and redirect the browser's location using standard
 * FreePBX user interface variables. By default, will take POST/GET variables
 * 'type' and 'display' and pass them along in the URL.
 * Also accepts a variable number of parameters, each being the name of a variable
 * to pass on.
 *
 * For example, calling redirect_standard('extdisplay','test'); will take $_REQUEST['type'],
 * $_REQUEST['display'], $_REQUEST['extdisplay'], and $_REQUEST['test'],
 * and if any are present, use them to build a GET string (eg, "config.php?type=setup&
 * display=somemodule&extdisplay=53&test=yes", which is then passed to redirect() to send the browser
 * there.
 *
 * redirect_standard_continue does exactly the same thing but does NOT abort processing. This
 * is used when you wish to do a redirect but there is a possibility of other hooks still needing
 * to continue processing. Note that this is used in core when in 'extensions' mode, as both the
 * users and devices modules need to hook into it together.
 *
 * @param string  (optional, variable number) The name of a variable from $_REQUEST to
 *                pass on to a GET URL.
 *
 */
function redirect_standard( /* Note. Read the next line. Variable No of Params */ ) {
	$args = func_get_Args();
	$url = _redirect_standard_helper($args);
	redirect($url);
}

function redirect_standard_continue( /* Note. Read the next line. Varaible No of Params */ ) {
	$args = func_get_Args();
	$url = _redirect_standard_helper($args);
	redirect($url, false);
}

function _redirect_standard_helper($args) {
	global $module_name, $fw_popover_process;
	$getdest = $module_name . '_getdest';

	// if processing a popOver postback and the module has not explicitly set the destination, try to
	// derive it here. This keeps most modules from having to do an explicit call to set_dest()
	//
	if ($fw_popover_process && !empty($args) && !fwmsg::is_dest_set() && $module_name && function_exists($getdest)) {
		foreach (array_intersect($args, array('extdisplay', 'id', 'account','itemid','extension')) as $arg) {
			if (isset($_REQUEST[$arg]) && trim($_REQUEST[$arg]) != '') {
				$dest = $getdest($_REQUEST[$arg]);
				fwmsg::set_dest($dest[0]);
				break;
			}
		}
	}
	foreach (array_merge(array('type','display','fw_popover_process'),$args) as $arg) {
		if (isset($_REQUEST[$arg])) {
			$urlopts[] = $arg.'='.urlencode($_REQUEST[$arg]);
		}
	}
	$url = 'config.php?'.implode('&',$urlopts);
	return $url;
}

function framework_include_css() {
	global $active_modules, $module_name, $module_page, $amp_conf;

	$version			= get_framework_version();
	$version_tag		= '?load_version=' . urlencode($version);
	if ($amp_conf['FORCE_JS_CSS_IMG_DOWNLOAD']) {
	  $this_time_append	= '.' . time();
	  $version_tag 		.= $this_time_append;
	} else {
		$this_time_append = '';
	}

	$html = '';
	$view_module_version	= isset($active_modules[$module_name]['version'])
							? $active_modules[$module_name]['version']
							: $version_tag;
	$mod_version_tag		= '&load_version=' . urlencode($view_module_version);
	if ($amp_conf['FORCE_JS_CSS_IMG_DOWNLOAD']) {
		$mod_version_tag	.= $this_time_append;
	}

	// DEPECRATED but still supported for a while, the assets directory is the new preferred mode
	if (is_file('modules/' . $module_name . '/' . $module_name . '.css')) {
		$html .= '<link href="?handler=file&amp;module=' . $module_name
				. '&amp;file=' . $module_name . '.css' . $mod_version_tag
				. '" rel="stylesheet" type="text/css" />';
	}
	if (isset($module_page)
		&& ($module_page != $module_name)
		&& is_file('modules/' . $module_name . '/' . $module_page . '.css')
	) {
			$html .= '<link href="?handler=file&amp;module=' . $module_name
					. '&amp;file=' . $module_page . '.css' . $mod_version_tag
					. '" rel="stylesheet" type="text/css" />';
	}


	// Check assets/css and then assets/css/page_name for any css files which will have been symlinked to
	// assets/module_name/css/*
	$css_dir = 'modules/' . $module_name . '/assets/css';
	if (is_dir($css_dir)) {
		$d = opendir($css_dir);
		$file_list = array();
		while ($file = readdir($d)) {
			$file_list[] = $file;
		}
		sort($file_list);
		foreach ($file_list as $file) {
			if (substr($file,-4) == '.css' && is_file($css_dir . '/' . $file)) {
			  $html .= '<link href="assets/' . $module_name . '/css/' . $file
						. '" rel="stylesheet" type="text/css" />';
			}
		}
		unset($file_list);
		$css_subdir = $css_dir . '/' . $module_page;
		if ($module_page != '' && is_dir($css_subdir)) {
			$sd = opendir($css_subdir);

			$file_list = array();
			while ($p_file = readdir($sd)) {
				$file_list[] = $p_file;
			}
			sort($file_list);
			foreach ($file_list as $p_file) {
				if (substr($p_file,-4) == '.css' && is_file($css_subdir . '/' . $p_file)) {
					$html .= '<link href="assets/' . $module_name . '/css/' . $module_page . '/' . $p_file . '" rel="stylesheet" type="text/css" />';
				}
			}
		}
	}

	$files = FreePBX::create()->Less->generateModuleStyles($module_name, $module_page);
	if(!empty($files)) {
		$html .= '<link href="assets/' . $module_name . '/less/' . $files[0] . '" rel="stylesheet" type="text/css" />';
	}
	return $html;
}

function framework_include_js($module_name, $module_page) {
	global $amp_conf, $active_modules;
	$mod_data = FreePBX::Modules()->getInfo($module_name);
	$version = !empty($mod_data[$module_name]['version']) ? $mod_data[$module_name]['version'] : '';
	$version_tag		= 'load_version=' . urlencode($version);
	if ($amp_conf['FORCE_JS_CSS_IMG_DOWNLOAD']) {
	  $this_time_append	= '.' . time();
	  $version_tag 		.= $this_time_append;
	} else {
		$this_time_append = '';
	}

	$html = '';

	if (is_file('modules/' . $module_name . '/' . $module_name . '.js')) {
		$html .= '<script type="text/javascript" src="?handler=file&amp;module='
				. $module_name . '&amp;file=' . $module_name . '.js'
				. '&amp;'.$version_tag . '"></script>';
	}
	if (isset($module_page)
		&& ($module_page != $module_name)
		&& is_file('modules/' . $module_name . '/' . $module_page . '.js')
	) {
		$html .= '<script type="text/javascript" src="?handler=file&amp;module='
				. $module_name . '&amp;file=' . $module_page . '.js'
				. '&amp;'.$version_tag . '"></script>';
	}

	// Check assets/js and then assets/js/page_name for any js files which will have been symlinked to
	// assets/module_name/js/*
	//
	$js_dir = 'modules/' . $module_name . '/assets/js';
	if (is_dir($js_dir)) {
		$file_list = scandir($js_dir);
		foreach ($file_list as $file) {
			if (substr($file,-3) == '.js' && is_file("$js_dir/$file")) {
				$html .= '<script type="text/javascript"'
						. ' src="assets/' . $module_name . '/js/' . $file . '?'.$version_tag.'"></script>';
			}
		}
		unset($file_list);
		$js_subdir ="$js_dir/$module_page";
		if ($module_page != '' && is_dir($js_subdir)) {
			$file_list = scandir($js_subdir);
			foreach ($file_list as $p_file) {
				if (substr($p_file,-3) == '.js' && is_file("$js_subdir/$p_file")) {
				  $html .= '<script type="text/javascript" '
							. ' src="assets/' . $module_name . '/js/'
							. $module_page . '/' . $p_file
							. '?'.$version_tag.'"></script>';
				}
			}
		}
  }

	// DEPCRETATED but still supported:
	// Note - include all the module js files first, then the page specific files,
	//in case a page specific file requires a module level file
	$js_dir = "modules/$module_name/js";
	if (is_dir($js_dir)) {
		$file_list = scandir($js_dir);
		foreach ($file_list as $file) {
			if (substr($file,-3) == '.js' && is_file("$js_dir/$file")) {
				$html .= '<script type="text/javascript"'
				. ' src="?handler=file&module='
				. $module_name . '&file='
				. $js_dir . '/' . $file . $mod_version_tag
				. '"></script>';
			}
		}
		unset($file_list);
		$js_subdir ="$js_dir/$module_page";
		if ($module_page != '' && is_dir($js_subdir)) {
			$sd = opendir($js_subdir);

			$file_list = array();
			while ($p_file = readdir($sd)) {
				$file_list[] = $p_file;
			}
			sort($file_list);
			foreach ($file_list as $p_file) {
				if (substr($p_file,-3) == '.js' && is_file("$js_subdir/$p_file")) {
					$html .= '<script type="text/javascript" src="?handler=file&module='
							. $module_name . '&file='
							. $js_subdir . '/' . $p_file . $mod_version_tag
							. '"></script>';
				}
			}
		}
	}

	return $html;

}


function framework_server_name() {
	global $amp_conf;
	if ($amp_conf['SERVERINTITLE']) {
		$title = trim(gethostname()) ." (".$_SERVER['SERVER_NAME'].") - ".$amp_conf['BRAND_TITLE'];
	} else {
		$title = $amp_conf['BRAND_TITLE'];
	}

	return $title;
}
function show_help($message, $title='', $collapse=false, $collapseable=true, $class='default') {
	$thisid = uniqid();
	$html = "<div class='panel panel-$class panel-help'>";
	$html .= sprintf('<div class="panel-heading %s" data-target="#%s" data-toggle="%s">', ((! $collapse) ? 'collapsed' : ''), $thisid,  (($collapseable) ? 'collapse' : '' ) );
	$html .= '<h3 class="panel-title">';
	$html .= '<span class="pull-left"><i class="fa fa-info-circle fa-lg fa-fw"></i></span>';
	$html .= ($title) ? $title : _("Helpful Information");
	if ($collapseable) {
		$html .= '<span class="pull-right">';
		$html .= '<i class="chevron fa fa-fw"></i>';
		$html .= '</span>';
	}
	$html .= '</h3>';
	$html .= '</div>';
	$html .= sprintf('<div id="%s" class="panel-collapse collapse %s">', $thisid, ( $collapse ? 'in' : '' ));
	$html .= '<div class="panel-body">';
	$html .= $message;
	$html .= '</div>';
	$html .= '</div>';
	$html .= '</div>';
	return $html;
}
function show_deprecated($message="", $dismisable = true, $logit = false){
	if($dismisable){
		$html = '<div class="alert alert-warning alert-dismissable hidden depnotice" data-for="dep">';
		$html .= '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>';
  }else{
		$html = '<div class="alert alert-warning hidden">';
	}
	$html .= '<h3><i class="fa fa-warning"></i> '._("This Module has been deprecated").'</h3>';
  $html .= $message;
	$html .= '</div>';
	$html .= '<script>';
	$html .= '$( document ).ready(function() {';
	$html .= 'if($.cookie(res[2]+"depdis") != "1"){$(".depnotice").each(function(){$(this).removeClass("hidden")})}';
	$html .= '$(".alert").bind("closed.bs.alert", function(){if($(this).data("for") == "dep"){$.cookie(res[2]+"depdis", "1")}});';
	$html .= '});';
	$html .= '</script>';

	if($logit){
		freepbx_log($message);
		dbug($message);
	}
	return $html;
}
