<?php

if($amp_conf["AMPDBENGINE"] != "sqlite3")  {
	outn("Alter table globals to increase field lengths.. ");
	$db->query("ALTER TABLE `globals` CHANGE `value` `value` varchar(255) NOT NULL");  
	$db->query("ALTER TABLE `globals` CHANGE `variable` `variable` varchar(255) NOT NULL");  
	out("Altered");
}

?>
