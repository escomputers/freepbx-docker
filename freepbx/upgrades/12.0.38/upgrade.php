<?php
global $amp_conf;
include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/freepbx_conf.class.php');
$freepbx_conf =& freepbx_conf::create();

$settings = array(
	'BRAND_IMAGE_SPONSOR_FOOT' => 'images/sangoma-horizontal_thumb.png',
	'BRAND_SPONSOR_ALT_FOOT' => 'www.sangoma.com',
	'BRAND_IMAGE_SPONSOR_LINK_FOOT' => 'http://www.sangoma.com'
);

$oldSettings = array(
	'BRAND_IMAGE_SPONSOR_FOOT' => 'images/schmooze-logo.png',
	'BRAND_SPONSOR_ALT_FOOT' => 'www.schmoozecom.com',
	'BRAND_IMAGE_SPONSOR_LINK_FOOT' => 'http://www.schmoozecom.com'
);

foreach($settings as $keyword => $newValue) {
	if ($freepbx_conf->conf_setting_exists($keyword)) {
		$val = $freepbx_conf->get_conf_setting($keyword);
		$def = $freepbx_conf->get_conf_default_setting($keyword);
		if($def != $newValue) {
			$freepbx_conf->define_conf_setting($keyword,array('defaultval' => $newValue, 'value' => $val));
		}
		if($val == $oldSettings[$keyword]) {
			$freepbx_conf->set_conf_values(array($keyword => $newValue),false,true);
		}
	}
}
$freepbx_conf->commit_conf_settings();
