<?php

global $amp_conf;

$outdated = array(
	$amp_conf['AMPWEBROOT']."/recordings/includes/zh_TW",
);

out("Cleaning up deprecated or moved files:");

foreach ($outdated as $file) {
	outn("Checking $file..");
	if (is_dir($file) && !is_link($file)) {
		exec("rm -rf $file", $out, $ret);
		$ret == 0 ? out("Removed") : out("Failed to Remove");
	} else {
		out("Not Required");
	}
}

?>
