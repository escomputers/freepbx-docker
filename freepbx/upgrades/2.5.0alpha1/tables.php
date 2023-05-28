<?php

outn("Checking for Global var MIXMON_FORMAT..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='MIXMON_FORMAT'");
if (!$nrows) {
	$db->query("insert into globals values ('MIXMON_FORMAT', 'wav')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var MIXMON_DIR..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='MIXMON_DIR'");
if (!$nrows) {
	$db->query("insert into globals values ('MIXMON_DIR', '')");
	out("Created");
} else {
	out("Already exists!");
}

outn("Checking for Global var MIXMON_POST..");
$nrows = $db->getOne("SELECT count(*) from globals where variable='MIXMON_POST'");
if (!$nrows) {
	$db->query("insert into globals values ('MIXMON_POST', '')");
	out("Created");
} else {
	out("Already exists!");
}

?>
