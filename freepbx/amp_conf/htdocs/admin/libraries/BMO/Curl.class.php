<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the FreePBX Big Module Object.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */
namespace FreePBX;
class Curl {

	public $requestshandles = array();
	public $pesthandles = array();
	private $options = array();
	private $headers = array();
	private $hooks = array();

	public function __construct($freepbx=null) {
		$this->hooks = new \Requests_Hooks();
	}

	public function getProxySettings() {
		$conf = \FreePBX::Config();
		if ($conf->get('PROXY_ENABLED')) {
			$url = trim($conf->get('PROXY_ADDRESS'));
			if (!$url) {
				// It's blank? Whut?
				return array("enabled" => false);
			}

			$retarr = array("enabled" => true, "type" => "http", "url" => $url);

			// We don't want any prefix before the proxy host for the 'host' tag.
			if (preg_match("/\/\/(.+)/", $url, $out)) {
				$retarr['host'] = $out[1];
			}  else {
				$retarr['host'] = $url;
			}

			// Do we have a valid username and password?
			$user = $conf->get('PROXY_USERNAME');
			$pass = $conf->get('PROXY_PASSWORD');
			if ($user && $pass) {
				$retarr['username'] = $user;
				$retarr['password'] = $pass;
			}
			return $retarr;
		} else {
			return array("enabled" => false);
		}
	}

	/**
	 * Get Proxy based PEST object
	 * @param  string $url The URL to pass
	 * @return object     PEST object supporting proxy
	 */
	public function pest($url) {
		if (!$url) {
			throw new \Exception("Invalid URL");
		}

		if (!isset($this->pesthandles[$url])) {
			// Create the handle
			$handle = new \Pest($url);

			$proxy = $this->getProxySettings();

			if ($proxy['enabled']) {
				switch ($proxy['type']) {
				case "http":
					$handle->curl_opts[\CURLOPT_PROXYTYPE] = \CURLPROXY_HTTP;
					break;
				case "socks":
					$handle->curl_opts[\CURLOPT_PROXYTYPE] = \CURLPROXY_SOCKS5;
					break;
				default:
					throw new \Exception("Unknown proxy type ".$proxy['type']);
				}

				$handle->curl_opts[\CURLOPT_PROXY] = $proxy['url'];
				if (isset($proxy['username'])) {
					$handle->curl_opts[\CURLOPT_PROXYUSERPWD] = $proxy['username'].":".$proxy['password'];
				}
			}

			$this->pesthandles[$url] = $handle;
		}

		return $this->pesthandles[$url];
	}

	/**
	 * Get Proxy based Requests object
	 * @param  string $url The URL to pass
	 * @return object     PEST object supporting proxy
	 */
	public function requests($url) {
		if (!$url) {
			throw new \Exception("Invalid URL");
		}
		if (!isset($this->requestshandles[$url])) {
			$session = new \Requests_Session($url);
			$this->setProxy();
			$session->options = $this->options;
			$this->requestshandles[$url] = $session;
		}
		return $this->requestshandles[$url];
	}

	public function get($url, $data = array()) {
		$this->setProxy();
		$data = !empty($data) ? '?'.http_build_query($data) : '';
		return \Requests::get($url.$data, $this->headers, $this->options);
	}

	public function head($url) {
		$this->setProxy();
		return \Requests::delete($url, $this->headers, $this->options);
	}

	public function delete($url) {
		$this->setProxy();
		return \Requests::delete($url, $this->headers, $this->options);
	}

	public function post($url, $data = array()) {
		$this->setProxy();
		return \Requests::post($url, $this->headers, $data, $this->options);
	}

	public function put($url, $data = array()) {
		$this->setProxy();
		return \Requests::put($url, $this->headers, $data, $this->options);
	}

	public function patch($url, $data = array()) {
		$this->setProxy();
		return \Requests::patch($url, $this->headers, $data, $this->options);
	}

	public function progressCallback($method) {
		$this->addHook('request.progress', $method);
	}

	public function addHook($name, $data) {
		$this->hooks->register($name, $data);
		$this->options['hooks'] = $this->hooks;
	}

	public function addHeader($key, $value) {
		$this->headers[$key] = $value;
	}

	public function addOption($key, $value) {
		$this->options[$key] = $value;
	}

	public function reset() {
		$this->options = array();
		$this->headers = array();
		$this->hooks = new \Requests_Hooks();
	}

	public function setEnvVariables() {
		$p = $this->getProxySettings();
		if (!$p['enabled']) {
			return;
		}

		// We have a proxy. Set the required environment variables.
		if (isset($p['username'])) {
			$url = "http://".$p['username'].":".$p['password']."@".$p['host'];
		} else {
			$url = "http://".$p['host'];
		}
		putenv("http_proxy=$url");
		putenv("no_proxy=localhost,127.0.0.1,:1");
		return;
	}

	private function setProxy() {
		$proxy = $this->getProxySettings();
		if($proxy['enabled']) {
			if(!empty($proxy['username'])) {
				$this->options['proxy'] = array( $proxy['host'], $proxy['username'], $proxy['password'] );
			} else {
				$this->options['proxy'] = $proxy['host'];
			}
		}
	}
}
