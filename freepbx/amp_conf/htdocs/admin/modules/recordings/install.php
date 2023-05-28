<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//for translation only
if (false) {
_("Recordings");
_("Save Recording");
_("Check Recording");
}

$fcc = new featurecode('recordings', 'record_save');
$fcc->delete();
unset($fcc);

$fcc = new featurecode('recordings', 'record_check');
$fcc->delete();
unset($fcc);
