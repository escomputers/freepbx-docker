#!/usr/bin/env php
<?php
// vim: set ai ts=4 sw=4 ft=php:
// License for all code of this FreePBX module can be found in the license file inside the module directory
// Copyright 2013 Schmooze Com Inc.
//
// Dashboard Scheduler.
// Runs every minute.
//

// Sleep to fix crazy issues with large VM hosting providers
sleep(mt_rand(1,30));

// Start quickly.
$bootstrap_settings['freepbx_auth'] = false;  // Just in case.
$restrict_mods = true; // Takes startup from 0.2 seconds to 0.07 seconds.
include '/etc/freepbx.conf';

use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Store\FlockStore;

$astrundir = \FreePBX::Config()->get('ASTRUNDIR');
if(!is_dir($astrundir) || !is_writable($astrundir)) {
	echo "Asterisk Run Dir [".$astrundir."] is missing or not writable! Is Asterisk running?\n";
	exit(1);
}
$lockStore = new FlockStore($astrundir);
$factory = new Factory($lockStore);
$lock = $factory->createLock('scheduler',60);
if (!$lock->acquire()) {
	// Unable to lock, we're already running.
	exit;
}

if(!$astman->connected()){
	exit;
}
// Run the trigger
\FreePBX::Dashboard()->runTrigger();
// remove lockfile, and then close handle to release kernel lock
$lock->release();
