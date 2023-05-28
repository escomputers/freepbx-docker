<?php
/* $Id:$ */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

if(version_compare_freepbx(getversion(),"14","lt")) {
	        sql("DELETE FROM kvstore WHERE module = 'sipsettings'");
} else {
	        sql("DELETE FROM kvstore_Sipsettings");
}

sql("DROP TABLE `sipsettings`");
