<?php

/**
* phpagi-asmanager.php : PHP Asterisk Manager functions
* Website: http://phpagi.sourceforge.net
*
* Copyright (c) 2004 Matthew Asham <matthewa@bcwireless.net>
* All Rights Reserved.
*
* This software is released under the terms of the GNU Public License v2
*  A copy of which is available from http://www.fsf.org/licenses/gpl.txt
*
* @package phpAGI
*/


if (!isset($bootstrap_settings['amportal_conf_initialized']) || !$bootstrap_settings['amportal_conf_initialized']) {
	$bootstrap_settings['skip_astman'] = true;
	$restrict_mods = true;
	include_once '/etc/freepbx.conf';
}

define('AST_CONFIG_DIR', $amp_conf['ASTETCDIR']);
define('AST_SPOOL_DIR', $amp_conf['ASTSPOOLDIR']);
define('AST_TMP_DIR', AST_SPOOL_DIR . '/tmp/');
define('DEFAULT_PHPAGI_CONFIG', AST_CONFIG_DIR . '/phpagi.conf');

define('AST_DIGIT_ANY', '0123456789#*');

define('AGI_PORT', 4573);

define('AGIRES_OK', 200);


define('AST_STATE_DOWN', 0);
define('AST_STATE_RESERVED', 1);
define('AST_STATE_OFFHOOK', 2);
define('AST_STATE_DIALING', 3);
define('AST_STATE_RING', 4);
define('AST_STATE_RINGING', 5);
define('AST_STATE_UP', 6);
define('AST_STATE_BUSY', 7);
define('AST_STATE_DIALING_OFFHOOK', 8);
define('AST_STATE_PRERING', 9);


function phpasmanager_error_handler($errno, $errstr, $errfile, $errline) {

		switch ($errno) {
		case E_WARNING:
		case E_USER_WARNING:
		case E_NOTICE:
		case E_USER_NOTICE:
		default:
			//dbug("Got a php-asmanager error of [$errno] $errstr");
			break;
		}

		/* Don't execute PHP internal error handler */
		return true;
}

/**
* Asterisk Manager class
*
* @link http://www.voip-info.org/wiki-Asterisk+config+manager.conf
* @link http://www.voip-info.org/wiki-Asterisk+manager+API
* @example examples/sip_show_peer.php Get information about a sip peer
* @package phpAGI
*/
class AGI_AsteriskManager {
	/**
	* Config variables
	*
	* @var array
	* @access public
	*/
	public $config;

	/**
	* Socket
	*
	* @access public
	*/
	public $socket = NULL;

	/**
	* Server we are connected to
	*
	* @access public
	* @var string
	*/
	public $server;

	/**
	* Port on the server we are connected to
	*
	* @access public
	* @var integer
	*/
	public $port;

	/**
	* Parent AGI
	*
	* @access private
	* @var AGI
	*/
	public $pagi;

	/**
	* Username we connected with (for reconnect)
	*
	* @access public
	* @var string
	*/
	public $username = NULL;

	/**
	* Secret we connected with (for reconnect)
	*
	* @access public
	* @var string
	*/
	public $secret = NULL;

	/**
	* Current state of events (for reconnect)
	*
	* @access public
	* @var string
	*/
	public $events = NULL;

	/**
	* Number of reconnect attempts per incident
	*
	* @access public
	* @var string
	*/
	public $reconnects = 2;

	/**
	* Event Handlers
	*
	* @access private
	* @var array
	*/
	public $event_handlers;

	/**
	* Log Level
	*
	* @access private
	* @var integer
	*/
	public $log_level;

	public $useCaching = false;

	private $memAstDB = array();
	private $memAstDBArray = array();

	private $memAstDBKeys = array();
	private $memAstDBKeysUpdated = false;

	/**
	* Constructor
	*
	* @param string $config is the name of the config file to parse or a parent agi from which to read the config
	* @param array $optconfig is an array of configuration vars and vals, stuffed into $this->config['asmanager']
	*/
	function __construct($config=NULL, $optconfig=array()) {
		// load config
		if(!is_null($config) && file_exists($config)) {
			$this->config = parse_ini_file($config, true);
		} elseif(file_exists(DEFAULT_PHPAGI_CONFIG)) {
			$this->config = parse_ini_file(DEFAULT_PHPAGI_CONFIG, true);
		}

		// If optconfig is specified, stuff vals and vars into 'asmanager' config array.
		foreach($optconfig as $var=>$val) {
			$this->config['asmanager'][$var] = $val;
		}


		// add default values to config for uninitialized values
		if (!isset($this->config['asmanager']['server'])) {
			$this->config['asmanager']['server'] = 'localhost';
		}
		if (!isset($this->config['asmanager']['port'])) {
			$this->config['asmanager']['port'] = 5038;
		}
		if (!isset($this->config['asmanager']['username'])) {
			$this->config['asmanager']['username'] = 'phpagi';
		}
		if (!isset($this->config['asmanager']['secret'])) {
			$this->config['asmanager']['secret'] = 'phpagi';
		}
		if (isset($this->config['asmanager']['cachemode'])) {
			$this->useCaching = $this->config['asmanager']['cachemode'];
		}

		$this->log_level = (isset($this->config['asmanager']['log_level']) && is_numeric($this->config['asmanager']['log_level']))
						? $this->config['asmanager']['log_level'] : false;
		$this->reconnects = isset($this->config['asmanager']['reconnects']) ? $this->config['asmanager']['reconnects'] : 2;
	}

	function LoadAstDB(){
		if (!empty($this->memAstDB)) {
			$this->memAstDB = array();
			$this->memAstDBKeys = array();
		}
		$this->memAstDB = $this->database_show();
		$this->memAstDBKeys = array_keys($this->memAstDB);
		$this->memAstDBKeysUpdated = true;
	}

	function getDBCache() {
		return $this->memAstDB;
	}

	/**
	* Send a request
	*
	* @param string $action
	* @param array $parameters
	* @return array of parameters
	*/
	function send_request($action, $parameters=array(), $retry=true) {
		$reconnects = $this->reconnects;

		$req = "Action: $action\r\n";
		foreach($parameters as $var=>$val) {
			if (is_array($val)) {
				foreach($val as $k => $v) {
					$req .= "$var: $k=$v\r\n";
				}
			} else {
				$req .= "$var: $val\r\n";
			}

		}
		$req .= "\r\n";
		$this->log("Sending Request down socket:",10);
		$this->log($req,10);
		if(!$this->connected()) {
			throw new Exception("Asterisk is not connected");
		}
		fwrite($this->socket, $req);
		$response = $this->wait_response();

			// If we got a false back then something went wrong, we will try to reconnect the manager connection to try again
			//
		while ($response === false && $retry && $reconnects > 0) {
			$this->log("Unexpected failure executing command: $action, reconnecting to manager and retrying: $reconnects");
			$this->disconnect();
			if ($this->connect($this->server.':'.$this->port, $this->username, $this->secret, $this->events) !== false) {
				if(!$this->connected()) {
					throw new Exception("Asterisk is not connected");
				}
				fwrite($this->socket, $req);
				$response = $this->wait_response();
			} else {
				if ($reconnects > 1) {
					$this->log("reconnect command failed, sleeping before next attempt");
					sleep(1);
				} else {
					$this->log("FATAL: no reconnect attempts left, command permanently failed, returning to calling program with 'false' failure code");
				}
			}
			$reconnects--;
		}
		if($action == 'Command' && empty($response['data']) && !empty($response['Output'])) {
			$response['data'] = $response['Output'];
			unset($response['Output']);
		}
		return $response;
	}

