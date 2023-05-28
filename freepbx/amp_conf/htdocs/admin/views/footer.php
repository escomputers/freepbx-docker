<?php
global $amp_conf;
global $module_name, $active_modules;
//For generating action buttons
include __DIR__ . '/../libraries/actionButton.class.php';
use FreePBX\libraries\actionButton;

$version	 = get_framework_version();
$version_tag = '?load_version=' . urlencode($version);
if ($amp_conf['FORCE_JS_CSS_IMG_DOWNLOAD']) {
  $this_time_append	= '.' . time();
  $version_tag 		.= $this_time_append;
} else {
	$this_time_append = '';
}

$html = '';

$html .= '</div>';//page_body
$html .= '</div>'; //page
$html .= '<div id="footer">';
// If displaying footer content, force the <hr /> tag to enforce clear separation of page vs. footer
if ($footer_content) {
	$html .= '<hr />';
}

//If we have the info... then we add the floating nav bar and the button for the user to click
if(!$covert && !empty($nav_bar)){
	$html .= '<div id="floating-nav-bar" class="col-xs-6"><button id="fixed-list-button" type="button" class="btn btn-primary"><i class="fa fa-list"></i></button><div class="floating-nav-bar-contents">'.$nav_bar.'</div></div>';
}

//Action Bar
if (!$covert && !empty($action_bar)) {
	$html .= '<div id="action-bar">';
  $html .= '<div id="action-buttons">';
  $html .= '<button id="action-bar-hide" class="btn"><i class="fa fa-angle-double-right"></i></button>';
	foreach($action_bar as $b){
		$temp = new actionButton();
		$temp->setParams($b);
		$html .= $temp->getHTML();
	}
	$html .= '</div>';
	$html .= '</div>';
}

$html .= '<div id="footer_content" class="row">';
$html .= $footer_content;
$html .= '</div>'; //footer_content
$html .= '</div>'; //footer
//add javascript

//localized strings and other javascript values that need to be set dynamically
//TODO: this should be done via callbacks so that all modules can hook in to it
$fpbx['conf'] = $amp_conf;
$clean = array(
		'AMPASTERISKUSER',
		'AMPASTERISKGROUP',
		'AMPASTERISKWEBGROUP',
		'AMPASTERISKWEBUSER',
		'AMPDBENGINE',
		'AMPDBHOST',
		'AMPDBNAME',
		'AMPDBPASS',
		'AMPDBUSER',
		'AMPDEVGROUP',
		'AMPDEVUSER',
		'AMPMGRPASS',
		'AMPMGRUSER',
		'AMPVMUMASK',
		'ARI_ADMIN_PASSWORD',
		'ARI_ADMIN_USERNAME',
		'ASTMANAGERHOST',
		'ASTMANAGERPORT',
		'ASTMANAGERPROXYPORT',
		'CDRDBHOST',
		'CDRDBNAME',
		'CDRDBPASS',
		'CDRDBPORT',
		'CDRDBTABLENAME',
		'CDRDBTYPE',
		'CDRDBUSER',
		'FOPPASSWORD',
		'FOPSORT',
);

foreach ($clean as $var) {
	if (isset($fpbx['conf'][$var])) {
		unset($fpbx['conf'][$var]);
	}
}

$modulef = module_functions::create();

$fpbx['conf']['text_dir']		= isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], array('he_IL'))
									? 'rtl' : 'ltr';
