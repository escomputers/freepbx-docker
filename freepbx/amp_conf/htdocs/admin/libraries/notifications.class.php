<?php
/**
 * FreePBX Notifications
 *
 * This has been replaced with a BMO interface.
 */

if (!class_exists('FreePBX\\Notifications')) {
	include 'BMO/Notifications.class.php';
}
class Notifications extends FreePBX\Notifications {};
