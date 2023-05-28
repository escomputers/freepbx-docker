<?php
	$logfiles = \FreePBX::create()->Logfiles;
	echo $logfiles->showPage("logs");
