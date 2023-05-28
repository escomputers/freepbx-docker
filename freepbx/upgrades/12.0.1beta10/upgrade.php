<?php
global $amp_conf;
global $db;

exec('sed -i.12.0.1.bak "/^\['.$amp_conf['AMPMGRUSER'].'\]/,/^\[.*\]/s/^\(\s*read\s*=\|\s*write\s*=\).*/\1 system,call,log,verbose,command,agent,user,config,command,dtmf,reporting,cdr,dialplan,originate,message/" '.$amp_conf['ASTETCDIR'].'/manager.conf',$outarr,$ret);