$fpbx['conf']['uniqueid']		= sql('SELECT data FROM module_xml WHERE id = "installid"', 'getOne');
$fpbx['conf']['dist']			= $modulef->_distro_id();
$fpbx['conf']['ver']			= get_framework_version();
$fpbx['conf']['reload_needed']  = $reload_needed;
$fpbx['conf']['brandid'] = $modulef->_brandid();
//TODO: This eventually should be provided by each individual module, rather than be hardcoded
$fpbx['conf']['modules']['sysadmin']['deployment_id'] = $modulef->_deploymentid();
$fpbx['conf']['modules']['sysadmin']['zendid'] = (function_exists('sysadmin_get_zendid'))?sysadmin_get_zendid():null;
$fpbx['conf']['modules']['sysadmin']['machineid'] = \FreePBX::Modules()->moduleHasMethod('sysadmin', 'getMachineId') ? \FreePBX::Sysadmin()->getMachineId() : null;
$fpbx['msg']['framework']['reload_unidentified_error'] = _(" error(s) occurred, you should view the notification log on the dashboard or main screen to check for more details.");
$fpbx['msg']['framework']['close'] = _("Close");
$fpbx['msg']['framework']['continuemsg'] = _("Continue");//continue is a resorved word!
$fpbx['msg']['framework']['cancel'] = _("Cancel");
$fpbx['msg']['framework']['retry'] = _("Retry");
$fpbx['msg']['framework']['update'] = _("Update");
$fpbx['msg']['framework']['save'] = _("Save");
$fpbx['msg']['framework']['bademail'] = _("Invalid email address");
$fpbx['msg']['framework']['updatenotifications'] = _("Update Notifications");
$fpbx['msg']['framework']['securityissue'] = _("Security Issue");
$fpbx['msg']['framework']['validation']['duplicate'] = _(" extension number already in use by: ");
$fpbx['msg']['framework']['validation']['delete'] = _("Are you sure you want to delete this?");
$fpbx['msg']['framework']['noupdates'] = _("Are you sure you want to disable automatic update notifications? This could leave your system at risk to serious security vulnerabilities. Enabling update notifications will NOT automatically install them but will make sure you are informed as soon as they are available.");
$fpbx['msg']['framework']['noupemail'] = _("Are you sure you don't want to provide an email address where update notifications will be sent. This email will never be transmitted off the PBX. It is used to send update and security notifications when they are detected.");
$fpbx['msg']['framework']['invalid_responce'] = _("Error: Did not receive valid response from server");
$fpbx['msg']['framework']['invalid_response'] = $fpbx['msg']['framework']['invalid_responce']; // TYPO ABOVE
$fpbx['msg']['framework']['validateSingleDestination']['required'] = _('Please select a "Destination"');
$fpbx['msg']['framework']['validateSingleDestination']['error'] = _('Custom Goto contexts must contain the string "custom-".  ie: custom-app,s,1');
$fpbx['msg']['framework']['weakSecret']['length'] = _("The secret must be at minimum six characters in length.");
$fpbx['msg']['framework']['weakSecret']['types'] = _("The secret must contain at least two numbers and two letters.");

if ($covert) {
	$fpbx['conf'] = array (
			'ASTVERSION' => '',
			'uniqueid' => '',
			'reload_needed' => '',
			'dist' => array(
				'pbx_type' => '',
				'pbx_version' => '')
			);
}

?>
<script>
var timezone = '<?php echo FreePBX::View()->getTimezone() ?>';
var language = '<?php echo FreePBX::View()->getLocale() ?>';
var UIDEFAULTLANG = '<?php echo FreePBX::Config()->get('UIDEFAULTLANG')?>';
var PHPTIMEZONE = '<?php echo FreePBX::Config()->get('PHPTIMEZONE')?>';
var datetimeformat = '<?php echo FreePBX::View()->getDateTimeFormat() ?>';
var dateformat = '<?php echo FreePBX::View()->getDateFormat() ?>';
var timeformat = '<?php echo FreePBX::View()->getTimeFormat() ?>';
</script>
<?php

$html .= "\n" . '<script type="text/javascript">'
		. 'var fpbx='
		. json_encode($fpbx)
		. ";\n"

		. 'var extmap='
		. $extmap

		. ';$(document).click();' //TODO: this should be cleaned up eventually as right now it prevents the nav bar from not being fully displayed
 		. '</script>';

// Production versions should include the packed consolidated javascript library but if it
// is not present (useful for development, then include each individual library below

foreach($compiled_scripts as $script) {
	$html .= '<script type="text/javascript" src="assets/js/'.$script . $version_tag . '"></script>';
}

