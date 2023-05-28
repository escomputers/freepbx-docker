<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

$fcc = new featurecode('conferences', 'conf_status');
$fcc->setDescription('Conference Status');
$fcc->setDefault('*87');
$fcc->update();
unset($fcc);

$freepbx_conf = freepbx_conf::create();
$set['value'] = false;
$set['defaultval'] = $set['value'];
$set['category'] = 'Conferences';
$set['name'] = 'Force Allow Conference Recording';
$set['description'] = "Until Asterisk 14+ a random timestamp would be added to the end of the conference call recording which could not be determined in post call handling. Thus enabling conference call recording is disabled if using Asterisk 13 or lower. Enable this option to allow Conference Call recording to be enabled in Astrisk 13 or lower";
$set['readonly'] = 0;
$set['hidden'] = 0;
$set['level'] = 0;
$set['module'] = 'conferences';
$set['emptyok'] = 0;
$set['sortorder'] = -133;
$set['type'] = CONF_TYPE_BOOL;
$freepbx_conf->define_conf_setting('FORCEALLOWCONFRECORDING',$set);
$freepbx_conf->commit_conf_settings();
