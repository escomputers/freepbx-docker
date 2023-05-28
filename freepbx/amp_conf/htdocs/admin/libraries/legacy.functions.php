<?php
/** Legacy proceedural functions **/

/**
 * Returns true if extension is within allowed range
 * Depreciated
 * @param int $extension Extension number
 */
function checkRange($extension){
	$high = '';
	$low = '';
	if(isset($_SESSION["AMP_user"]) && is_object($_SESSION["AMP_user"])){
		$low = $_SESSION["AMP_user"]->getExtensionLow();
		$high = $_SESSION["AMP_user"]->getExtensionHigh();
	} else {
		return true;
	}
	if ((($extension >= $low) && ($extension <= $high)) || ($low == '' && $high == '')){
		return true;
	}else{
		return false;
	}
}

/**
 * Legacy Check AMP Administrators Department
 * ALWAYS return true here now forever
 * @param string $dept department
 */
function checkDept($dept){
	return true;
}