	/**
	* Wait for a response
	*
	* If a request was just sent, this will return the response.
	* Otherwise, it will loop forever, handling events
	* unless $return_on_event is set to true
	*
	* @param boolean $allow_timeout if the socket times out, return an empty array
	* @param boolean $return_on_event return on event as if it was a message or response
	* @return array of parameters, empty on timeout
	*/
	function wait_response($allow_timeout = false, $return_on_event = false) {
		$timeout = false;

		set_error_handler("phpasmanager_error_handler");
		do {
			$type = NULL;
			$parameters = array();

			if (!$this->socket || feof($this->socket)) {
				$this->log("Got EOF in wait_response() from socket waiting for response, returning false",10);
				restore_error_handler();
				return false;
			}
			$buffer = trim(fgets($this->socket, 4096));
			while($buffer != '') {
				$a = strpos($buffer, ':');
				if($a) {
					if(!count($parameters)) {// first line in a response?
						$type = strtolower(substr($buffer, 0, $a));
						if(substr($buffer, $a + 2) == 'Follows') {
							// A 'follows' response means there is a muiltiline field that follows.
							$parameters['data'] = '';
							$buff = fgets($this->socket, 4096);
							while(substr($buff, 0, 6) != '--END ') {
								$parameters['data'] .= $buff;
								$buff = fgets($this->socket, 4096);
							}
						}
					} elseif(count($parameters) == 2) {
						if($parameters['Response'] == "Success" && isset($parameters['Message']) && $parameters['Message'] == 'Command output follows') {
							// A 'Command output follows' response means there is a muiltiline field that follows.
							// This is Asterisk 14 Logic:
							$parameters['data'] = "Privilege: Command\n"; //Add this to make Asterisk 14 look/work like < 13
							$parameters['data'] .= preg_replace("/^Output:\s*/","",$buffer)."\n";
							$buff = fgets($this->socket, 4096);
							while($buff !== "\r\n") {
								$buff = preg_replace("/^Output:\s*/","",$buff);
								$parameters['data'] .= trim($buff)."\n";
								$buff = fgets($this->socket, 4096);
							}
							break;
						}
					}

					// store parameter in $parameters
					$parameters[substr($buffer, 0, $a)] = substr($buffer, $a + 2);
				}
				$buffer = trim(fgets($this->socket, 4096));
			}

			// process response
			switch($type) {
				case '': // timeout occured
					$timeout = $allow_timeout;
					break;
				case 'event':
					$this->process_event($parameters);
					break;
				case 'response':
				case 'message':
					break;
				default:
					$this->log('Unhandled response packet ('.$type.') from Manager: ' . print_r($parameters, true));
					break;
			}
		} while(($return_on_event && ($type != 'event' && $type != 'response' && $type != 'message' && !$timeout)) || (!$return_on_event && ($type != 'response' && $type != 'message' && !$timeout)));
		$this->log("returning from wait_response with with type: $type",10);
		$this->log('$parmaters: '.print_r($parameters,true),10);
		$this->log('$buffer: '.print_r($buffer,true),10);
		if (isset($buff)) {
			$this->log('$buff: '.print_r($buff,true),10);
		}
		restore_error_handler();
		return $parameters;
	}

	/**
	 * Attempt to reconnect to Asterisk
	 * @param string $events whether events are on or off for this connection
	 */
	function reconnect($events = null) {
		if($this->connected()) {
			return true; //we are already connected
		}
		$this->events = !empty($events) ? $events : $this->events;
		set_error_handler("phpasmanager_error_handler");
		if(empty($this->username) || empty($this->secret) || empty($this->server)) {
			throw new \Exception("Unable to reconnect, try using connect first");
		}

		// connect the socket
		$errno = $errstr = NULL;

		$this->socket = stream_socket_client("tcp://".$this->server.":".$this->port, $errno, $errstr);
		stream_set_timeout($this->socket,30);
		if(!$this->socket) {
			$this->log("Unable to connect to manager {$this->server}:{$this->port} ($errno): $errstr");
			restore_error_handler();
			return false;
		}

		// read the header
		$str = fgets($this->socket);
		if($str == false) {
			// a problem.
			$this->log("Asterisk Manager header not received.");
			restore_error_handler();
			return false;
		} else {
			// note: don't $this->log($str) until someone looks to see why it mangles the logging
		}

		// login
		$res = $this->send_request('login',
							array('Username'=>$this->username, 'Secret'=>$this->secret, 'Events'=>$events),
							false);
		if($res['Response'] != 'Success') {
			$this->log("Failed to login.");
			$this->disconnect();
			restore_error_handler();
			return false;
		}
		restore_error_handler();
		return true;
	}

	/**
	* Connect to Asterisk
	*
	* @example examples/sip_show_peer.php Get information about a sip peer
	*
	* @param string $server
	* @param string $username
	* @param string $secret
	* @param string $events whether events are on or off for this connection
	* @return boolean true on success
	*/
	function connect($server=NULL, $username=NULL, $secret=NULL, $events='on') {
		set_error_handler("phpasmanager_error_handler");

		// use config if not specified
		if(is_null($server)) {
			$server = $this->config['asmanager']['server'];
		}
		$this->username = is_null($username) ? $this->config['asmanager']['username'] : $username;
		$this->secret = is_null($secret) ? $this->config['asmanager']['secret'] : $secret;
		$this->events = $events;

		// get port from server if specified
		if(strpos($server, ':') !== false) {
			$c = explode(':', $server);
			$this->server = $c[0];
			$this->port = $c[1];
		} else {
			$this->server = $server;
			$this->port = $this->config['asmanager']['port'];
		}

		// connect the socket
		$errno = $errstr = NULL;

		$this->socket = stream_socket_client("tcp://".$this->server.":".$this->port, $errno, $errstr);
		stream_set_timeout($this->socket,30);
		if(!$this->socket) {
			$this->log("Unable to connect to manager {$this->server}:{$this->port} ($errno): $errstr");
			restore_error_handler();
			return false;
		}

		// read the header
		$str = fgets($this->socket);
		if($str == false) {
			// a problem.
			$this->log("Asterisk Manager header not received.");
			restore_error_handler();
			return false;
		} else {
			// note: don't $this->log($str) until someone looks to see why it mangles the logging
		}

		// login
		$res = $this->send_request('login',
							array('Username'=>$this->username, 'Secret'=>$this->secret, 'Events'=>$this->events),
							false);
		if($res['Response'] != 'Success') {
			$this->log("Failed to login.");
			$this->disconnect();
			restore_error_handler();
			return false;
		}
		restore_error_handler();
		return true;
	}

	/**
	* Disconnect
	*
	* @example examples/sip_show_peer.php Get information about a sip peer
	*/
	function disconnect() {
		if($this->connected()) {
			$this->logoff();
		}
		if(is_resource($this->socket)) {
			fclose($this->socket);
		}
	}

	/**
	* Check if the socket is connected
	*
	*/
	function connected() {
		return is_resource($this->socket) && !feof($this->socket);
	}

	/**
	* Set Absolute Timeout
	*
	* Hangup a channel after a certain time. Acknowledges set time with Timeout Set message.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_AbsoluteTimeout
	* @version 11
	* @param string $channel
	* @param integer $timeout
	*/
	function AbsoluteTimeout($channel, $timeout) {
		return $this->send_request('AbsoluteTimeout', array('Channel'=>$channel, 'Timeout'=>$timeout));
	}

	/**
	* Sets an agent as no longer logged in.
	*
	* Sets an agent as no longer logged in.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_AgentLogoff
	* @version 11
	* @param string $agent Agent ID of the agent to log off.
	* @param string $soft  Set to true to not hangup existing calls.
	*/
	function AgentLogoff($agent, $soft) {
		return $this->send_request('AgentLogoff', array('Agent'=>$agent, 'Soft'=>$soft));
	}

