<?php

global $amp_conf;

$htdocs = $amp_conf['AMPWEBROOT']."/admin/modules/framework/htdocs";
$bin = $amp_conf['AMPWEBROOT']."/admin/modules/framework/bin";

// Include the file that will end up in framework also so it doesn't get
// copied back
//
$outdated = array(
	$amp_conf['AMPWEBROOT']."/recordings/modules/help.module",
	"$htdocs/recordings/modules/help.module",
	$amp_conf['AMPWEBROOT']."/admin/bounce_op.sh",
	"$htdocs/admin/bounce_op.sh",
	$amp_conf['AMPWEBROOT']."/admin/logout.php",
	"$htdocs/admin/logout.php",
	$amp_conf['AMPWEBROOT']."/admin/footer.php",
	"$htdocs/admin/footer.php",
	$amp_conf['AMPWEBROOT']."/admin/images/background-grid.png",
	"$htdocs/admin/images/background-grid.png",
	$amp_conf['AMPWEBROOT']."/admin/images/background-triangle.png",
	"$htdocs/admin/images/background-triangle.png",
	$amp_conf['AMPWEBROOT']."/admin/common/jquery.js",
	"$htdocs/admin/common/jquery.js",
	$amp_conf['AMPWEBROOT']."/admin/common/jquery.tabs.js",
	"$htdocs/admin/common/jquery.tabs.js",
	$amp_conf['AMPWEBROOT']."/admin/common/freepbx.css",
	"$htdocs/admin/common/freepbx.css",
	$amp_conf['AMPWEBROOT']."/admin/common/jquery.interface.js",
	"$htdocs/admin/common/jquery.interface.js",
	$amp_conf['AMPWEBROOT']."/admin/common/jquery.tabs.css",
	"$htdocs/admin/common/jquery.tabs.css",
	$amp_conf['AMPBIN']."/retrieve_queues_conf_from_mysql.pl",
	"$bin/retrieve_queues_conf_from_mysql.pl",
	$amp_conf['AMPBIN']."/retrieve_zap_conf_from_mysql.pl",
	"$bin/retrieve_zap_conf_from_mysql.pl",
	$amp_conf['AMPBIN']."/retrieve_sip_conf_from_mysql.pl",
	"$bin/retrieve_sip_conf_from_mysql.pl",
	$amp_conf['AMPBIN']."/retrieve_iax_conf_from_mysql.pl",
	"$bin/retrieve_iax_conf_from_mysql.pl",
);

out("Cleaning up deprecated or moved files:");

foreach ($outdated as $file) {
	outn("Checking $file..");
	if (file_exists($file) && !is_link($file)) {
		unlink($file) ? out("Removed") : out("Failed to Remove");
	} else {
		out("Not Required");
	}
}

?>
