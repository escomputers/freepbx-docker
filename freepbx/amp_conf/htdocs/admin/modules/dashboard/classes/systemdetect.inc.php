<?php
// vim: set ai ts=4 sw=4 ft=php:
//
// License for all code of this FreePBX module can be found in the license file inside the module directory
// Copyright 2006-2014 Schmooze Com Inc.

// Intelligence goes here to detect system types.
// This should be included from every class in Dash2.

if (PHP_OS == "FreeBSD") {
	$this->systemtype = "freebsd";
} else {
	$this->systemtype = "linux";
}
