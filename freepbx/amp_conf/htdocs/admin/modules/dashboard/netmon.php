#!/usr/bin/env php
<?php

// This is a small script that writes a log to /dev/shm/netusage.
//
// It auto-exits if the file /dev/shm/running is more than 60 seconds
// old.

$outputfile = "/dev/shm/netusage";
$watchfile = "/dev/shm/running";

// If the output file already exists, we're already running!
if (file_exists($outputfile)) {
	print "Output file already exists. Am I already running?\n";
	exit(-1);
}

$fh = fopen($outputfile, "w");
chmod($outputfile, 0666);

// Figure out where 'ip' is
if (file_exists("/usr/sbin/ip")) {
	$iploc = "/usr/sbin/ip";
} elseif (file_exists("/sbin/ip")) {
	$iploc = "/sbin/ip";
} else {
	// Hope for the best...
	$iploc = "ip";
}


while (true) {
	// Does our watchfile exist?
	if (!file_exists($watchfile)) {
		print "Watchfile doesn't exist. Exiting!\n";
		@unlink($outputfile);
		exit(-1);
	}
	// Is our watchfile more than 60 seconds old?
	clearstatcache(); // lolphp
	$stat = stat($watchfile);
	if ($stat['mtime'] < time() - 60) {
		print "Watchfile too old. Exiting.\n";
		@unlink($outputfile);
		exit(0);
	}

	// Is our OUTPUT file too big?
	$stat  = stat($outputfile);
	if ($stat['size'] > 524288) { // 512kb
		// Nuke it and start again.
		ftruncate($fh, 0);
		rewind($fh);
	}

	$execoutput = [];
	exec("$iploc -s link", $execoutput, $ret);
	if ($ret !== 0) {
		print "Error running ip, can't continue\n";
		@unlink($outputfile);
		exit(-1);
	}
	try {
		$conf = parse_ip_output($execoutput);
		fwrite($fh, json_encode(["timestamp" => time(), "data" => $conf ])."\n");
		fflush($fh);
	} catch (\Exception $e) {
		print "Error: ".$e->getMessage()."\n";
		@unlink($outputfile);
		exit(-1);
	}
	sleep(1);
}


function parse_ip_output($outarr) {
	$ints = [];
	$nextline = [];
	$current = false;
	foreach ($outarr as $line) {
		if (!isset($line[0])) {
			// Empty line?
			continue;
		}
		// If the first char is NOT a space, it's a network name.
		if ($line[0] !== " ") {
			$intarr = explode(":", $line);
			$current = trim($intarr[1]);
			// If it's actually 'lo', we never save that.
			if ($current !== "lo") {
				$ints[$current] = [ "intnum" => $intarr[0], "intname" => $current, "other" => $intarr[2] ];
			}
			continue;
		}
		$line = trim($line);
		// Does it start with 'link/ether'? We have a MAC
		if (strpos($line, "link/ether") === 0) {
			$tmparr = explode(" ", $line);
			if (isset($tmparr[1])) {
				$ints[$current]['mac'] = $tmparr[1];
			}
			continue;
		}

		// Is the SECOND char 'X'? As in 'TX' or 'RX'?
		if ($line[1] === 'X') {
			$nextline = preg_split("/\s+/", $line);
			continue;
		}

		// Is the FIRST char a number?  This means we actually have data!
		if (is_numeric($line[1])) {
			if (!isset($nextline[0])) {
				throw new \Exception("Error parsing ip, number received before definition\n");
			}
			// Which line is this?
			$type = strtolower(substr(array_shift($nextline), 0, 2)); // Converts 'RX:' to 'rx'
			$data = preg_split("/\s+/", $line);
			// If it's actually 'lo', we never save that.
			if ($current !== "lo") {
				$ints[$current][$type] = array_combine($nextline, $data);
			}
		}

	}
	return $ints;
}

