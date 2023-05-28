<?php

// Remove our cronjob
$file = \FreePBX::Config()->get('AMPWEBROOT')."/admin/modules/dashboard/scheduler.php";
$c = \FreePBX::Cron(\FreePBX::Config()->get('AMPASTERISKWEBUSER'));
$c->removeAll($file);