	/**
	* Lists agents and their status.
	*
	* Will list info about all possible agents.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_Agents
	* @version 11
	*/
	function Agents() {
		return $this->send_request('AgentLogoff');
	}

	function AGI($channel, $command, $commandid) {
		return $this->send_request('AGI', array('Channel'=>$channel, 'Command'=>$command, "CommandID" => $commandid));
	}

	/**
	* Send an arbitrary event.
	*
	* Send an event to manager sessions.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_UserEvent
	* @param string $channel
	* @param string $file
	*/
	function UserEvent($event, $headers=array()) {
		$d = array('UserEvent'=>$event);
		$i = 1;
		foreach($headers as $key => $value) {
			if($key == 'UserEvent') {
				continue;
			}
			$d[$key] = $value;
			$i++;
		}
		return $this->send_request('UserEvent', $d);
	}

	/**
	* Change monitoring filename of a channel
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ChangeMonitor
	* @param string $channel
	* @param string $file
	*/
	function ChangeMonitor($channel, $file) {
		return $this->send_request('ChangeMontior', array('Channel'=>$channel, 'File'=>$file));
	}

	/**
	* Execute Command
	*
	* @example examples/sip_show_peer.php Get information about a sip peer
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Command
	* @link http://www.voip-info.org/wiki-Asterisk+CLI
	* @param string $command
	* @param string $actionid message matching variable
	*/
	function Command($command, $actionid=NULL) {
		$parameters = array('Command'=>$command);
		if($actionid) {
			$parameters['ActionID'] = $actionid;
		}
		return $this->send_request('Command', $parameters);
	}

	/**
	* Tell Asterisk to poll mailboxes for a change
	*
	* Normally, MWI indicators are only sent when Asterisk itself changes a mailbox.
	* With external programs that modify the content of a mailbox from outside the
	* application, an option exists called pollmailboxes that will cause voicemail
	* to continually scan all mailboxes on a system for changes. This can cause a
	* large amount of load on a system. This command allows external applications
	* to signal when a particular mailbox has changed, thus permitting external
	* applications to modify mailboxes and MWI to work without introducing
	* considerable CPU load.
	*
	* If Context is not specified, all mailboxes on the system will be polled for
	* changes. If Context is specified, but Mailbox is omitted, then all mailboxes
	* within Context will be polled. Otherwise, only a single mailbox will be
	* polled for changes.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+12+ManagerAction_VoicemailRefresh
	* @param string $context
	* @param string $mailbox
	* @param string $actionid ActionID for this transaction. Will be returned.
	*/
	function VoicemailRefresh($context=NULL,$mailbox=NULL, $actionid=NULL) {
		global $amp_conf;
		if(version_compare($amp_conf['ASTVERSION'], "12.0", "lt")) {
			return false;
		}
		$parameters = array();
		if(!empty($context)) {
			$parameters['Context'] = $context;
		}
		if(!empty($mailbox)) {
			$parameters['Mailbox'] = $mailbox;
		}
		if(!empty($actionid)) {
			$parameters['ActionID'] = $actionid;
		}

		$ret = $this->send_request('VoicemailRefresh', $parameters);
		//voicemail refresh doesnt always work so just reload voicemail
		$this->Reload('app_voicemail.so',$actionid);
		return $ret;
	}

	/**
	 * Get and parse codecs
	 * @param {string} $type='audio' Type of codec to look up
	 */
	function Codecs($type='audio') {
		$type = strtolower($type);
		switch($type) {
			case 'video':
				$ret = $this->Command('core show codecs video');
			break;
			case 'text':
				$ret = $this->Command('core show codecs text');
			break;
			case 'image':
				$ret = $this->Command('core show codecs image');
			break;
			case 'audio':
			default:
				$ret = $this->Command('core show codecs audio');
			break;
		}
		global $amp_conf;
		//must take it from the FORMAT column
		if(version_compare($amp_conf['ASTVERSION'], "13.10", "ge")) {
			if(preg_match_all('/\d{1,6}\s*'.$type.'\s*[a-z0-9]*\s*([a-z0-9]*)\s/i',$ret['data'],$matches)) {
				return $matches[1];
			}
		} else {
			if(preg_match_all('/\d{1,6}\s*'.$type.'\s*([a-z0-9]*)\s/i',$ret['data'],$matches)) {
				return $matches[1];
			}
		}

		return array();

	}

	/**
	* Kick a Confbridge user.
	*
	* Kick a Confbridge user.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_ConfbridgeKick
	* @param string $conference Conference number.
	* @param string $channel If this parameter is not a complete channel name, the first channel with this prefix will be used.
	*/
	function ConfbridgeKick($conference, $channel) {
		return $this->send_request('ConfbridgeKick', array('Conference'=>$conference, 'Channel'=>$channel));
	}

	/**
	* List Users in a Conference
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_ConfbridgeList
	* @param string $conference Conference number.
	*/
	function ConfbridgeList($conference) {
		$this->add_event_handler("confbridgelist", array($this, 'Confbridge_catch'));
		$this->add_event_handler("confbridgelistcomplete", array($this, 'Confbridge_catch'));
		$response = $this->send_request('ConfbridgeList', array('Conference'=>$conference));
		if ($response["Response"] == "Success") {
			$this->response_catch = array();
			$this->wait_response(true);
			stream_set_timeout($this->socket, 30);
		} else {
			return false;
		}
		unset($this->event_handlers['confbridgelist']);
		unset($this->event_handlers['confbridgelistcomplete']);
		return $this->response_catch;
	}

	/**
	* List active conferences.
	*
	* Lists data about all active conferences. ConfbridgeListRooms will follow as separate events, followed by a final event called ConfbridgeListRoomsComplete.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_ConfbridgeListRooms
	*/
	function ConfbridgeListRooms() {
		$this->add_event_handler("confbridgelistrooms", array($this, 'Confbridge_catch'));
		$this->add_event_handler("confbridgelistroomscomplete", array($this, 'Confbridge_catch'));
		$response = $this->send_request('ConfbridgeListRooms');
		if ($response["Response"] == "Success") {
			$this->response_catch = array();
			$this->wait_response(true);
			stream_set_timeout($this->socket, 30);
		} else {
			return false;
		}
		unset($this->event_handlers['confbridgelistrooms']);
		unset($this->event_handlers['confbridgelistroomscomplete']);
		return $this->response_catch;
	}

	/**
	* Conference Bridge Event Catch
	*
	* This catches events obtained from the confbridge stream, it should not be used externally
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_ConfbridgeListRooms
	*/
	private function Confbridge_catch($event, $data, $server, $port) {
		switch($event) {
			case 'confbridgelistcomplete':
			case 'confbridgelistroomscomplete':
				/* HACK: Force a timeout after we get this event, so that the wait_response() returns. */
				stream_set_timeout($this->socket, 0, 1);
			break;
			default:
				$this->response_catch[] =  $data;
		}
	}

	/**
	* Conference Bridge Event Catch
	*
	* This catches events obtained from the confbridge stream, it should not be used externally
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_ConfbridgeListRooms
	*/
	private function Meetme_catch($event, $data, $server, $port) {
		switch($event) {
			case 'meetmelistcomplete':
			case 'meetmelistroomscomplete':
				/* HACK: Force a timeout after we get this event, so that the wait_response() returns. */
				stream_set_timeout($this->socket, 0, 1);
			break;
			default:
				$this->response_catch[] =  $data;
		}
	}

