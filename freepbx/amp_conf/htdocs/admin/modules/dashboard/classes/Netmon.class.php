<?php

namespace FreePBX\modules\Dashboard;
use Hhxsv5\SSE\SSE;
use Hhxsv5\SSE\Update;
use Symfony\Component\Process\Process;
class Netmon {

	public function __construct() {
		// Figure out where 'ip' is
		if (file_exists("/usr/sbin/ip")) {
			$this->iploc = "/usr/sbin/ip";
		} elseif (file_exists("/sbin/ip")) {
			$this->iploc = "/sbin/ip";
		} else {
			// Hope for the best...
			$this->iploc = "ip";
		}
		set_time_limit(0);
	}

	public function getStats() {
		$execoutput = [];

		$process = new Process("{$this->iploc} -s link");
		$process->setTimeout(30);
		try {
			$process->run();
			$execoutput = explode("\n",$process->getOutput());
		} catch(\Exception $e) {
			return ["status" => false, "message" => $e->getMessage()];
		}

		try {
			$conf = $this->parse_ip_output($execoutput);
			return ["status" => true, "data" => $conf];
		} catch (\Exception $e) {
			return ["status" => false, "message" => $e->getMessage()];
		}
	}

	public function getLiveStats() {
		if(function_exists("apache_setenv")) {
			apache_setenv('no-gzip', '1');
		}
		session_write_close();
		header_remove();
		header('Content-Type: text/event-stream');
		header('Cache-Control: no-cache');
		header('Connection: keep-alive');
		header("Access-Control-Allow-Origin: *");
		header('Access-Control-Allow-Credentials: true');
		header('X-Accel-Buffering: no');//Nginx: unbuffered responses suitable for Comet and HTTP streaming applications
		(new SSE())->start(new Update(function () {
			return json_encode($this->getStats());
		}, 1), 'new-msgs', 1000);
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
}

