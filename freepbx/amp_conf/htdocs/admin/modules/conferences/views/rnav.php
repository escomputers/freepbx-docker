<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2015 Sangoma Technologies.
//
$confs = conferences_list();
foreach ($confs as $conf) {
	$crows .= '<tr>';
	$crows .= '<td>';
	$crows .= $conf[0];
	$crows .= '</td>';
	$crows .= '<td>';
	$crows .= $conf[1];
	$crows .= '</td>';
	$crows .= '</tr>';
}
?>
<div id="toolbar-all-side">
	<a href="config.php?display=conferences" class="btn"><i class="fa fa-list"></i>&nbsp; <?php echo _("List Conferences") ?></a>
	<a class="btn btn-primary" href="config.php?display=conferences&amp;view=form"><i class="fa fa-plus"></i> <?php echo _("Add")?></a>
</div>
<table id="conferences-side" data-toolbar="#toolbar-all-side" data-search="true" data-toggle="table" class="table">
<thead>
	<tr>
		<th data-sortable="true"><?php echo _("Conference")?></th>
		<th data-sortable="true"><?php echo _("Description")?></th>
	</tr>
</thead>
<tbody>
	<?php echo $crows ?>
</tbody>
</table>
