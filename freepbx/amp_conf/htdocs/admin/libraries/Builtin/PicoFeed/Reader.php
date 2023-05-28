<?php

namespace PicoFeed\Reader;

class Reader {
	public function download($feed, $last_modified, $etag) {
		return new resource();
	}

	public function getParser($url, $content, $encoding) {
		return new parser();
	}
}

class resource {
	public function isModified() {
		return true;
	}
	public function getUrl() {

	}
	public function getContent() {

	}
	public function getEncoding() {

	}

	public function getEtag() {

	}

	public function getLastModified() {
		return time().rand(0,1000);
	}
}

class parser {
	public function execute() {
		return new \PicoFeed\Client\Client();
	}
}