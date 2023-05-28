<?php
/**
 * Read file with tail and be able to obtain in another 
 * query only the new lines.
 *
 * @author Javier Pastor (VSC55)
 * @license GPLv3
 */

namespace FreePBX\modules\Logfiles;

class Tail
{
	private $NAME_SESSION = "tail_file";

	private $file;
    private $lines;
	private $out;
	private $id_tail;

	public function __construct($filename, $lines, $id_tail = null)
	{
		$this->file     = $filename;
		$this->lines    = intval($lines);

		if ($id_tail)
		{
			$this->set_id_tail($id_tail);
		}
		else
		{
			$this->generate_id_tail();
		}

		$this->init();
	}

	private function init()
	{
		@session_start();
		$this->create_array_session();
		$this->clean_session();
		$this->reset_out();
	}

	private function create_array_session()
	{
		if ( ! isset($_SESSION[$this->NAME_SESSION]) )
		{
			$_SESSION[$this->NAME_SESSION] = array();
		}
		if ( ! isset($_SESSION[$this->NAME_SESSION][$this->get_id_tail()]) )
		{
			$_SESSION[$this->NAME_SESSION][$this->get_id_tail()] = array();
		}
		if ( ! isset($_SESSION[$this->NAME_SESSION][$this->get_id_tail()][$this->file]) )
		{
			$_SESSION[$this->NAME_SESSION][$this->get_id_tail()][$this->file] = array();
		}
	}

	private function count()
	{
		//We count the number of lines in the file
		//We don't use file() since it consumes a lot of memory with large files.
		$num_lines_file = 0;
		$fp = fopen($this->file, "r");
		while ( ! feof($fp) )
		{
			fgets($fp);
			$num_lines_file++;
		}
		fclose($fp);
		return $num_lines_file;
	}

	private function reset_out() 
	{
		$this->out = array(
			'type' => 'NONE',
			'count_all'=> 0,
			'count_new'=> 0,
			'count_old'=> 0,
		);
	}

	private function clean_session()
	{
		$this->create_array_session();
		$_SESSION[$this->NAME_SESSION][$this->get_id_tail()][$this->file] = array (
			'name'  => NULL,
			'lines' => -1
		);
		// // session_destroy();
	}

	private function get_session($option, $default = "")
	{
		$data_return = $default;
		$this->create_array_session();
		if ( array_key_exists ( $option, $_SESSION[$this->NAME_SESSION][$this->get_id_tail()][$this->file] ) )
		{
			$data_return = $_SESSION[$this->NAME_SESSION][$this->get_id_tail()][$this->file][$option];
		}
		return $data_return;
	}

	private function set_session($option, $value)
	{
		$this->create_array_session();
		$_SESSION[$this->NAME_SESSION][$this->get_id_tail()][$this->file][$option] = $value;
	}

	public function out($new_session) 
	{
		$this->reset_out();

		//We check and set the session variables for the resume option.
		if ( $this->file != $this->get_session('name') || $new_session )
		{
			$this->clean_session();
		}
		$this->set_session('name', $this->file);

		$type_out			 = "NONE";
		$count_lines_file 	 = $this->count();
		$count_lines_session = $this->get_session('lines');
		$count_new 			 = $count_lines_file - $count_lines_session;

		if ( $count_new < 0 || $count_lines_session < 0 )
		{
			// New reading or the file has been cleaned.
			// - File Clean    => $count_new < 0
			// - New read file => $count_lines_session < 0
            $num_lines_read = $this->lines;

            $type_out = ($count_new < 0 ? "CLEAN_FILE": "FIRS_READ");
		}
		else if ( $count_new > 0 )
		{
			// It is not a new reading and there is new data.
            $num_lines_read = $count_new;
            $type_out = "NEW_LINES";
		}
		else
		{
			//There are no changes
            $num_lines_read = 0;
            $type_out = "EQUAL";
		}
		$this->set_session('lines', $count_lines_file);

		$out_log = array();
		if ($num_lines_read > 0) 
		{
			$cmd = sprintf("%s -n -%d %s", fpbx_which('tail'), $num_lines_read, $this->file);
			exec($cmd, $out_log);
		}

		// Update info out
		$this->out['type'] 		 = $type_out;
		$this->out['count_all']  = $count_lines_file;
		$this->out['count_old']  = $count_lines_session;
		$this->out['count_new']  = $count_new;
		$this->out['count_read'] = $num_lines_read;
		$this->out['session_id'] = $this->get_id_tail();

		return $out_log;
    }
	
	public function get_info_out($option = null, $default = null)
	{
		$return_data = $default;
		if ($option === null || empty( trim($option) ) )
		{
			$return_data = $this->out;
		}
		else
		{
			$option = trim($option);
			if ( array_key_exists($option, $this->out) )
			{
				$data_return = $this->out[$option];
			}
		}
		return $return_data;
	}

	public function get_id_tail()
	{
		return $this->id_tail;
	}

	public function set_id_tail($newid)
	{
		$this->id_tail = $newid;
	}

	public function generate_id_tail()
	{
		$this->set_id_tail( uniqid() );
	}

}
