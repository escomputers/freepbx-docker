<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
class Ajax extends FreePBX_Helpers {

	public $storage = 'null';
	private $headers = array();
	public $settings = array( "authenticate" => true, "allowremote" => false, "changesession" => false );

	public function __construct($freepbx = null) {
		$this->init();
		$this->freepbx = $freepbx;
	}

	public function init() {
		$this->getHeaders();
	}

	/**
	 * Perform AJAX Request
	 *
	 * Perform the Ajax Request
	 *
	 * @param $module The module name
	 * @param $command The command to execute
	 */
	public function doRequest($module = null, $command = null) {
		if (!$module || !$command) {
			throw new \Exception("Module or Command were null. Check your code.");
		}

		if (class_exists(ucfirst($module)) && $module != "directory") {
			throw new \Exception("The class $module already existed. Ajax MUST load it, for security reasons");
		}

		// Is someone trying to be tricky with filenames?
		if (strpos($module, ".") !== false) {
			throw new \Exception("Module requested invalid");
		}
		// Is it the hardcoded Framework module?
		if ($module == "framework") {
			$file = $this->Config->get_conf_setting('AMPWEBROOT')."/admin/libraries/BMO/Framework.class.php";
			$ucMod = "Framework";
		} elseif ($module == "search") { // Ajax Search plugin
			$file = $this->Config->get_conf_setting('AMPWEBROOT')."/admin/libraries/BMO/Search.class.php";
			$ucMod = "Search";
		} else {
			$ucMod = ucfirst($module);
			$ucMod = str_replace("-","dash",$ucMod);
			$file = $this->Config->get_conf_setting('AMPWEBROOT')."/admin/modules/$module/$ucMod.class.php";
		}

		// Note, that Self_Helper will throw an exception if the file doesn't exist, or if it does
		// exist but doesn't define the class.
		$this->injectClass($ucMod, $file);

		$thisModule = $this->$ucMod;
		if (!method_exists($thisModule, "ajaxRequest")) {
			$this->ajaxError(501, 'ajaxRequest not found');
		}

		$settings = array();
		if (!$thisModule->ajaxRequest($command, $settings)) {
			$this->ajaxError(403, 'ajaxRequest declined');
		}
		foreach($this->settings as $k => &$v) {
			if(isset($settings[$k])) {
				$v = $settings[$k];
			}
		}

		if($this->settings['allowremote'] !== true && $this->freepbx->Config->get('CHECKREFERER')) {
			// Try to avoid CSRF issues.
			if (!isset($_SERVER['HTTP_REFERER'])) {
				$this->ajaxError(403, 'ajaxRequest declined - Referrer');
			}

			// Make sure the url the request is sent to (ie us) is the same host as the referrer
                        // (ie the one which made the page whcih sent us this request).
                        $url = parse_url($_SERVER['HTTP_REFERER']);
			if (isset($url['port'])) {
				$checkhost = $url['host'].":".$url['port'];
			} else {
				$checkhost = $url['host'];
			}
			if ($checkhost != $_SERVER['HTTP_HOST']) {
				$this->ajaxError(403, 'ajaxRequest declined - Referrer');
			}
			// TODO: We should add tokens in here, as we're still vulnerable to CSRF via XSS.
		}

		// If we haven't been asked to NOT close the session, close it.
		// It's still readable, but you can't change it. We do this so that
		// it's not locked, and multiple things can ajax at the same time.
		if (!$this->settings['changesession']) {
			session_write_close();
		}

		// If the request has come from this machine then no need to authenticate.
		$request_from_ip = $_SERVER['REMOTE_ADDR'];
		if (($request_from_ip == '127.0.0.1') || ($request_from_ip == '::1')) {
			$this->settings['authenticate'] = false;
		}

		// Check authentication if set to
		if ($this->settings['authenticate']) {
			if (!isset($_SESSION['AMP_user'])) {
				$this->ajaxError(401, 'Not Authenticated');
			} else {
				if (!defined('FREEPBX_IS_AUTH')) {
					define('FREEPBX_IS_AUTH', 'TRUE');
				}
			}
		}

		if (!method_exists($thisModule, "ajaxHandler")) {
			$this->ajaxError(501, 'ajaxHandler not found');
		}

		if (method_exists($thisModule, "ajaxCustomHandler")) {
			$ret = $thisModule->ajaxCustomHandler();
			if($ret === true) {
				exit;
			}
		}

		// Right. Now we can actually do it!
		$ret = $thisModule->ajaxHandler();
		if($ret === false) {
			$this->triggerFatal();
		}
		$this->addHeader('HTTP/1.0','200');
		//some helpers
		if(is_bool($ret)) {
			$ret = array(
				"status" => $ret,
				"message" => "unknown"
			);
		} elseif(is_string($ret)) {
			$ret = array(
				"status" => true,
				"message" => $ret
			);
		}
		$output = $this->generateResponse($ret);
		$this->sendHeaders();
		echo $output;
		exit;
	}

