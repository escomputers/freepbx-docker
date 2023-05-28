<?php
$version			= get_framework_version();
$version_tag		= '?load_version=' . urlencode($version);
if ($amp_conf['FORCE_JS_CSS_IMG_DOWNLOAD']) {
	$this_time_append	= '.' . time();
	$version_tag 		.= $this_time_append;
} else {
	$this_time_append = '';
}

//Chrome Frame and IE sub fixes for IE when it doesnt support html5
if ((isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false))) {
	header('X-UA-Compatible: IE=edge,chrome=1');
}

//html head
$html = '';
$html .= '<!DOCTYPE html>'; //html5 functionality support
$html .= '<html class="firsttypeofselector">';
$html .= '<head>';
$html .= '<title>'
		. (isset($title) ? _($title) : $amp_conf['BRAND_TITLE'])
		. '</title>';

$html .= '<meta http-equiv="Content-Type" content="text/html;charset=utf-8">'
		. '<meta name="robots" content="noindex" />'
		. '<link rel="shortcut icon" href="' . $amp_conf['BRAND_IMAGE_FAVICON'] . '">';

//not supported in some browsers but will solve issues when switching from
//http to https
$html .= '<meta name="referrer" content="always">';

$html .= '<link href="assets/css/bootstrap-3.3.7.min.css'.$version_tag.'" rel="stylesheet" type="text/css">';
$html .= '<link href="assets/css/font-awesome.min-4.7.0.css'.$version_tag.'" rel="stylesheet" type="text/css">';
$html .= '<link href="assets/css/bootstrap-table-dev.css'.$version_tag.'" rel="stylesheet" type="text/css">';
$html .= '<link href="assets/css/bootstrap-table-reorder-rows.css'.$version_tag.'" rel="stylesheet" type="text/css">';
$html .= '<link href="assets/css/jquery-ui-1.10.3.custom.css'.$version_tag.'" rel="stylesheet" type="text/css">';
$html .= '<link href="assets/css/typehead.js-bootstrap3-0.2.3.css'.$version_tag.'" rel="stylesheet" type="text/css">';
$html .= '<link href="assets/css/notie.css'.$version_tag.'" rel="stylesheet" type="text/css">';
$html .= '<link href="assets/css/text-editor-1.2.1.css'.$version_tag.'" rel="stylesheet" type="text/css">';

//CSS First THEN JS (http://uxmovement.com/content/why-you-should-place-style-sheets-before-scripts/)
//less compiled into css
foreach($compiled_less_files as $file) {
	$html .= '<link href="assets/less/'.$file.'" rel="stylesheet" type="text/css">';
}

if(!empty($amp_conf['BRAND_CSS_ALT_MAINSTYLE'])) {
	$css_ver = '.' . filectime($amp_conf['BRAND_CSS_ALT_MAINSTYLE']);
	$html .= '<link href="' . $amp_conf['BRAND_CSS_ALT_MAINSTYLE'].$version_tag.$css_ver . '" rel="stylesheet" type="text/css">';
}

//$html .= '<link href="' . $amp_conf['JQUERY_CSS'] . $version_tag . '" rel="stylesheet" type="text/css">';

//add the popover.css stylesheet if we are displaying a popover to override mainstyle.css styling
if ($use_popover_css) {
	$popover_css = $amp_conf['BRAND_CSS_ALT_POPOVER'] ? $amp_conf['BRAND_CSS_ALT_POPOVER'] : 'assets/css/popover.css';
	$html .= '<link href="' . $popover_css.$version_tag . '" rel="stylesheet" type="text/css">';
}

// Insert a custom CSS sheet if specified (this can change what is in the main CSS)
if ($amp_conf['BRAND_CSS_CUSTOM']) {
	$html .= '<link href="' . $amp_conf['BRAND_CSS_CUSTOM'] . $version_tag . '" rel="stylesheet" type="text/css">';
}

//include rtl stylesheet if using a right to left langauge
if (isset($_SESSION['langdirection']) && ($_SESSION['langdirection'] == 'rtl')) {
	$html .= '<link href="assets/css/bootstrap-rtl.css'.$version_tag.'" rel="stylesheet" type="text/css" />';
	$html .= '<link href="assets/css/mainstyle-rtl.css'.$version_tag.'" rel="stylesheet" type="text/css" />';
}

$html .= '<link rel="stylesheet" href="assets/css/outdatedbrowser.min.css'.$version_tag.'">';
$html .= '<script type="text/javascript" src="assets/js/outdatedbrowser-1.1.3.min.js'.$version_tag.'"></script>';

//it seems extremely difficult to put jquery in the footer with the other scripts
$html .= '<script type="text/javascript" src="assets/js/jquery-3.1.1.min.js'.$version_tag.'"></script>';
$html .= '<script type="text/javascript" src="assets/js/selector-set-1.1.0.js'.$version_tag.'"></script>';
$html .= '<script type="text/javascript" src="assets/js/jquery.selector-set-0.2.2.js'.$version_tag.'"></script>';

// As we have code in the header acting as a class, this has to be up here.
$html .= '<script type="text/javascript" src="assets/js/class.js'.$version_tag.'"></script>';

$html .= '<script type="text/javascript" src="assets/js/jed-1.1.1.js' . $version_tag . '"></script>';

$html .= '<script type="text/javascript" src="assets/js/modgettext.js' . $version_tag . '"></script>';

if(isset($module_name) && !empty($module_name)) {
	$html .= '<script>textdomain("' . $module_name . '")</script>';
}

$html .= '<script type="text/javascript" src="assets/js/FreePBX.js' . $version_tag . '"></script>';

$html .= '<meta name="viewport" content="width=device-width,user-scalable=no,initial-scale=1,maximum-scale=1">
<meta name="apple-mobile-web-app-capable" content="yes" />
<link rel="apple-touch-icon" href="assets/images/badge.png" />
<link rel="apple-touch-icon-precomposed" href="assets/images/badge.png" />';

$html .= '</head>';

//open body
$html .= '<body>';

$html .= '<div id="page">';//open page

//add script warning
$html .= '<noscript><div class="attention">'
		. sprintf(_('WARNING: Javascript is disabled in your browser. The %s administration interface requires Javascript to run properly. Please enable javascript or switch to another  browser that supports it.'),FreePBX::Config()->get("DASHBOARD_FREEPBX_BRAND"))
		. '</div></noscript>';

echo $html;
