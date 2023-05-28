<?php
// vim: set ai ts=4 sw=4 ft=php:
//
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//

class AsteriskInfo2  {

	public $astman = false;

	public function __construct() {
		// Astman.. astman.. Where did I put that astman connection? Oh, HERE it is.
		$astman = FreePBX::create()->astman;
		$this->astman = $astman;
	}

	public function get_channel_totals() {
		if (!$this->astman) {
			return array(
				'external_calls'=>-1,
				'internal_calls'=>-1,
				'total_calls'=>-1,
				'total_channels'=>-1,
			);
		}
		$response = $this->astman->send_request('Command',array('Command'=>"core show channels"));

		$astout = explode("\n",$response['data']);

		$external_calls = 0;
		$internal_calls = 0;
		$total_calls = 0;
		$total_channels = 0;

		foreach ($astout as $line) {
			if (preg_match('/s@macro-dialout/', $line)) {
				$external_calls++;
			} else if (preg_match('/s@macro-dial:/', $line)) {
				$internal_calls++;
			} else if (preg_match('/^(\d+) active channel/i', $line, $matches)) {
				$total_channels = $matches[1];
			} else if (preg_match('/^(\d+) active call/i', $line, $matches)) {
				$total_calls = $matches[1];
			}
		}
		return array(
			'external_calls'=>$external_calls,
			'internal_calls'=>$internal_calls,
			'total_calls'=>$total_calls,
			'total_channels'=>$total_channels,
		);
	}

