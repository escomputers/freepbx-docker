<?php /* $Id$ */
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2015 Sangoma Technologies.
//
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

echo FreePBX::Music()->showPage();
function music_return_bytes($val) {
	$val = trim($val);
	$last = strtolower($val[strlen($val)-1]);
	switch($last) {
		// The 'G' modifier is available since PHP 5.1.0
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
		case 'k':
			$val *= 1024;
	}
	return $val;
}
?>
<script>
var post_max_size = <?php echo music_return_bytes(ini_get('post_max_size'))?>;
var upload_max_filesize = <?php echo music_return_bytes(ini_get('upload_max_filesize'))?>;
var max_size = (upload_max_filesize < post_max_size) ? upload_max_filesize : post_max_size;
</script>
