<?php

global $asterisk_conf;
out("Cleaning up leftover backup scripts that were distributed in core...");
outn("ampbackup.pl...");
if (file_exists($asterisk_conf['astvarlibdir']."/bin/ampbackup.pl") && !is_link($asterisk_conf['astvarlibdir']."/bin/ampbackup.pl")) {
	unlink($asterisk_conf['astvarlibdir']."/bin/ampbackup.pl");
	out("Done");
} else {
	out("Not Required");
}
outn("retrieve_backup_cron_from_mysql.pl...");
if (file_exists($asterisk_conf['astvarlibdir']."/bin/retrieve_backup_cron_from_mysql.pl") && !is_link($asterisk_conf['astvarlibdir']."/bin/retrieve_backup_cron_from_mysql.pl")) {
	unlink($asterisk_conf['astvarlibdir']."/bin/retrieve_backup_cron_from_mysql.pl");
	out("Done");
} else {
	out("Not Required");
}
?>