	/**
	* Lock a Confbridge conference.
	*
	* Lock a Confbridge conference.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_ConfbridgeLock
	* @param string $conference Conference number.
	*/
	function ConfbridgeLock($conference) {
		return $this->send_request('ConfbridgeLock', array('Conference'=>$conference));
	}

	/**
	* Mute a Confbridge user.
	*
	* Mute a Confbridge user.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_ConfbridgeMute
	* @param string $conference Conference number.
	* @param string $channel If this parameter is not a complete channel name, the first channel with this prefix will be used.
	*/
	function ConfbridgeMute($conference,$channel) {
		return $this->send_request('ConfbridgeMute', array('Conference'=>$conference, 'Channel' => $channel));
	}

	/**
	* Set a conference user as the single video source distributed to all other participants.
	*
	* Set a conference user as the single video source distributed to all other participants.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_ConfbridgeSetSingleVideoSrc
	* @param string $conference Conference number.
	* @param string $channel If this parameter is not a complete channel name, the first channel with this prefix will be used.
	*/
	function ConfbridgeSetSingleVideoSrc($conference,$channel) {
		return $this->send_request('ConfbridgeSetSingleVideoSrc', array('Conference'=>$conference, 'Channel' => $channel));
	}

	/**
	* Start recording a Confbridge conference.
	*
	* Start recording a conference. If recording is already present an error will be returned.
	* If RecordFile is not provided, the default record file specified in the conference's bridge profile will be used, if that is not present either a file will automatically be generated in the monitor directory.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_ConfbridgeMute
	* @param string $conference Conference number.
	* @param string $channel If this parameter is not a complete channel name, the first channel with this prefix will be used.
	*/
	function ConfbridgeStartRecord($conference,$recordFile) {
		return $this->send_request('ConfbridgeStartRecord', array('Conference'=>$conference, 'RecordFile' => $recordFile));
	}

	/**
	* Stop recording a Confbridge conference.
	*
	* Stop recording a Confbridge conference.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_ConfbridgeStopRecord
	* @param string $conference Conference number.
	*/
	function ConfbridgeStopRecord($conference) {
		return $this->send_request('ConfbridgeStopRecord', array('Conference'=>$conference));
	}

	/**
	* Unlock a Confbridge conference.
	*
	* Unlock a Confbridge conference.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_ConfbridgeUnlock
	* @param string $conference Conference number.
	*/
	function ConfbridgeUnlock($conference) {
		return $this->send_request('ConfbridgeUnlock', array('Conference'=>$conference));
	}

	/**
	* Unmute a Confbridge user.
	*
	* Unmute a Confbridge user.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_ConfbridgeUnmute
	* @param string $conference Conference number.
	*/
	function ConfbridgeUnmute($conference,$channel) {
		return $this->send_request('ConfbridgeUnmute', array('Conference'=>$conference,'Channel' => $channel));
	}

	/**
	* Enable/Disable sending of events to this manager
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Events
	* @param string $eventmask is either 'on', 'off', or 'system,call,log'
	*/
	function Events($eventmask) {
		$this->events = $eventmask;
		return $this->send_request('Events', array('EventMask'=>$eventmask));
	}

	/**
	* Check Extension Status
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ExtensionState
	* @param string $exten Extension to check state on
	* @param string $context Context for extension
	* @param string $actionid message matching variable
	*/
	function ExtensionState($exten, $context, $actionid = NULL) {
		$parameters = array('Exten'=>$exten, 'Context'=>$context);
		if($actionid) {
			$parameters['ActionID'] = $actionid;
		}
					return $this->send_request('ExtensionState', $parameters);
	}

	/**
	* Gets a Channel Variable
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+GetVar
	* @param string $channel  Channel to read variable from
	* @param string $variable
	* @param string $actionid message matching variable
	*/
	function GetVar($channel=null, $variable, $actionid=NULL) {
		$parameters = array('Variable'=>$variable);
		if(!empty($channel)) {
			$parameters['Channel'] = $channel;
		}
		if($actionid) {
			$parameters['ActionID'] = $actionid;
		}
		return $this->send_request('GetVar', $parameters);
	}

	/**
	* Hangup Channel
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Hangup
	* @param string $channel The channel name to be hungup
	*/
	function Hangup($channel) {
		return $this->send_request('Hangup', array('Channel'=>$channel));
	}

	/**
	* List IAX Peers
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+IAXpeers
	*/
	function IAXPeers() {
		return $this->send_request('IAXPeers');
	}


	/**
	* Check Presence State
	*
	* Report the presence state for the given presence provider.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+13+ManagerAction_PresenceState
	* @param string $provider Presence Provider to check the state of
	*/
	function PresenceState($provider) {
		return $this->send_request('PresenceState',array('Provider'=>$provider));
	}

	/**
	* List the current known presence states.
	*
	* This will list out all known presence states in a sequence of PresenceStateChange events. When finished, a PresenceStateListComplete event will be emitted.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+13+ManagerAction_PresenceStateList
	*/
	function PresenceStateList() {
		$this->add_event_handler("presencestatechange", array($this, 'Presencestate_catch'));
		$this->add_event_handler("presencestatelistcomplete", array($this, 'Presencestate_catch'));
		$response = $this->send_request('PresenceStateList');
		if ($response["Response"] == "Success") {
			$this->response_catch = array();
			$this->wait_response(true);
			stream_set_timeout($this->socket, 30);
		} else {
			return false;
		}
		return $this->response_catch;
	}

	private function Presencestate_catch($event, $data, $server, $port) {
		switch($event) {
			case 'presencestatelistcomplete':
				/* HACK: Force a timeout after we get this event, so that the wait_response() returns. */
				stream_set_timeout($this->socket, 0, 1);
			break;
			default:
				$this->response_catch[] =  $data;
			break;
		}
	}

	/**
	* List available manager commands
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ListCommands
	* @param string $actionid message matching variable
	*/
	function ListCommands($actionid=NULL) {
		if($actionid) {
			return $this->send_request('ListCommands', array('ActionID'=>$actionid));
		} else {
			return $this->send_request('ListCommands');
		}
	}

	/**
	* Logoff Manager
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Logoff
	*/
	function Logoff() {
		return $this->send_request('Logoff',array(),false);
	}

	/**
	* Check Mailbox Message Count
	*
	* Returns number of new and old messages.
	*   Message: Mailbox Message Count
	*   Mailbox: <mailboxid>
	*   NewMessages: <count>
	*   OldMessages: <count>
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+MailboxCount
	* @param string $mailbox Full mailbox ID <mailbox>@<vm-context>
	*/
	function MailboxCount($mailbox, $actionid=NULL) {
		$parameters = array('Mailbox'=>$mailbox);
		if($actionid) {
			$parameters['ActionID'] = $actionid;
		}
		return $this->send_request('MailboxCount', $parameters);
	}

	/**
	* Check Mailbox
	*
	* Returns number of messages.
	*   Message: Mailbox Status
	*   Mailbox: <mailboxid>
	*   Waiting: <count>
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+MailboxStatus
	* @param string $mailbox Full mailbox ID <mailbox>@<vm-context>
	* @param string $actionid message matching variable
	*/
	function MailboxStatus($mailbox, $actionid=NULL) {
		$parameters = array('Mailbox'=>$mailbox);
		if($actionid) {
			$parameters['ActionID'] = $actionid;
		}
		return $this->send_request('MailboxStatus', $parameters);
	}

	/**
	 * MessageSend
	 *
	 * Send an SMS message
	 *
	 * @param string $to
	 * @param string $from
	 * @param string $body
	 * @param string $variable optional
	 * @returns array result of send_request
	*/
	function MessageSend($to, $from, $body, $variable=null) {
		$parameters['To'] = $to;
		$parameters['From'] = $from;
		$parameters['Base64Body'] = base64_encode($body);
		if($variable) {
			$parameters['Variable'] = $variable;
		}
		return $this->send_request('MessageSend', $parameters);
	}