	/**
	 * Generate Ajax Error
	 *
	 * Generates an Ajax Error (lower/less than fatal)
	 *
	 * @param $errnum The Error Number from addHeader()
	 * @param $message The message to display
	 */
	public function ajaxError($errnum, $message = 'Unknown Error') {
		$this->addHeader('HTTP/1.0',$errnum);
		$output = $this->generateResponse(array("error" => $message));
		$this->sendHeaders();
		echo $output;
		exit;
	}

	/**
	 * Trigger a Fatal Error
	 *
	 * Trigger Fatal error
	 *
	 * @param $message The message to display
	 */
	private function triggerFatal($message = 'Unknown Error') {
		$this->ajaxError(500, $message);
	}

	/**
	 * Gt URL
	 *
	 * Get The Ultimate Final URL
	 *
	 * @return string http url
	 */
	private function getUrl() {
		return isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'])
			? $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
			: '';
	}

	/**
	 * Get Body
	 *
	 * Get the raw HTML Body
	 *
	 * @return string The Raw Body Returned from PHP
	 */
	private function getBody() {
		return empty($this->body) ? file_get_contents('php://input') : $this->body;
	}

	/**
	 * Get Known Headers from the Remote
	 *
	 * Get headers and then store them in an object hash
	 */
	private function getHeaders() {
        $h = array(
            'accept'        => '',
			'address'		=> '',
			'content_type'	=> '',
			'host' 			=> '',
			'ip'			=> '',
			'nonce'			=> '',
			'port'			=> '',
			'signature'		=> '',
			'timestamp'		=> '',
			'token'			=> '',
			'uri'			=> '',
			'request'		=> '',
			'user_agent'	=> '',
			'verb'			=> '',
		);

		foreach ($_SERVER as $k => $v) {
        	switch ($k) {
            	case 'HTTP_ACCEPT':
                	$h['accept'] = $v;
                break;
				case 'HTTP_HOST':
					$h['host'] = $v;
				break;
				case 'CONTENT_TYPE':
					$h['content_type'] = $v;
				break;
				case 'SERVER_NAME':
					$h['address'] = $v;
				break;
				case 'SERVER_PORT':
					$h['port'] = $v;
				break;
				case 'REMOTE_ADDR':
					$h['ip'] = $v;
				break;
				case 'REQUEST_URI':
					$h['request'] = $v;
				break;
				case 'HTTP_TOKEN':
					$h['token'] = $v;
				break;
				case 'HTTP_NONCE':
					$h['nonce'] = $v;
				break;
				case 'HTTP_SIGNATURE':
					$h['signature'] = $v;
				break;
				case 'HTTP_USER_AGENT':
					$h['user_agent'] = $v;
				break;
				case 'REQUEST_METHOD':
					$h['verb'] = strtolower($v);
				break;
				case 'PATH_INFO':
					$h['uri'] = $v;
				break;
				default:
				break;
			}
		}

		if(empty($h['uri'])) {
			$h['uri'] = $h['request'];
		}

		$this->req = new \StdClass();
		$this->req->headers = $this->arrayToObject($h);
	}

	/**
	 * Get Server Protocol
	 *
	 * Not used yet
	 *
	 * @return string http
	 */
	private function getProtocol() {
		return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on"
			? 'https'
			: 'http';
	}

