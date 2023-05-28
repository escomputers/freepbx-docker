<?php
global $amp_conf;
global $db;

if($amp_conf["AMPDBENGINE"] != "sqlite3")  {
	outn("Alter table incomming to increase extension lengths.. ");
	$result = $db->query("ALTER TABLE `incoming` CHANGE `extension` `extension` varchar(50) NOT NULL");  
  if (DB::IsError($result)) {
	  out("ERROR ALTER TABLE FAILED");
  } else {
	  out("Altered");
  }
} else {
	out("WARNING: column extension in incoming table has NOT been altered to 50 from 20 and will need to be addressed manually");
}
?>
