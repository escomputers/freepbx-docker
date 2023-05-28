<?php

if (! class_exists('\FreePBX\modules\Logfiles\logfiles_conf')) {
    include_once (dirname(__FILE__) . '/Logfiles_conf.php');
}
if (! class_exists('logfiles_conf')) {
    // We create the alias since "/libraries/BMO/FileHooks.class.php::processOldHooks"
    // cannot find the class if it has a namespace defined.
    class_alias('\FreePBX\modules\Logfiles\logfiles_conf', 'logfiles_conf');
}

/**
 * Generate astierks configs
 * https://wiki.freepbx.org/pages/viewpage.action?pageId=98701336
 * 
 * Code to test third party events.
 * Uncomment for testing.
 * 
 */
// function logfiles_get_config($engine)
// {
//     global $logfiles_conf;
//     $logfiles_conf->addLoggerLogfiles('test_dpma', 'notice,warning,dpma');
// }