	public function get_connections() {
		// Grab our list of extensions.
		$sql = "SELECT `id` FROM `devices` WHERE `tech` <> 'custom'";
		$alldevices = FreePBX::create()->Database->query($sql)->fetchAll(PDO::FETCH_COLUMN, 0);
		$devices = array_flip($alldevices);

		$protocols = array("sip", "iax2", "pjsip");
		$vars = array(
			"users_online", "users_offline", "users_total",
			"trunks_online", "trunks_offline", "trunks_total",
			"registrations_online", "registrations_offline", "registrations_total",
		);

		// Build array to return
		foreach ($protocols as $p) {
			foreach ($vars as $v) {
				$retarr[$p."_".$v] = 0;
			}
		}

		// Add Totals
		foreach ($vars as $v) {
			$retarr[$v] = 0;
		}

		if (!$this->astman) {
			return $retarr;
		}

		$response = $this->astman->send_request('Command',array('Command'=>"sip show peers"));
		$astout = explode("\n",$response['data']);
		$blacklist = \FreePBX::Dashboard()->extIgnoreList();
		foreach ($astout as $line) {
			// Previous bug IRT trunks starting or ending with /'s here. Investigate.
			$exploded = preg_split('/\s+/', $line);
			if (strpos($exploded[0], '/') === false) {
				$name = $exploded[0];
			} else {
				list($name, $null) = explode('/', $exploded[0]);
			}
			//prefix blacklist
			foreach($blacklist as $num) {
				if(substr($name,0,$num['length']) == $num['value'] && $name !== $num['value']){
					continue 2;
				}
			}

			// How to we see if a trunk is down?
			if ( $exploded[1] == "(Unspecified)" ||  // No IP Address
				$exploded[5] == "UNREACHABLE" || $exploded[6] == "UNREACHABLE") {
				// This is a device that's down
				if (!isset($devices[$name])) {
					// It is, actually a TRUNK that's down.
					$retarr['sip_trunks_offline']++;
				} else {
					$retarr['sip_users_offline']++;
				}
			} elseif (filter_var($exploded[1], FILTER_VALIDATE_IP)) {
				// This is a device that's up.
				if (!isset($devices[$name])) {
					$retarr['sip_trunks_online']++;
				} else {
					$retarr['sip_users_online']++;
				}
			} // else it's not a device.
		}

		$response = $this->astman->send_request('Command',array('Command'=>"sip show registry"));
		$astout = explode("\n",$response['data']);
		$pos = false;
		foreach ($astout as $line) {
			if (trim($line) != '') {
				if ($pos===false) {
					// find the position of "State" in the first line
					$pos = strpos($line,"State");
				} else {
					// subsequent lines, check if it says "Registered" at that position
					if (substr($line,$pos,10) == "Registered") {
						$retarr['sip_registrations_online']++;
					} elseif (strlen($line) > $pos) {
						$retarr['sip_registrations_offline']++;
					}
				}
			}
		}

		$response = $this->astman->send_request('Command',array('Command'=>"iax2 show peers"));
		$astout = explode("\n",$response['data']);
		foreach ($astout as $line) {
			if (preg_match('/^(([a-z0-9\-_]+)(\/([a-z0-9\-_]+))?)\s+(\([a-z]+\)|\d{1,3}(\.\d{1,3}){3})/i', $line, $matches)) {
				//matches: [2] = name, [4] = username, [5] = host, [6] = part of ip (if IP)

				// have an IP address listed, so its online
				$online = !empty($matches[6]);

				if (!isset($devices[$matches[2]])) {
					// this is a trunk
					//TODO match trunk tech as well?
					$retarr['iax2_trunks_'.($online?'online':'offline')]++;
				} else {
					$retarr['iax2_users_'.($online?'online':'offline')]++;
				}
			}
		}


		$response = $this->astman->send_request('Command',array('Command'=>"iax2 show registry"));
		$astout = explode("\n",$response['data']);
		$pos = false;
		foreach ($astout as $line) {
			if (trim($line) != '') {
				if ($pos===false) {
					// find the position of "State" in the first line
					$pos = strpos($line,"State");
				} else {
					// subsequent lines, check if it syas "Registered" at that position
					if (substr($line,$pos,10) == "Registered") {
						$retarr['iax2_registrations_online']++;
					} elseif (strlen($line) > $pos) {
						$retarr['iax2_registrations_offline']++;
					}
				}
			}
		}

		$response = $this->astman->send_request('Command',array('Command'=>"pjsip show endpoints"));
		// This is an amazingly awful format to parse.
		$lines = explode("\n", $response['data']);
		$inheader = true;
		$istrunk = $isendpoint = false;
		foreach ($lines as $l) {
			if ($inheader) {
				if (isset($l[1]) && $l[1] == "=") {
					// Last line of the header.
					$inheader = false;
				}
				continue;
			}

			$l = trim($l);
			if (!$l) {
				continue;
			}

			// If we have a line starting with 'Endpoint:' then we found one!
			if (strpos($l, "Endpoint:") === 0) {
				if (preg_match("/Endpoint:\s+(.+)\/(.+?)\b\s+(.+)/", $l, $out)) {
					// Found a device
					$isendpoint = $out[1];
					$istrunk = false;

					foreach($blacklist as $num) {
						if(substr($isendpoint,0,$num['length']) == $num['value'] && $isendpoint !== $num['value']){
							continue 2;
						}
					}

					if (isset($out[3]) && strpos($out[3], "Unavail") === 0) {
						// Unavailable endpoint.
						$retarr['pjsip_users_offline']++;
					} else {
						$retarr['pjsip_users_online']++;
					}
					continue;
				} elseif (preg_match("/Endpoint:\s+(.+?)\b/", $l, $out)) {
					// Found a trunk
					$isendpoint = false;
					$istrunk = $out[1];
					continue;
				} else {
					throw new \Exception("Unable to parse endpoint $l");
				}
			}

			// If we have a Contact: line, then that's something that's registered!
			if (strpos($l, "Contact:") === 0) {
				if ($isendpoint !== false) {
					// This is a registered endpoint
					$retarr['pjsip_registrations_online']++;
				} elseif ($istrunk !== false) {
					// Trunk status... Check for 'avail' and 'NonQual'
					if (strpos($l, "Avail ") === false && strpos($l, "NonQual") === false) {
						// Trunk down.
						$retarr['pjsip_trunks_offline']++;
					} else {
						$retarr['pjsip_trunks_online']++;
					}
				} else {
					throw new \Exception("Found a contact before I figured out what it is!");
				}
			}
		}

		// Now figure out the totals.
		foreach ($protocols as $p) {
			$users = $retarr[$p."_users_online"]+$retarr[$p."_users_offline"];
			$retarr[$p."_users_total"] = $users;

			$trunks = $retarr[$p."_trunks_online"]+$retarr[$p."_trunks_offline"];
			$retarr[$p."_trunks_total"] = $trunks;

			$regs = $retarr[$p."_registrations_online"]+$retarr[$p."_registrations_offline"];
			$retarr[$p."_registrations_total"] = $regs;
		}

		foreach ($vars as $v) {
			foreach ($protocols as $p) {
				$retarr[$v] += $retarr[$p."_".$v];
			}
		}

		return $retarr;
	}

	public function get_uptime() {
		$output = array(
			'system' => 'Unknown',
			'reload' => 'Unknown',
			'system-seconds' => '-2',
			'reload-seconds' => '-2',
		);

		// If we can't connect to astman for some reason..
		if (!$this->astman) {
			return $output;
		}

		$response = $this->astman->send_request('Command',array('Command'=>"core show uptime seconds"));
		$astout = explode("\n",$response['data']);

		if (!class_exists('TimeUtils')) {
			include 'TimeUtils.class.php';
		}

		// Second line:
		// System uptime: 922134
		$words = explode(" ", $astout[1]);
		$output['system'] = TimeUtils::getReadable($words[2]);
		$output['system-seconds'] = $words[2];

		// Third line:
		// Last reload: 4157
		$words = explode(" ", $astout[2]);
		$output['reload'] = TimeUtils::getReadable($words[2]);
		$output['reload-seconds'] = $words[2];

		return $output;
	}
}
