<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2015 Sangoma Technologies.
foreach (callrecording_list() as $row) {
	$arows .= '<tr><td>';
	$arows .= $row['description'];
	$arows .= '</td>';
	$arows .= '<td>';
	$arows .= '<a href="?display=callrecording&view=form&extdisplay='.$row['callrecording_id'].'"><i class="fa fa-edit"></i></a>';
	$arows .= '&nbsp;';
	$arows .= '<a href="config.php?display=callrecording&amp;callrecording_id='.$row['callrecording_id'].'&amp;action=delete"><i class="fa fa-trash"></i></a>';
	$arows .= '</td></tr>';
}

?>
<div id="toolbar-all">
	<a href="config.php?display=callrecording&view=form" class="btn btn-default"><i class="fa fa-plus"></i>&nbsp; <?php echo _("Add Call Recording") ?></a>
</div>
<table data-toggle="table" data-toolbar="#toolbar-all" data-escape="true" data-pagination="true" data-search="true" class="table table-striped">
<thead>
	<tr>
		<th><?php echo _("Description")?></th>
		<th data-formatter="linkFormatter"><?php echo _("Actions")?></th>
	</tr>
</thead>
<tbody>
	<?php echo $arows ?>
</tbody>
</table>
