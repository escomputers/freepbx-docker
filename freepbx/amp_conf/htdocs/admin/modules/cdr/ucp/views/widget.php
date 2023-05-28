<div class="col-md-12">
	<?php if(!empty($message)) { ?>
		<div class="alert alert-<?php echo $message['type']?>"><?php echo $message['message']?></div>
	<?php } ?>
	<table class="cdr-grid"
		data-url="ajax.php?module=cdr&amp;command=grid&amp;extension=<?php echo htmlentities($ext)?>"
		data-cache="false"
		data-cookie="true"
		data-cookie-id-table="ucp-cdr-table"
		data-maintain-selected="true"
		data-show-columns="true"
		data-show-toggle="true"
		data-toggle="table"
		data-pagination="true"
		data-search="true"
		data-sort-order="desc"
		data-sort-name="timestamp"
		data-side-pagination="server"
		data-show-refresh="true"
		data-silent-sort="false"
		data-mobile-responsive="true"
		data-check-on-init="true"
		data-show-export="false"
		class="table table-hover">
		<thead>
			<tr class="cdr-header">
				<th data-field="timestamp" data-sortable="true" data-formatter="UCP.Modules.Cdr.formatDate"><?php echo _("Date")?></th>
				<th data-field="text" data-sortable="true" data-formatter="UCP.Modules.Cdr.formatDescription"><?php echo _("Description")?></th>
				<th data-field="duration" data-sortable="true" data-formatter="UCP.Modules.Cdr.formatDuration"><?php echo _("Duration")?></th>
				<?php if($showPlayback) { ?>
					<th data-field="playback" data-formatter="UCP.Modules.Cdr.formatPlayback"><?php echo _("Playback")?></th>
				<?php } ?>
				<th data-field="controls" data-formatter="UCP.Modules.Cdr.formatActions"><?php echo _("Controls")?></th>
			</tr>
		</thead>
	</table>
</div>