	/**
	 * Prepare headers to be returned
	 *
	 * Note: if just type is set, it will be assumed to be a value
	 *
	 * @param mixed $type type of header to be returned
	 * @param mixed $value value header should be set to
	 * @return $object New Object
	 */
	public function addHeader($type, $value = '') {
		$responses = array(
			200	=> 'OK',
			201	=> 'Created',
			202	=> 'Accepted',
			204	=> 'No Content',
			301	=> 'Moved Permanently',
			303	=> 'See Other',
			304	=> 'Not Modified',
			307	=> 'Temporary Redirect',
			400	=> 'Bad Request',
			401	=> 'Unauthorized',
			402	=> 'Forbidden',
			404	=> 'Not Found',
			405	=> 'Method Not Allowed',
			406	=> 'Not Acceptable',
			409	=> 'Conflict',
			412	=> 'Precondition Failed',
			415	=> 'Unsupported Media Type',
			500	=> 'Internal Server Error',
			503 => 'Service Unavailable'
		);

		if ($type && !$value) {
			$value = $type;
			$type = 'HTTP/1.1';
		}

		//clean up type
		$type = str_replace(array('_', ' '), '-', trim($type));
		//HTTP responses headers
		if ($type == 'HTTP/1.1') {
			$value = ucfirst($value);
			//ok is always fully capitalized, not just its first letter
			if ($value == 'Ok') {
				$value = 'OK';
			}

			if (array_key_exists($value, $responses) || $value = array_search($value, $responses)) {
				$this->headers['HTTP/1.1'] = $value . ' ' . $responses[$value];
				return true;
			} else {
				return false;
			}
		} //end HTTP responses

		//all other headers. Not sure if/how we can validate them more...
		$this->headers[$type] = $value;

		return true;
	}

	/**
	 * Send Headers to PHP
	 *
	 * Gets headers from this Object (if set) and sends them to the PHP compiler
	 */
	private function sendHeaders() {
		//send http header
		if (headers_sent()) {
			return;
		}
		if (isset($this->headers['HTTP/1.1'])) {
			header('HTTP/1.1 ' . $this->headers['HTTP/1.1']);
			unset($this->headers['HTTP/1.1']);
		} else {
			header('HTTP/1.1 200 OK'); //defualt to 200
		}

		//send all headers, if any
		if ($this->headers) {
			foreach ($this->headers as $k => $v) {
				header($k . ': ' . $v);
				//unlist sent headers, as this method can be called more than once
				unset($this->headers[$k]);
			}
		}

		//CORS: http://en.wikipedia.org/wiki/Cross-origin_resource_sharing
		header('Access-Control-Allow-Headers:Content-Type, Depth, User-Agent, X-File-Size, X-Requested-With, If-Modified-Since, X-File-Name, Cache-Control, X-Auth-Token');
		header('Access-Control-Allow-Methods: '.strtoupper($this->req->headers->verb));
		if ($this->settings['allowremote'] === true) {
			// You don't want to do this, honest.
			header('Access-Control-Allow-Origin: *');
		} else {
			$url = "http" . ((isset($_SERVER['HTTPS']) && $_SERVER["HTTPS"] == "on") ? "s://" : "://") . $_SERVER['HTTP_HOST'];
			header('Access-Control-Allow-Origin: $url');
		}
		header('Access-Control-Max-Age:86400');
		header('Allow: '.strtoupper($this->req->headers->verb));
	}

	/**
	 * Generate Response
	 *
	 * Generates a response after determining the accepted response from the client
	 *
	 * @param mixed $body Array of what should be in the body
	 * @return string XML or JSON or WHATever
	 */
	private function generateResponse($body) {
		$ret = false;

		if(!is_array($body)) {
			$body = array("message" => $body);
		}

		$accepts = explode(",",$this->req->headers->accept);
		foreach($accepts as $accept) {
			//strip off content accept priority
			$accept = preg_replace('/;(.*)/i','',$accept);
			switch($accept) {
				/* case "text/xml":
				case "application/xml":
					$this->addHeader('Content-Type', 'text/xml');
					//DOMDocument provides us with pretty print XML. Which is...pretty.
					require_once(dirname(__DIR__).'/Array2XML.class.php');
					$xml = \Array2XML::createXML('response', $body);
					return $xml->saveXML(); */
				case "text/json":
				case "application/json":
				default:
					$this->addHeader('Content-Type', 'application/json');
					return json_encode($body);
					break;
			}
		}

		//If nothing is defined then just default to showing json
		$this->addHeader('Content-Type', 'application/json');
		return json_encode($body);
	}

	/**
	 * Turn Array into an Object
	 *
	 * This turns any PHP array hash into a PHP Object. It's a cheat, but it works
	 *
	 * @param $arr The array
	 * @return object The PHP Object
	 */
	private function arrayToObject($arr) {
		return json_decode(json_encode($arr), false);
	}
}