if($lang != "en_US") {
  switch($lang) {
    case "es_ES":
      $html .= '<script src="assets/js/bootstrap-table-locale/bootstrap-table-es-SP.js'.$version_tag.'"></script>';
      $html .= "<script>$.extend($.fn.bootstrapTable.defaults, $.fn.bootstrapTable.locales['es-SP']);</script>";
    break;
    default:
      $html .= '<script src="assets/js/bootstrap-table-locale/bootstrap-table-'.str_replace("_","-",$lang).'.js'.$version_tag.'"></script>';
      $html .= "<script>$.extend($.fn.bootstrapTable.defaults, $.fn.bootstrapTable.locales['".str_replace("_","-",$lang)."']);</script>";
    break;
  }
}
$html .= "<script>moment.locale('".$lang."');</script>";
//Please see the BMO View class for more information about this
if(FreePBX::View()->replaceState()) {
  $html .= '<script>history.replaceState(null, null, "'.FreePBX::View()->getQueryString().'");</script>';
}
if ($amp_conf['BRAND_ALT_JS']) {
	$html .= '<script src="' . $amp_conf['BRAND_ALT_JS'] . $version_tag . '"></script>';
}

if (isset($module_name) && $module_name != '') {
	$html .= framework_include_js($module_name, $module_page);
}

if ($amp_conf['BROWSER_STATS']) {
	$ga = "<script>

			(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
			m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
			})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

			ga('create', 'UA-1862516-7', 'auto');

			ga('set', 'dimension1', fpbx.conf.dist.pbx_type);
			ga('set', 'dimension2', fpbx.conf.dist.pbx_version);
			ga('set', 'dimension3', fpbx.conf.ASTVERSION);
			ga('set', 'dimension4', fpbx.conf.ver);
			ga('set', 'dimension5', $.urlParam('display'));
			ga('set', 'dimension6', fpbx.conf.uniqueid);
			ga('send', 'pageview');

		</script>";

	$html .= str_replace(array("\t", "\n"), '', $ga);
}

if (!empty($js_content)) {
	$html .= $js_content;
}

//add IE specifc polyfills
if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
	//http://caniuse.com/#search=eventsource
	$html .= '<script src="assets/js/eventsource.min.js"></script>';
}

//TODO: This should move to a hook similar to framework_include_js
if(!empty($sysadmin)) {
  $html .= $sysadmin;
}

echo $html;
?>
<script type="text/javascript">
function addLoadEvent(func) {
  var oldonload = window.onload;
  if (typeof window.onload != 'function') {
    window.onload = func;
  } else {
    window.onload = function() {
      if (oldonload) {
        oldonload();
      }
      func();
    }
  }
}
//call plugin function after DOM ready
addLoadEvent(function(){
  outdatedBrowser({
    bgColor: '#f25648',
    color: '#ffffff',
    lowerThan: 'IE10',
    languagePath: ''
  })
});
</script>
<div id="outdated">
  <h6><?php echo _("Your browser is out-of-date!")?></h6>
  <p><?php echo sprintf(_("%s requires a new browser to function correctly. You can still use %s with the browser you currently have but your experience may be diminished and is not supported"),FreePBX::Config()->get("DASHBOARD_FREEPBX_BRAND"),FreePBX::Config()->get("DASHBOARD_FREEPBX_BRAND"))?><a id="btnUpdateBrowser" href="http://outdatedbrowser.com/"><?php echo _("Update my browser now")?></a></p>
  <p class="last"><a href="#" id="btnCloseUpdateBrowser" title="Close">&times;</a></p>
</div>
  <?php
  $consolealert = '
  <script>
  $(window.console).ready(function(){
    console.log(("%c%s"), "color: green; font-size: large","'. $amp_conf['DASHBOARD_FREEPBX_BRAND'].'");
    console.log(("Thankyou for using %s"),"'.$amp_conf['DASHBOARD_FREEPBX_BRAND'].'");
    ';
  if($amp_conf['DASHBOARD_FREEPBX_BRAND'] == 'FreePBX'){
  $consolealert .= '
    console.log("If you find bugs you may file a report at http://issues.freepbx.org");
    console.log("For developer resources visit: http://wiki.freepbx.org/x/BAAQ");
    ';
  }
  if(!empty($module_name) && isset($active_modules[$module_name])){
  $consolealert .='
    console.log(("Framework: %s"),"'. $version .'");
    console.log(("Module Name: %s"),"'. $module_name .'");
    console.log(("Module Version: %s"),"'. $active_modules[$module_name]["version"].'");
    ';
  }
  $consolealert .='
  });
  </script>';
  if (isset($_SESSION['AMP_user']) && !isset($_REQUEST['fw_popover'])){
    echo $consolealert;
  }
?>
</body>
</html>
