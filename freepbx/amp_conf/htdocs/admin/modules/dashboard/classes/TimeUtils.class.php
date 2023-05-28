<?php
// vim: set ai ts=4 sw=4 ft=php:
//
// License for all code of this FreePBX module can be found in the license file inside the module directory
// Copyright 2006-2014 Schmooze Com Inc.

class TimeUtils {
	public static function getReadable($secs, $significance = 9) {
		$year = _("year");
		$year_plural = _("years");
		$month = _("month");
		$month_plural = _("months");
		$week = _("week");
		$week_plural = _("weeks");
		$day = _("day");
		$day_plural = _("days");
		$hour = _("hour");
		$hour_plural = _("hours");
		$minute = _("minute");
		$minute_plural = _("minutes");
		$second = _("second");
		$second_plural = _("seconds");
		$zero_seconds = _("0 seconds");
		
		$units = array(
			"year"   => array( "divisor" => 31536000, "one" => $year, "many" => $year_plural),   /* day * 365 */
			"month"  => array( "divisor" =>  2628000, "one" => $month, "many" => $month_plural), /* year / 12 */
			"week"   => array( "divisor" =>   604800, "one" => $week, "many" => $week_plural),   /* day * 7  */
			"day"    => array( "divisor" =>    86400, "one" => $day, "many" => $day_plural),     /* hour * 24 */
			"hour"   => array( "divisor" =>     3600, "one" => $hour, "many" => $hour_plural),   /* 60 * 60 */
			"minute" => array( "divisor" =>       60, "one" => $minute, "many" => $minute_plural),
			"second" => array( "divisor" =>        1, "one" => $second, "many" => $second_plural),
		);

		// specifically handle zero
		if ( $secs == 0 ) return $zero_seconds;

		$s = "";

		foreach ( $units as $unit ) {
			if ( $quot = intval($secs / $unit['divisor']) ) {
				if (abs($quot) > 1) {
					$s .= $quot." ".$unit['many'];
				} else {
					$s .= $quot." ".$unit['one'];
				}
				$s .= ", ";
				$secs -= $quot * $unit['divisor'];
			}
		}

		// Check to see if we want to drop off some least significant strings.
		$tmparr = explode(',', $s);
		while (count($tmparr) > $significance) {
			array_pop($tmparr);
		}

		return join(',', $tmparr);
	}
}
