<?php

/*  core_migrate.php
 *
 *  The purpose of this script is to remove all the core files that are being moved from the
 *  framework code to the core module. They are then auto-linked back to their proper place
 *  in retrieve_conf, however, the auto-linking will fail if there is already a file there by
 *  the same name.
 *
 *  In order to protect potentially modified files (especially configuratoin files that a user
 *  may have modified, we rename the file with a .0 (or higher) extension number instead of
 *  removing it.
 *
 */

global $asterisk_conf;

// File to migrate
//
$agibin_arr = array("dialparties.agi", "directory", "enumlookup.agi", "fixlocalprefix", "list-item-remove.php", "recordingcheck");
$bin_arr = array("fax-process.pl");
$etc_arr = array("extensions.conf", "iax.conf", "sip.conf");

$migrate_arr["agibin"] = $agibin_arr;
$migrate_arr["bin"] = $bin_arr;
$migrate_arr["etc"] = $etc_arr;

$index_arr["agibin"] = $asterisk_conf['astagidir'];
$index_arr["bin"] = $asterisk_conf['astvarlibdir']."/bin";
$index_arr["etc"] = $asterisk_conf['astetcdir'];

foreach ($migrate_arr as $dir_index => $files) {

	out("Renaming core files in: ".$index_arr[$dir_index]);
	foreach ($files as $file_item) {

		$file = $index_arr[$dir_index]."/".$file_item;
		$count=0;
		$max_count=1000;

		if (is_file($file) & !is_link($file)) {
			while (is_file($file.".".$count)) {
				debug($file.".".$count." already in use, trying again");
				$count++;
				if ($count > $max_count) {
					debug($file_item.": (ERROR - unable to find name");
					break;
				}
			}
			if ($count > $max_count || !rename($file,$file.".".$count)) {
				fatal($file.": Unable to rename and remove this file, proper functioning will be inhibitted");
			} else {
				out("\t".$file_item."..OK");
			}
		} else {
			out("\t".$file_item."..(no action needed)");
		}
	}
	out("Finished processing core files from: ".$index_arr[$dir_index]);
}

?>
