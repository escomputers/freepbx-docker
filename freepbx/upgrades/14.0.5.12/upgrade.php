<?php

$um = new \FreePBX\Builtin\UpdateManager();

$settings = $um->getCurrentUpdateSettings();

$update = [];
if($settings['auto_system_updates'] === 'enabled') {
	$update['auto_system_updates'] = "emailonly";
}

if($settings['auto_module_updates'] === 'enabled') {
	$update['auto_module_updates'] = "emailonly";
}

if(!empty($update)) {
	$um->updateUpdateSettings($update);
	\FreePBX::Notifications()->add_security('freepbx', 'UPDATE_CHANGES', _('System Updates have changed'), _('System Updates have changed. You are encouraged to make sure these settings are correct. Please follow the resolve link and click the "Scheduler and Alerts" tab to ensure the settings are correct then re-save'),'config.php?display=updates#scheduletab',true,true);
}