<?php /* $Id: custom-context.php $ */
/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
  
global $amp_conf;

if (! function_exists("out")) {
	function out($text) {
		echo $text."<br />";
	}
}

if (! function_exists("outn")) {
	function outn($text) {
		echo $text;
	}
}

/* update modules.conf to make sure it preloads chan_local.so and pbx_config.so
   The issue is discussed in ticket #3963
   This makes sure that the modules.conf has been updated for older systems
   which assures that static queue agents are availabe when Asterisk first starts
*/

$mod_conf = $amp_conf['ASTETCDIR'].'/modules.conf';
exec("grep -e '^[[:space:]]*preload[[:space:]]*=.*chan_local.so' $mod_conf",$output,$ret);
if ($ret) {
  outn(_("adding preload for chan_local.so to modules.conf.."));
  exec('sed -i.2.8.0-1.bak "s/\s*autoload\s*=.*/&\npreload => chan_local.so ;auto-inserted by FreePBX/" '.$mod_conf,$output,$ret);
  exec("grep -e '^[[:space:]]*preload[[:space:]]*=.*chan_local.so' $mod_conf",$output,$ret);
  if ($ret) {
    out(_("FAILED"));
    out(_("you may need to add the line 'preload => chan_local.so' to your modules.conf manually"));
  } else {
    out(_("ok"));
  }
}
unset($output);

exec("grep -e '^[[:space:]]*preload[[:space:]]*=.*pbx_config.so' $mod_conf",$output,$ret);
if ($ret) {
  outn(_("adding preload for pbx_config.so to modules.conf.."));
  exec('sed -i.2.8.0-2.bak "s/\s*autoload\s*=.*/&\npreload => pbx_config.so ;auto-inserted by FreePBX/" '.$mod_conf,$output,$ret);
  exec("grep -e '^[[:space:]]*preload[[:space:]]*=.*pbx_config.so' $mod_conf",$output,$ret);
  if ($ret) {
    out(_("FAILED"));
    out(_("you may need to add the line 'preload => pbx_config.so' to your modules.conf manually"));
  } else {
    out(_("ok"));
  }
}
unset($output);