	/**
	* List participants in a conference.
	*
	* Lists all users in a particular MeetMe conference. MeetmeList will follow as separate events, followed by a final event called MeetmeListComplete.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_MeetmeList
	* @param string $conference Conference number.
	*/
	function MeetmeList($conference) {
		$this->add_event_handler("meetmelist", array($this, 'Meetme_catch'));
		$this->add_event_handler("meetmelistcomplete", array($this, 'Meetme_catch'));
		$response = $this->send_request('MeetmeList', array('Conference'=>$conference));
		if ($response["Response"] == "Success") {
			$this->response_catch = array();
			$this->wait_response(true);
			stream_set_timeout($this->socket, 30);
		} else {
			return false;
		}
		unset($this->event_handlers['meetmelist']);
		unset($this->event_handlers['meetmecomplete']);
		return $this->response_catch;
	}

	/**
	* List active conferences.
	*
	* Lists data about all active conferences. MeetmeListRooms will follow as separate events, followed by a final event called MeetmeListRoomsComplete.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_ConfbridgeListRooms
	*/
	function MeetmeListRooms() {
		$this->add_event_handler("meetmelistrooms", array($this, 'Meetme_catch'));
		$this->add_event_handler("meetmelistroomscomplete", array($this, 'Meetme_catch'));
		$response = $this->send_request('MeetmeListRooms');
		if ($response["Response"] == "Success") {
			$this->response_catch = array();
			$this->wait_response(true);
			stream_set_timeout($this->socket, 30);
		} else {
			return false;
		}
		unset($this->event_handlers['meetmelistrooms']);
		unset($this->event_handlers['meetmelistroomscomplete']);
		return $this->response_catch;
	}

	/**
	* Mute a Meetme user.
	*
	* Mute a Meetme user.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_MeetmeMute
	* @param string $meetme Conference number.
	* @param string $usernum User Number
	*/
	function MeetmeMute($meetme,$usernum) {
		return $this->send_request('MeetmeMute', array('Meetme'=>$meetme,'Usernum'=>$usernum));
	}

	/**
	* Unmute a Meetme user.
	*
	* Unmute a Meetme user.
	*
	* @link https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+ManagerAction_MeetmeUnmute
	* @param string $meetme Conference number.
	* @param string $usernum User Number
	*/
	function MeetmeUnmute($meetme,$usernum) {
		return $this->send_request('MeetmeUnmute', array('Meetme'=>$meetme,'Usernum'=>$usernum));
	}

	/**
	* Monitor a channel
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Monitor
	* @param string $channel
	* @param string $file
	* @param string $format
	* @param boolean $mix
	*/
	function Monitor($channel, $file=NULL, $format=NULL, $mix=NULL) {
		$parameters = array('Channel'=>$channel);
		if($file) {
			$parameters['File'] = $file;
		}
		if($format) {
			$parameters['Format'] = $format;
		}
		if(!is_null($file)) {
			$parameters['Mix'] = ($mix) ? 'true' : 'false';
		}
		return $this->send_request('Monitor', $parameters);
	}

	/**
	* Originate Call
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Originate
	* @param string $channel
	* @param string $exten
	* @param string $context
	* @param string $priority
	* @param integer $timeout
	* @param string $callerid
	* @param string $variable (Supports an array of values)
	* @param string $account
	* @param string $application
	* @param string $data
	* == exactly 11 values required ==
	*
	* -- OR --
	*
	* @pram array a key => value array of what ever you want to pass in
	*/
	function Originate() {
		$num_args = func_num_args();

		if ($num_args === 10) {
			$args = func_get_args();

			$parameters = array();
			if ($args[0]) {
				$parameters['Channel'] = $args[0];
			}
			if ($args[1]) {
				$parameters['Exten'] = $args[1];
			}
			if ($args[2]) {
				$parameters['Context'] = $args[2];
			}
			if ($args[3]) {
				$parameters['Priority'] = $args[3];
			}
			if ($args[4]) {
				$parameters['Timeout'] = $args[4];
			}
			if ($args[5]) {
				 $parameters['CallerID'] = $args[5];
			}
			if ($args[6]) {
				$parameters['Variable'] = $args[6];
			}
			if ($args[7]) {
				$parameters['Account'] = $args[7];
			}
			if ($args[8]) {
				$parameters['Application'] = $args[8];
			}
			if ($args[9]) {
				$parameters['Data'] = $args[9];
			}
		} else {
			$args = func_get_args();
			$args = $args[0];
			foreach ($args as $key => $val) {
				$parameters[$key] = $val;
			}
		}

		return $this->send_request('Originate', $parameters);
	}

	/**
	* List parked calls
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ParkedCalls
	*/
	function ParkedCalls($actionid=NULL) {
		if($actionid) {
			return $this->send_request('ParkedCalls', array('ActionID'=>$actionid));
		} else {
		return $this->send_request('ParkedCalls');
		}
	}

	/**
	* Ping
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Ping
	*/
	function Ping() {
		return $this->send_request('Ping');
	}

		 /**
	* Queue Add
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+QueueAdd
	* @param string $queue
	* @param string $interface
	* @param integer $penalty
	*/
	function QueueAdd($queue, $interface, $penalty=0) {
		$parameters = array('Queue'=>$queue, 'Interface'=>$interface);
		if($penalty) {
			$parameters['Penalty'] = $penalty;
		}
		return $this->send_request('QueueAdd', $parameters);
	}

	/**
	* Queue Remove
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+QueueRemove
	* @param string $queue
	* @param string $interface
	*/
	function QueueRemove($queue, $interface) {
		 return $this->send_request('QueueRemove', array('Queue'=>$queue, 'Interface'=>$interface));
	}
	/**
	* Queues
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Queues
	*/
	function Queues() {
		return $this->send_request('Queues');
	}

	/**
	* Queue Status
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+QueueStatus
	* @param string $actionid message matching variable
	*/
	function QueueStatus($queue=null,$member=null, $actionid=NULL) {
		$this->add_event_handler("queueentry", array($this, 'Queuestatus_catch'));
		$this->add_event_handler("queuemember", array($this, 'Queuestatus_catch'));
		$this->add_event_handler("queueparams", array($this, 'Queuestatus_catch'));
		$this->add_event_handler("queuestatuscomplete", array($this, 'Queuestatus_catch'));
		$parameters = array();
		if ($actionid) {
			$parameters['ActionID'] = $actionid;
		}
		if ($queue) {
			$parameters['Queue'] = $queue;
		}
		if ($member) {
			$parameters['Member'] = $member;
		}
		$response = $this->send_request('QueueStatus', $parameters);
		if ($response["Response"] == "Success") {
			$this->response_catch = array();
			$this->wait_response(true);
			stream_set_timeout($this->socket, 30);
		} else {
			return false;
		}
		unset($this->event_handlers['queueentry']);
		unset($this->event_handlers['queuemember']);
		unset($this->event_handlers['queueparams']);
		unset($this->event_handlers['queuestatuscomplete']);
		return $this->response_catch;
	}

	private function Queuestatus_catch($event, $data, $server, $port) {
		switch($event) {
			case 'queuestatuscomplete':
				/* HACK: Force a timeout after we get this event, so that the wait_response() returns. */
				stream_set_timeout($this->socket, 0, 1);
			break;
			default:
				$this->response_catch[] =  $data;
		}
	}

