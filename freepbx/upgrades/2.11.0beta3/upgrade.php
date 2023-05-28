<?php
global $amp_conf;

if (file_exists($amp_conf['AMPWEBROOT'] . '/index.html')) {
	unlink($amp_conf['AMPWEBROOT'] . '/index.html');
}
if (file_exists($amp_conf['AMPWEBROOT'] . '/mainstyle.css')) {
	unlink($amp_conf['AMPWEBROOT'] . '/mainstyle.css');
}
