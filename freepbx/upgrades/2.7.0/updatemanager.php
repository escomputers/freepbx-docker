<?php
global $amp_conf;

/*  fix manager.conf settings for older manager.conf files being upgraded as new permissions are needed for later releases of Asterisk
 *  in english, this is limited to everything between the AMPMGRUSER section and any new section the user may have edited. It replaces
 *  everything to the right of a 'read =' or 'write =' permission line with the full set of permissoins Asterisk offers.
 */
exec('sed -i.2.7.0.bak "/^\['.$amp_conf['AMPMGRUSER'].'\]/,/^\[.*\]/s/^\(\s*read\s*=\|\s*write\s*=\).*/\1 system,call,log,verbose,command,agent,user,config,command,dtmf,reporting,cdr,dialplan,originate/" '.$amp_conf['ASTETCDIR'].'/manager.conf',$outarr,$ret);
?>
