<?php

namespace FreePBX\modules\Logfiles;

class logfiles_conf
{
	var $_loggergeneral  = array();
	var $_loggerlogfiles = array();

	private static $obj;

	// FreePBX magic ::create() call
	public static function create()
	{
		if ( !isset(self::$obj) )
		{
			self::$obj = new logfiles_conf();
		}
		return self::$obj;
	}

	public function __construct()
	{
		self::$obj = $this;
	}

	// return an array of filenames to write
	function get_filename()
	{
		return array(
			'logger_general_additional.conf',
			'logger_logfiles_additional.conf',
		);
	}

	// return the output that goes in each of the files
	function generateConf($file)
	{
		global $version, $amp_conf;

		switch ($file)
		{
			case 'logger_general_additional.conf':
				return $this->generate_loggergeneral_additional($version);
				break;

			case 'logger_logfiles_additional.conf':
				return $this->generate_loggerlogfiles_additional($version);
				break;
		}
	}


	function addLoggerGeneral($key, $value)
	{
		$this->_loggergeneral[] = array('key' => $key, 'value' => $value);
	}

	function generate_loggergeneral_additional($ast_version)
	{
		$output = '';

		if (isset($this->_loggergeneral) && is_array($this->_loggergeneral))
		{
			foreach ($this->_loggergeneral as $values)
			{
				$output .= $values['key'] . '=' . $values['value'] . "\n";
			}
		}

		return $output;
	}

	function addLoggerLogfiles($key, $value)
	{
		$this->_loggerlogfiles[] = array('key' => $key, 'value' => $value);
	}

	function generate_loggerlogfiles_additional($ast_version)
	{
		$output = '';

		if ( isset($this->_loggerlogfiles) && is_array($this->_loggerlogfiles) )
		{
			foreach ($this->_loggerlogfiles as $values)
			{
				$output .= $values['key'] . ' => ' . $values['value'] . "\n";
			}
		}

		return $output;
	}

}
