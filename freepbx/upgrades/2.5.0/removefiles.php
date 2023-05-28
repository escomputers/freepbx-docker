<?php

global $amp_conf;

$outdated = array(
	$amp_conf['AMPWEBROOT']."/admin/cdr/css",
	$amp_conf['AMPWEBROOT']."/admin/cdr/encrypt.js",
	$amp_conf['AMPWEBROOT']."/admin/cdr/graph_hourdetail.php",
	$amp_conf['AMPWEBROOT']."/admin/cdr/graph_pie.php",
	$amp_conf['AMPWEBROOT']."/admin/cdr/graph_stat.php",
	$amp_conf['AMPWEBROOT']."/admin/cdr/graph_statbar.php",
	$amp_conf['AMPWEBROOT']."/admin/cdr/images/print.css",
);

out("Cleaning up deprecated or moved files:");

foreach ($outdated as $file) {
	outn("Checking $file..");
	if (!is_dir($file)) {
		if (file_exists($file) && !is_link($file)) {
			unlink($file) ? out(_("Removed")) : out(_("Failed to Remove"));
		} else {
			out(_("Not Required"));
		}
	} else {
		exec("rm -rf $file",$outarr,$ret);
		if ($ret == 0) {
			out(_("Removed directory"));
		} else {
			out(_("Failed to Remove directory"));
		}
	}
}

?>
