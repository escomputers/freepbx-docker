<?php  /* $Id$ */
outn("Upgrading extensions table..");

$sql = "ALTER TABLE `extensions` CHANGE `priority` `priority` VARCHAR( 5 ) DEFAULT '1' NOT NULL";
$results = $db->query($sql);
if (DB::IsError($results)) {
	die($results->getMessage());
}
out("OK");


out("Checking extension hints..");

// Find all the extensions in ext-local then don't have a match 'hint' priority
$sql = "SELECT DISTINCT extension FROM extensions WHERE context = 'ext-local' ORDER BY extension";
$extensions = $db->getAll($sql);
if(DB::IsError($extensions)) {
        die($results->getMessage());
}

$toupgradectr = 0;
foreach ($extensions as $extkey=>$extvalue) {
	$extnum = $extvalue[0];
	outn("  extension $extnum...");
	$sql = "SELECT extension FROM extensions WHERE context = 'ext-local' AND priority = 'hint' AND extension = $extnum";
	$results = $db->getAll($sql);
	if (DB::IsError($results)) {
		die($results->getMessage());
	}

	if (count($results) == 0) {
		$toupgradectr++;
		$exttoupgrade[] = $extnum;
		out("MISSING HINT");
	} else {
		out("OK");
	}
	
}
out("Found $toupgradectr to upgrade...");

if ($toupgradectr > 0) {
	out("Upgrading extension hints..");

	foreach ($exttoupgrade as $extnum) {
		outn("  extension $extnum...");
		$extds = get_dial_string($extnum);

		if ($extds != '') {
			//write database
			$sql = "INSERT INTO extensions (context, extension, priority, application) VALUES ('ext-local', $extnum, 'hint', '$extds')";
			$extres = $db->query($sql);
			if (DB::IsError($extres))
				die($extres->getMessage());
			out("DONE ($extds)");
		} else {
			out("**ERROR** Unrecognised technology type in E$extnum");
		}

	}
	out("Upgrading extension hints..OK");
}

function get_dial_string($extnum) {
	global $db;

	$ds = '';

	$sql = "SELECT value FROM globals WHERE variable = 'E$extnum'";
	$result = $db->getAll($sql);
	if (DB::IsError($result))
		die($result->getMessage());

	if (count($result) > 0) {
		$exttech = $result[0][0];
		switch ($exttech) {
			case "SIP":
				$ds = "SIP/" . $extnum;
				break;
			case "IAX2":
				$ds = "IAX2/" . $extnum;
				break;
			case "ZAP":
				$sql = "SELECT value FROM globals WHERE variable = 'ZAPCHAN_$extnum'";
				$zapres = $db->getAll($sql);
				if (DB::IsError($zapres))
					die($zapres->getMessage());
				if (count($zapres) > 0)
					$zapchannel = $result[0][0];
				$ds = "Zap/" . $zapchannel;
				break;
			default:
				outn($exttech . "...");
				break;
		}
	}

	return $ds;
}
?>