	/**
	* Redirect
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Redirect
	* @param string $channel
	* @param string $extrachannel
	* @param string $exten
	* @param string $context
	* @param string $priority
	*/
	function Redirect($channel, $extrachannel, $exten, $context, $priority) {
		return $this->send_request('Redirect', array('Channel'=>$channel, 'ExtraChannel'=>$extrachannel, 'Exten'=>$exten,
								'Context'=>$context, 'Priority'=>$priority));
	}

	/**
	* Set the CDR UserField
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+SetCDRUserField
	* @param string $userfield
	* @param string $channel
	* @param string $append
*/
	function SetCDRUserField($userfield, $channel, $append=NULL) {
		$parameters = array('UserField'=>$userfield, 'Channel'=>$channel);
		if($append) {
			$parameters['Append'] = $append;
		}
		return $this->send_request('SetCDRUserField', $parameters);
	}

	/**
	* Set Channel Variable
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+SetVar
	* @param string $channel Channel to set variable for
	* @param string $variable name
	* @param string $value
	*/
	function SetVar($channel, $variable, $value) {
		return $this->send_request('SetVar', array('Channel'=>$channel, 'Variable'=>$variable, 'Value'=>$value));
	}

	/**
	* List SIP Peers
	*/
	function SIPpeers() {
	// XXX need to look at source to find this function...
		return $this->send_request('SIPpeers');
	}

	/* Channel Status
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+Status
	* @param string $channel
	* @param string $actionid message matching variable
	*/
	function Status($channel=null,$actionid=NULL) {
		$this->add_event_handler("status", array($this, 'Status_catch'));
		$this->add_event_handler("statuscomplete", array($this, 'Status_catch'));
		$parameters = array();
		if ($actionid) {
			$parameters['ActionID'] = $actionid;
		}
		if ($channel) {
			$parameters['Channel'] = $queue;
		}
		$response = $this->send_request('Status', $parameters);
		if ($response["Response"] == "Success") {
			$this->response_catch = array();
			$this->wait_response(true);
			stream_set_timeout($this->socket, 30);
		} else {
			return false;
		}
		unset($this->event_handlers['status']);
		unset($this->event_handlers['statuscomplete']);
		return $this->response_catch;
	}

	private function Status_catch($event, $data, $server, $port) {
		switch($event) {
			case 'statuscomplete':
				/* HACK: Force a timeout after we get this event, so that the wait_response() returns. */
				stream_set_timeout($this->socket, 0, 1);
			break;
			default:
				$this->response_catch[] =  $data;
		}
	}

	/**
	* Stop monitoring a channel
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+StopMonitor
	* @param string $channel
	*/
	function StopMonitor($channel) {
		return $this->send_request('StopMonitor', array('Channel'=>$channel));
	}

	/**
	* Dial over Zap channel while offhook
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapDialOffhook
	* @param string $zapchannel
	* @param string $number
	*/
	function ZapDialOffhook($zapchannel = '', $number = '') {
		// XXX need to look at source to find this function...
		if ($zapchannel && $number) {
			return $this->send_request('ZapDialOffhook', array('ZapChannel'=>$zapchannel, 'Number'=>$number));
		} else {
			return $this->send_request('ZapDialOffhook');
		}

	}

	/**
	* Toggle Zap channel Do Not Disturb status OFF
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapDNDoff
	* @param string $zapchannel
	*/
	function ZapDNDoff($zapchannel = '') {
		// XXX need to look at source to find this function...
		if ($zapchannel) {
			return $this->send_request('ZapDNDoff', array('ZapChannel'=>$zapchannel));
		} else {
			return $this->send_request('ZapDNDoff');
		}
	}

	/**
	* Toggle Zap channel Do Not Disturb status ON
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapDNDon
	* @param string $zapchannel
	*/
	function ZapDNDon($zapchannel = '') {
		// XXX need to look at source to find this function...
		if ($zapchannel) {
			return $this->send_request('ZapDNDon', array('ZapChannel'=>$zapchannel));
		} else {
			return $this->send_request('ZapDNDon');
		}
	}

	/**
	* Hangup Zap Channel
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapHangup
	* @param string $zapchannel
	*/
	function ZapHangup($zapchannel = '') {
		// XXX need to look at source to find this function...
		if ($zapchannel) {
			return $this->send_request('ZapHangup', array('ZapChannel'=>$zapchannel));
		} else {
			return $this->send_request('ZapHangup');
		}
	}

	/**
	* Transfer Zap Channel
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapTransfer
	* @param string $zapchannel
	*/
	function ZapTransfer($zapchannel = '') {
		// XXX need to look at source to find this function...
		if ($zapchannel) {
			return $this->send_request('ZapTransfer', array('ZapChannel'=>$zapchannel));
		} else {
			return $this->send_request('ZapTransfer');
		}
	}
	/**
	* Zap Show Channels
	*
	* @link http://www.voip-info.org/wiki-Asterisk+Manager+API+Action+ZapShowChannels
	* @param string $actionid message matching variable
	*/
	function ZapShowChannels($actionid=NULL) {
		if($actionid) {
			return $this->send_request('ZapShowChannels', array('ActionID'=>$actionid));
		} else {
			return $this->send_request('ZapShowChannels');
		}
		}

	/*
	* Log a message
	*
	* @param string $message
	* @param integer $level from 1 to 4
	*/
	function log($message, $level = 1) {
		if($this->pagi != false) {
			$this->pagi->conlog($message, $level);
		} elseif ($this->log_level === false && $level <= $this->log_level) {
			if (function_exists('dbug')) {
				dbug('php-asmanager log', $message);
			} else {
				error_log(date('r') . ' - ' . $message);
			}
		}
	}

	/**
	* Add event handler
	*
	* Known Events include ( http://www.voip-info.org/wiki-asterisk+manager+events )
	*   Link - Fired when two voice channels are linked together and voice data exchange commences.
	*   Unlink - Fired when a link between two voice channels is discontinued, for example, just before call completion.
	*   Newexten -
	*   Hangup -
	*   Newchannel -
	*   Newstate -
	*   Reload - Fired when the "RELOAD" console command is executed.
	*   Shutdown -
	*   ExtensionStatus -
	*   Rename -
	*   Newcallerid -
	*   Alarm -
	*   AlarmClear -
	*   Agentcallbacklogoff -
	*   Agentcallbacklogin -
	*   Agentlogoff -
	*   MeetmeJoin -
	*   MessageWaiting -
	*   join -
	*   leave -
	*   AgentCalled -
	*   ParkedCall - Fired after ParkedCalls
	*   Cdr -
	*   ParkedCallsComplete -
	*   QueueParams -
	*   QueueMember -
	*   QueueStatusEnd -
	*   Status -
	*   StatusComplete -
	*   ZapShowChannels -  Fired after ZapShowChannels
	*   ZapShowChannelsComplete -
	*
	* @param string $event type or * for default handler
	* @param string $callback function
	* @return boolean sucess
	*/
	function add_event_handler($event, $callback) {
		$event = strtolower($event);
		$this->event_handlers[$event][] = $callback;
		return true;
	}

	/**
	* Process event
	*
	* @access private
	* @param array $parameters
	* @return mixed result of event handler or false if no handler was found
	*/
	function process_event($parameters) {
		$ret = false;
		$handlers = array();
		$e = strtolower($parameters['Event']);
		$this->log("Got event... $e");

		if(isset($this->event_handlers[$e])) {
			$handlers = array_merge($handlers, $this->event_handlers[$e]);
		}
		if(isset($this->event_handlers['*'])) {
			$handlers = array_merge($handlers, $this->event_handlers['*']);
		}

		foreach ($handlers as $handler) {
			if(is_callable($handler)){
				if (is_array($handler)) {
					$this->log('Execute handler ' . get_class($handler[0]) . '::' . $handler[1]);
					$ret = $handler[0]->{$handler[1]}($e, $parameters, $this->server, $this->port);
				} else {
					if(is_object($handler)) {
						$this->log("Execute handler " . get_class($handler));
					} else {
						$this->log("Execute handler $handler");
					}
					$ret = $handler($e, $parameters, $this->server, $this->port);
				}
			}
		}


		return $ret;
	}

