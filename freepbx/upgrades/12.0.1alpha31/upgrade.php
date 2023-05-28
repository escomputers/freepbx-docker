<?php
global $amp_conf;
include_once ($amp_conf['AMPWEBROOT'].'/admin/libraries/freepbx_conf.class.php');
$freepbx_conf =& freepbx_conf::create();

//Depreciated advanced settings that no longer apply FREEPBX-7119
$set = array();
$set['hidden'] = 1;
$set['value'] = true;
$set['defaultval'] = true;
if ($freepbx_conf->conf_setting_exists('USEDEVSTATE')) {
	$freepbx_conf->set_conf_values(array('USEDEVSTATE' => true), true);
    $freepbx_conf->define_conf_setting('USEDEVSTATE',$set,true);
}

if ($freepbx_conf->conf_setting_exists('USEQUEUESTATE')) {
	$freepbx_conf->set_conf_values(array('USEQUEUESTATE' => true), true);
	$freepbx_conf->define_conf_setting('USEQUEUESTATE',$set,true);
}

if ($freepbx_conf->conf_setting_exists('QUEUES_UPDATECDR')) {
	$freepbx_conf->set_conf_values(array('QUEUES_UPDATECDR' => true), true);
	$freepbx_conf->define_conf_setting('QUEUES_UPDATECDR',$set,true);
}