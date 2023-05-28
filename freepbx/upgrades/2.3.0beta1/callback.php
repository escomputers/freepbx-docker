<?php

global $asterisk_conf;
out("Cleaning up leftover callback script that was distributed in core...");
outn("callback...");
if (file_exists($asterisk_conf['astvarlibdir']."/bin/callback") && !is_link($asterisk_conf['astvarlibdir']."/bin/callback")) {
	unlink($asterisk_conf['astvarlibdir']."/bin/callback");
	out("Done");
} else {
	out("Not Required");
}

?>