	/** Show all entries in the asterisk database
	* @return Array associative array of key=>value
	*/
	function database_show($family='') {
		if ($this->useCaching) {
			if(empty($this->memAstDB)) {
				//blow away sub as well
				$this->memAstDBArray = array();
				$this->memAstDB = $this->parseAsteriskDatabase();
			}
			if ($family == '') {
				return $this->memAstDB;
			} else {
				$key = '/'.$family;
				if (isset($this->memAstDB[$key])) {
					return array($key => $this->memAstDB[$key]);
				} elseif(isset($this->memAstDBArray[$key])) {
					return $this->memAstDBArray[$key];
				} else {
					//TODO: this is intensive cache results
					$k = $key;
					$key .= '/';
					$regex = "/^".str_replace("/","\/",$key)."/";
					if ($this->memAstDBKeysUpdated == false) {
						$this->memAstDBKeys = array_keys($this->memAstDB);
						$this->memAstDBKeysUpdated = true;
					}
					$fam_arr = array_intersect_key($this->memAstDB,array_flip(preg_grep($regex,$this->memAstDBKeys)));
					$this->memAstDBArray[$k] = $fam_arr;
					return $fam_arr;
				}
			}
		} else {
			return $this->parseAsteriskDatabase($family);
		}
	}

	private function parseAsteriskDatabase($family='') {
		$r = $this->command("database show $family");

		$data = explode("\n",$r["data"]);
		$db = array();

		// Remove the Privilege => Command initial entry that comes from the heading
		//
		array_shift($data);
		foreach ($data as $line) {
			// Note the space here is specifically for PJSIP registration entries,
			// which have a : in them:
			// /registrar/contact/301;@sip:301@192.168.15.125:5062: {"outbound_proxy":"",....
			$temp = explode(": ",$line,2);
			if (trim($temp[0]) != '' && count($temp) == 2) {
				$temp[1] = isset($temp[1])?$temp[1]:null;
				$db[ trim($temp[0]) ] = trim($temp[1]);
			}
		}
		return $db;
	}

	/** Add an entry to the asterisk database
	 * @param string $family	The family name to use
	 * @param string $key		The key name to use
	 * @param mixed $value		The value to add
	 * @return bool True if successful
	 */
	function database_put($family, $key, $value) {
		$write_through = false;
		if (!empty($this->memAstDB)){
			$keyUsed="/".str_replace(" ","/",$family)."/".str_replace(" ","/",$key);
			$keyExists=isset($this->memAstDB[$keyUsed]);
			if (!$keyExists || $this->memAstDB[$keyUsed] !== $value) {
				if (!$keyExists) {
					$this->memAstDBKeysUpdated = false;
				}
				$this->memAstDB[$keyUsed] = $value;
				$write_through = true;
			}
			$this->memAstDBArray = array();
		} else {
			$write_through = true;
		}
		if ($write_through) {
			$value = str_replace('"','\\"',$value);
			$r = $this->command("database put ".str_replace(" ","/",$family)." ".str_replace(" ","/",$key)." \"".$value."\"");
			return (bool)strstr($r["data"], "success");
		}
		return true;
	}

	/** Get an entry from the asterisk database
	 * @param string $family	The family name to use
	 * @param string $key		The key name to use
	 * @return mixed Value of the key, or false if error
	 */
	function database_get($family, $key) {
		if ($this->useCaching) {
			if (empty($this->memAstDB)) {
				$this->LoadAstDB();
			}
			$keyUsed="/".str_replace(" ","/",$family)."/".str_replace(" ","/",$key);
			if (isset($this->memAstDB[$keyUsed])){
				return $this->memAstDB[$keyUsed];
			}
		} else {
			$r = $this->command("database get ".str_replace(" ","/",$family)." ".str_replace(" ","/",$key));
			$data = strpos($r["data"],"Value:");
			if ($data !== false) {
				return trim(substr($r["data"],6+$data));
			}
		}
		return false;
	}


	/** Delete an entry from the asterisk database
	 * @param string $family	The family name to use
	 * @param string $key		The key name to use
	 * @return bool True if successful
	 */
	function database_del($family, $key) {
		$r = $this->command("database del ".str_replace(" ","/",$family)." ".str_replace(" ","/",$key));
		$status = (bool)strstr($r["data"], "removed");
		if ($status && !empty($this->memAstDB)){
			$keyUsed="/".str_replace(" ","/",$family)."/".str_replace(" ","/",$key);
			unset($this->memAstDB[$keyUsed]);
			$this->memAstDBArray = array();
		}
		return $status;
	}

	/** Delete a family from the asterisk database
	 * @param string $family	The family name to use
	 * @return bool True if successful
	 */
	function database_deltree($family) {
		$r = $this->command("database deltree ".str_replace(" ","/",$family));
		$status = (bool)strstr($r["data"], "removed");
		if ($status && !empty($this->memAstDB)){
			$keyUsed="/".str_replace(" ","/",$family);
			foreach($this->memAstDB as $key => $val) {
				$reg = preg_quote($keyUsed,"/");
				if(preg_match("/^".$reg.".*/",$key)) {
					unset($this->memAstDB[$key]);
					$this->memAstDBArray = array();
				}
			}
		}
		return $status;
	}

	/** Returns whether a give function exists in this Asterisk install
	 * @param string $func	The case sensitve name of the function
	 * @return bool True if if it exists
	 */
	function func_exists($func) {
		$r = $this->command("core show function $func");
		return (strpos($r['data'],"No function by that name registered") === false);
	}

	/** Returns whether a give application exists in this Asterisk install
	 * @param string $app	The case in-sensitve name of the application
	 * @return bool True if if it exists
	 */
	function app_exists($app) {
		$r = $this->command("core show applications like $app");
		return (strpos($r['data'],"0 Applications Matching") === false);
	}

	/** Returns whether a give channeltype exists in this Asterisk install
	 * @param string $channel	The case in-sensitve name of the channel
	 * @return bool True if if it exists
	 */
	function chan_exists($channel) {
		$r = $this->command("core show channeltype $channel");
		return (strpos($r['data'],"is not a registered channel driver") === false);
	}

	/** Returns whether a give asterisk module is loaded in this Asterisk install
	 * @param string $app	The case in-sensitve name of the application
	 * @return bool True if if it exists
	 */
	function mod_loaded($mod) {
		$r = $this->command("module show like $mod");
		return (preg_match('/1 modules loaded/', $r['data']) > 0);
	}

	/** Sets a global var or function to the provided value
	 * @param string $var	The variable or function to set
	 * @param string $val	the value to set it to
	 * @return array returns the array value from the send_request
	 */
	function set_global($var, $val) {
		global $amp_conf;
		static $pre = '';

		if (! $pre) {
			$pre = version_compare($amp_conf['ASTVERSION'], "1.6.1", "ge") ? 'dialplan' : 'core';
		}
		return $this->command($pre . ' set global ' . $var . ' ' . $val);
	}

