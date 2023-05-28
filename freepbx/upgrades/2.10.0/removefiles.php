<?php

global $amp_conf;

$outdated = array(
  $amp_conf['AMPBIN'].'/bounce_op.sh',
);

out("Cleaning up deprecated or moved files:");

foreach ($outdated as $file) {
	outn("Checking $file..");
	if (file_exists($file) && !is_link($file)) {
		unlink($file) ? out("removed") : out("failed to remove");
	} else {
		out("Not Required");
	}
}
?>
