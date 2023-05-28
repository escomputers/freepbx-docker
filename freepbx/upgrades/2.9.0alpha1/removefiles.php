<?php

global $amp_conf;

$htdocs = $amp_conf['AMPWEBROOT']."/admin/modules/framework/htdocs";
$bin = $amp_conf['AMPWEBROOT']."/admin/modules/framework/bin";

// Include the file that will end up in framework also so it doesn't get
// copied back
//
$outdated = array(
	$amp_conf['AMPWEBROOT']."/admin/cdr/about.php",
	"$htdocs/admin/cdr/about.php",
	$amp_conf['AMPWEBROOT']."/admin/common/content.css",
	"$htdocs/admin/common/content.css",
	$amp_conf['AMPWEBROOT']."/admin/common/docbook.css",
	"$htdocs/admin/common/docbook.css",
	$amp_conf['AMPWEBROOT']."/admin/common/encrypt.js",
	"$htdocs/admin/common/encrypt.js",
	$amp_conf['AMPWEBROOT']."/admin/common/graph_hourdetail.php",
	"$htdocs/admin/common/graph_hourdetail.php",
	$amp_conf['AMPWEBROOT']."/admin/common/graph_pie.phps",
	"$htdocs/admin/common/graph_pie.php",
	$amp_conf['AMPWEBROOT']."/admin/common/graph_statbar.php",
	"$htdocs/admin/common/graph_statbar.php",
	$amp_conf['AMPWEBROOT']."/admin/common/graph_stat.php",
	"$htdocs/admin/common/graph_stat.php",
	$amp_conf['AMPWEBROOT']."/admin/common/ie.css",
	"$htdocs/admin/common/ie.css",
	$amp_conf['AMPWEBROOT']."/admin/common/interface.dim.js",
	"$htdocs/admin/common/interface.dim.js",
	$amp_conf['AMPWEBROOT']."/admin/common/jquery-1.4.2.js",
	"$htdocs/admin/common/jquery-1.4.2.js",
	$amp_conf['AMPWEBROOT']."/admin/common/jquery.cookie.js",
	"$htdocs/admin/common/jquery.cookie.js",
	$amp_conf['AMPWEBROOT']."/admin/common/jquery.dimensions.js",
	"$htdocs/admin/common/jquery.dimensions.js",
	$amp_conf['AMPWEBROOT']."/admin/common/jquery.toggleval.3.0.js",
	"$htdocs/admin/common/jquery.toggleval.3.0.jss",
	$amp_conf['AMPWEBROOT']."/admin/common/jquery-ui-1.8.custom.min.jss",
	"$htdocs/admin/common/jquery-ui-1.8.custom.min.js",
	$amp_conf['AMPWEBROOT']."/admin/common/layout.css",
	"$htdocs/admin/common/layout.css",
	$amp_conf['AMPWEBROOT']."/admin/common/php-asmanager.php",
	"$htdocs/admin/common/php-asmanager.php",
	$amp_conf['AMPWEBROOT']."/admin/common/print.css",
	"$htdocs/admin/common/print.css",
	$amp_conf['AMPWEBROOT']."/admin/common/script.legacy.js",
	"$htdocs/admin/common/script.legacy.js",
	$amp_conf['AMPWEBROOT']."/admin/common/tabber-minimized.js",
	"$htdocs/admin/common/tabber-minimized.js",
	$amp_conf['AMPWEBROOT']."/admin/components.class.php",
	"$htdocs/admin/components.class.php",
	$amp_conf['AMPWEBROOT']."/admin/extensions.class.php",
	"$htdocs/admin/extensions.class.php",
	$amp_conf['AMPWEBROOT']."/admin/favicon.ico",
	"$htdocs/admin/favicon.ico",
	$amp_conf['AMPWEBROOT']."/admin/featurecodes.class.php",
	"$htdocs/admin/featurecodes.class.php",
	$amp_conf['AMPWEBROOT']."/admin/header_auth.php",
	"$htdocs/admin/header_auth.php",
	$amp_conf['AMPWEBROOT']."/admin/header.php",
	"$htdocs/admin/header.php"
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
