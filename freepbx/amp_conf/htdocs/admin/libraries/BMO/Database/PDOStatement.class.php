<?php

namespace FreePBX\Database;

class PDOStatement extends \PDOStatement {
	private $dbh;
	protected function __construct($dbh) {
		$this->dbh = $dbh;
	}

	public function execute($input_parameters=null) {
		if(!empty($input_parameters)) {
			$logger = \FreePBX::Logger()->createLogDriver('query_performance', \FreePBX::Config()->get('ASTLOGDIR').'/query_performance.log', \Monolog\Logger::DEBUG);
			$logger = $logger->withName(posix_getpid());
			$logger->debug($this->queryString, json_encode($input_parameters));
		}
		return parent::execute($input_parameters);
	}
}