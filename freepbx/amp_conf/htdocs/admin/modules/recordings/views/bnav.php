<div class="container-fluid">
	<div class="row">
		<div class="col-sm-12">
			<div id="toolbar-all">
				<a class="btn btn-primary" href="?display=recordings&amp;action=add"><i class="fa fa-plus"></i> <?php echo _("Add Recording")?></a>
				<a class="btn btn-primary" href="?display=recordings"><i class="fa fa-list"></i> <?php echo _("List Recordings")?></a>
			</div>
			<table id="bnav-grid"
				data-url="ajax.php?module=recordings&amp;command=grid"
				data-cache="false"
				data-toolbar="#toolbar-all"
				data-maintain-selected="true"
				data-toggle="table"
				data-pagination="false"
				data-search="true"
				data-escape="true" 
				class="table table-striped">
				<thead>
					<tr>
						<th data-sortable="true" data-field="displayname"><?php echo _("Display Name")?></th>
						<th data-sortable="true" data-field="description"><?php echo _("Description")?></th>
						<th data-field="languages"><?php echo _("Supported Languages")?></th>
					</tr>
				</thead>
			</table>
		</div>
	</div>
</div>
