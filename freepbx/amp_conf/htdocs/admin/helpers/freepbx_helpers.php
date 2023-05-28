<?php
/**
 * TODO: This needs to go away in 14. Remove useless functions
 * move drawselects to utilities or view (pref view)
 */

/**
 * short FreePBX Label generator
 * long Function  used to generate FreePBX 'labels' that can
 * show a help popup when moused over
 *
 * @author Moshe Brevda <mbrevda@gmail.com>
 * @param string $text
 * @param string $help
 * @return string
 * @todo change format to take advantage of html's data attribute. No need for spans!
 *
 */
function fpbx_label($text, $help = '') {
	if ($help) {
		$ret = '<a href="#" class="info" tabindex="-1">'
				. $text
				. '<span>'
				. $help
				. '</span></a>';
	} else {
		$ret = $text;
	}

	return $ret;
}

/**
 * short FreePBX Section header generator
 * long Function used to generate FreePBX GUI sections
 *
 * @author Philippe Lindheimer
 * @param string $text
 * @return string
 */
function fpbx_section_header($text) {
	return '<br><h5>' . $text . '<hr></h5>';
}

/**
 * Text Input Field With Enable/Disable Checkbox
 *
 * @access	public
 * @param	mixed
 * @param	string
 * @param	string
 * @param	string
 * @param	string
 * @param	bool
 * @return	string
 */
function fpbx_form_input_check($data = '', $value = '', $extra = '', $label = 'Enable', $disabled_value = 'DEFAULT', $check_enables = true) {
	if (!is_array($data)) {
		$data['name'] = $data['id'] = $data;
	}
	if (!isset($data['id'])) {
		$data['id'] = $data['name'];
	}
	if (!isset($data['value'])) {
		$data['value'] = $value;
	}
	if (!empty($data['disabled'])) {
		$data['value'] = $disabled_value;
	}
  $cbdata['name'] = $data['name'] . '_cb';
  $cbdata['id'] = $data['id'] . '_cb';
  $cbdata['checked'] = isset($data['disabled']) ? !$data['disabled'] : true;
	$cbdata['data-disabled'] = $disabled_value;
	if ($check_enables) {
  	$cbdata['class'] = "input_checkbox_toggle_false";
	} else {
  	$cbdata['class'] = "input_checkbox_toggle_true";
  	$cbdata['checked'] = ! $cbdata['checked'];
	}
	return form_input($data) . form_checkbox($cbdata) . form_label($label, $cbdata['id']);
}
// ------------------------------------------------------------------------

/**
 * Destination drawselects.
 *
 * This is where the magic happens. Query all modules for valid destinations
 * Then build a javascript based multi-select box.
 * Hide the second select box until the first is selected.
 * Auto-populate the second based on the first.
 *
 * The first is almost always a module name, though it can be custom as well.
 * The second is the actually destination
 *
 * @param  string $goto             The current goto destination setting. EG: ext-local,2000,1
 * @param  int $i                   the destination set number (used when drawing multiple destination sets in a single form ie: digital receptionist)
 * @param  array $restrict_modules  Array of modules or array of modules with ids to restrict getting destinations from
 * @param  bool $table              Wrap this in a table row using <tr> and <td> (deprecated should not be used in 13+)
 * @param  string $nodest_msg       No Destination selected message
 * @param  bool $required           Whether the destination is required to be set
 * @param  bool $output_array       Output an array instead of html (you will need to make sure the html is correct later on for the functionality of this to work correctly)
 * @param  bool $reset              Reset the drawselect_* globals (useful when using multiple destination dropdowns on a page, each with their own restricted modules)
 * @param  bool $disable            Set html element to disabled on creation
 * @param  string $class            String of classes to add to to the html element (class="<string>")
 * @return mixed                    Array if $output_array is true otherwise a string of html
 */
function drawselects($goto, $i, $restrict_modules=false, $table=true, $nodest_msg='', $required = false, $output_array = false, $reset = false, $disable=false, $class='') {
	set_time_limit(60);
	return \FreePBX::View()->destinationDrawSelect($goto, $i, $restrict_modules, $table, $nodest_msg, $required, $output_array, $reset, $disable, $class);
}


/**
 * This function will get the MySQL field size of the specified fieldname
 * It's useful for finding out the limit of certain fields in MySQL so that
 * we can do validation checks on strings to make sure they aren't too long.
 * This will help prevent MySQL from needing to do auto chopping on lengthy strings
 * which causes problems with multibyte characters getting cut off abruptly.
 * The third argument defaultsize is just to futureproof in case someone decides
 * to change things in MySQL in the future that would otherwise just pass null back
 * and cause a bug.
 * @param  string $tablename   The table name
 * @param  string $fieldname   The fieldname
 * @param  string $defaultsize Default Size of the field
 * @return string              The default size
 */
function module_get_field_size($tablename, $fieldname, $defaultsize) {
	global $db;

	$sql = "SELECT character_maximum_length FROM information_schema.columns WHERE table_name = ? AND column_name = ?";

	$results = $db->getAll($sql, array($tablename, $fieldname));

	if(DB::IsError($results)) {
		$results = null;
	}

	return isset($results)?$results[0][0]:$defaultsize;
}
