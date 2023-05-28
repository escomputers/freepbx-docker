<?php

$contents = parse_ini_file(\FreePBX::Config()->get('ASTETCDIR').'/asterisk.conf',true);
$contents['options']['dontwarn'] = 'yes';
\FreePBX::WriteConfig()->writeConfig('asterisk.conf', $contents, false);