	/**
	* Reload module(s)
	*
	* @link http://www.voip-info.org/wiki/view/Asterisk+Manager+API+Action+Reload
	* @param string $module
	* @param string $actionid
	*/
	function Reload($module=NULL, $actionid=NULL) {
		$parameters = array();

		if ($actionid) {
			$parameters['ActionID'] = $actionid;
		}
		if ($module) {
			$parameters['Module'] = $module;
			return $this->send_request('Reload', $parameters);
		} else {
			//Until https://issues.asterisk.org/jira/browse/ASTERISK-25996 is fixed
			$a = function_exists("fpbx_which") ? fpbx_which("asterisk") : "asterisk";
			if(!empty($a)) {
				return exec($a . " -rx 'core reload'");
			}
		}

	}

	/** Starts mixmonitor
	 * @param string $channel	The channel to start recording
	 * @param string $file The file to record to
	 * @param string $options Options to pass to mixmonitor
	 * @param string $postcommand Command to execute after recording
	 * @param string $actionid message matching variable
	 *
	 * @return array returns the array value from the send_request
	 */
	function mixmonitor($channel, $file, $options='', $postcommand='', $actionid=NULL) {
		if (!$channel || !$file) {
			return false;
		}
		$args = 'mixmonitor start ' . trim($channel) . ' ' . trim($file);
		if ($options || $postcommand) {
			$args .= ',' . trim($options);
		}
		if ($postcommand) {
			$args .= ',' . trim($postcommand);
		}
		return $this->command($args, $actionid);
	}

	/** Stops mixmonitor
	 * @param string $channel	The channel to stop recording
	 * @param string $actionid message matching variable
	 *
	 * @return array returns the array value from the send_request
	 */
	function stopmixmonitor($channel, $actionid=NULL) {
		if (!$channel) {
			return false;
		}
		$args = 'mixmonitor stop ' . trim($channel);
		return $this->command($args, $actionid);
	}

	/**
	 * PJSIPShowEndpoint
	 * @param string $channel
	 *
	 * @return array returns a key => val array
	 */
	function PJSIPShowEndpoints() {
		$this->add_event_handler("endpointlist", array($this, 'Endpoints_catch'));
		$this->add_event_handler("endpointlistcomplete", array($this, 'Endpoints_catch'));
		$response = $this->send_request('PJSIPShowEndpoints');
		if ($response["Response"] == "Success") {
			$this->wait_response(true);
			stream_set_timeout($this->socket, 30);
		} else {
			return false;
		}
		$res = $this->response_catch;
		// Asterisk 12 can sometimes dump extra garbage after the
		// output of this. So grab it, and discard it, if it's
		// pending.
		// Note that this has been reported as a bug and should
		// be removed, or, wait_response needs to be re-written
		// to keep waiting until it receives the ending event
		// https://issues.asterisk.org/jira/browse/ASTERISK-24331
		usleep(1000);
		stream_set_blocking($this->socket, false);
		while (fgets($this->socket)) { /* do nothing */ }
		stream_set_blocking($this->socket, true);
		unset($this->event_handlers['endpointlist']);
		unset($this->event_handlers['endpointlistcomplete']);
		return $res;
	}

	function PJSIPShowRegistrationInboundContactStatuses() {
		$this->add_event_handler("contactstatusdetail", array($this, 'Contacts_catch'));
		$this->add_event_handler("contactstatusdetailcomplete", array($this, 'Contacts_catch'));
		$response = $this->send_request('PJSIPShowRegistrationInboundContactStatuses');
		if ($response["Response"] == "Success") {
			$this->wait_response(true);
			stream_set_timeout($this->socket, 30);
		} else {
			return false;
		}
		$res = $this->response_catch;
		// Asterisk 12 can sometimes dump extra garbage after the
		// output of this. So grab it, and discard it, if it's
		// pending.
		// Note that this has been reported as a bug and should
		// be removed, or, wait_response needs to be re-written
		// to keep waiting until it receives the ending event
		// https://issues.asterisk.org/jira/browse/ASTERISK-24331
		usleep(1000);
		stream_set_blocking($this->socket, false);
		while (fgets($this->socket)) { /* do nothing */ }
		stream_set_blocking($this->socket, true);
		unset($this->event_handlers['contactstatusdetail']);
		unset($this->event_handlers['contactstatusdetailcomplete']);
		return $res;
	}

	/**
	 * PJSIPShowEndpoint
	 * @param string $channel
	 *
	 * @return array returns a key => val array
	 */
	function PJSIPShowEndpoint($dev) {
		$this->add_event_handler("endpointdetail", array($this, 'Endpoint_catch'));
		$this->add_event_handler("authdetail", array($this, 'Endpoint_catch'));
		$this->add_event_handler("transportdetail", array($this, 'Endpoint_catch'));
		$this->add_event_handler("identifydetail", array($this, 'Endpoint_catch'));
		$this->add_event_handler("endpointdetailcomplete", array($this, 'Endpoint_catch'));
		$params = array("Endpoint" => $dev);
		$response = $this->send_request('PJSIPShowEndpoint', $params);
		if ($response["Response"] == "Success") {
			$this->wait_response(true);
			stream_set_timeout($this->socket, 30);
		} else {
			return false;
		}
		$res = $this->response_catch;
		// Asterisk 12 can sometimes dump extra garbage after the
		// output of this. So grab it, and discard it, if it's
		// pending.
		// Note that this has been reported as a bug and should
		// be removed, or, wait_response needs to be re-written
		// to keep waiting until it receives the ending event
		// https://issues.asterisk.org/jira/browse/ASTERISK-24331
		usleep(1000);
		stream_set_blocking($this->socket, false);
		while (fgets($this->socket)) { /* do nothing */ }
		stream_set_blocking($this->socket, true);
		unset($this->event_handlers['endpointdetail']);
		unset($this->event_handlers['authdetail']);
		unset($this->event_handlers['transportdetail']);
		unset($this->event_handlers['identifydetail']);
		unset($this->event_handlers['endpointdetailcomplete']);
		return $res;
	}

	/**
	* Catcher for the pjsip events
	*
	*/
	private function Contacts_catch($event, $data, $server, $port) {
		switch($event) {
			case 'contactstatusdetailcomplete':
				stream_set_timeout($this->socket, 0, 1);
				break;
			default:
				$this->response_catch[] =  $data;
		}
	}

	/**
	* Catcher for the pjsip events
	*
	*/
	private function Endpoints_catch($event, $data, $server, $port) {
		switch($event) {
			case 'endpointlistcomplete':
				stream_set_timeout($this->socket, 0, 1);
				break;
			default:
				$this->response_catch[] =  $data;
		}
	}

	/**
	* Catcher for the pjsip events
	*
	*/
	private function Endpoint_catch($event, $data, $server, $port) {
		switch($event) {
			case 'endpointdetailcomplete':
				stream_set_timeout($this->socket, 0, 1);
				break;
			default:
				$this->response_catch[] =  $data;
		}
	}

	/** List all channels
	 *
	 * @return array of all channels currently active
	 */

	public function CoreShowChannels() {
		$this->add_event_handler("coreshowchannel", array($this, 'coreshowchan_catch'));
		$this->add_event_handler("coreshowchannelscomplete", array($this, 'coreshowchan_catch'));
		$response = $this->send_request('CoreShowChannels');
		if ($response["Response"] == "Success") {
			$this->response_catch = array();
			$this->wait_response(true);
			stream_set_timeout($this->socket, 30);
		} else {
			return false;
		}
		unset($this->event_handlers['coreshowchannel']);
		unset($this->event_handlers['coreshowchannelscomplete']);
		return $this->response_catch;
	}


	private function coreshowchan_catch($event, $data, $server, $port) {
		switch($event) {
			case 'coreshowchannelscomplete':
				stream_set_timeout($this->socket, 0, 1);
			break;
			default:
				$this->response_catch[] =  $data;
		}
	}

}
