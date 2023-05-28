<?php 
//add FOPWEBROOT, AMPBIN, AMPSBIN option to amportal.conf

$filename=AMP_CONF;

if (!array_key_exists("FOPWEBROOT",$amp_conf)) {
	out("Adding FOPWEBROOT option to amportal.conf - using AMP default");
	$amp_conf["FOPWEBROOT"] = $amp_conf["AMPWEBROOT"]."/panel";
}

if (!array_key_exists("AMPBIN",$amp_conf)) {
	out("Adding AMPBIN option to amportal.conf - using AMP default");
	$amp_conf["AMPBIN"] = "/var/lib/asterisk/bin";
}

if (!array_key_exists("AMPSBIN",$amp_conf)) {
	out("Adding AMPSBIN option to amportal.conf - using AMP default");
	$amp_conf["AMPSBIN"] = "/usr/sbin";
}

if (!array_key_exists("AMPDBHOST",$amp_conf)) {
	out("Adding AMPDBHOST option to amportal.conf - using AMP default");
	$amp_conf["AMPDBHOST"] = "localhost";
}

// write amportal.conf
write_amportal_conf($filename, $amp_conf);

?>
