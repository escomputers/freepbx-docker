<?php
// vim: set ai ts=4 sw=4 ft=php:
namespace FreePBX\Builtin;

/**
 * Abstraction layer for sending emails
 */
class Email {

	private $toarray = [];
	private $subject = false;
	private $from = false;
	private $body = false;
	private $priority = 3;

	public function __construct($to = false) {
		if ($to) {
			$this->toarray($to);
		}
	}

	public function setTo($to) {
		foreach ((array) $to as $dest) {
			$this->toarray[$dest] = true;
		}
	}

	public function setFrom($from) {
		$this->from = $from;
	}

	public function setSubject($subject) {
		$this->subject = $subject;
	}

	public function setBodyPlainText($body) {
		$this->bodyplain = $body;
	}

	public function setPriority($priority) {
		$this->priority = (int) $priority;
	}

	public function send() {
		// TODO: Use PHPMailer?
		$em = new \CI_Email;
		$em->from($this->getFrom());
		$em->to(join(",", $this->getTo()));
		$em->subject($this->getSubject());
		$em->message($this->getBodyPlainText());
		$em->set_priority($this->priority);
		return $em->send();
	}

	private function getFrom() {
		if (!$this->from) {
			throw new \Exception("No from address set");
		}
		return $this->from;
	}

	private function getTo() {
		if (!$this->toarray) {
			throw new \Exception("No to address set");
		}
		return array_keys($this->toarray);
	}

	private function getSubject() {
		if (!$this->subject) {
			throw new \Exception("No subject");
		}
		return $this->subject;
	}

	private function getBodyPlainText() {
		if (!$this->bodyplain) {
			throw new \Exception("No plain-text body");
		}
		return $this->bodyplain;
	}
}

