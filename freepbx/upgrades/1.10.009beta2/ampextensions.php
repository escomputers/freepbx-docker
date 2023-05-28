<?php 
//add AMPEXTENSIONS option to amportal.conf

if (!array_key_exists("AMPEXTENSIONS",$amp_conf)) {
	$filename=AMP_CONF;
	
	outn("\n\nUse simple Extensions [extensions] admin or separate Devices and Users [deviceanduser]?\n [extensions] ");
	$key = trim(fgets(STDIN,1024));
	if (preg_match('/^$/',$key)) $amp_conf["AMPEXTENSIONS"] = "extensions";
	else $amp_conf["AMPEXTENSIONS"] = $key;
	
	// write amportal.conf
	write_amportal_conf($filename, $amp_conf);
}

?>