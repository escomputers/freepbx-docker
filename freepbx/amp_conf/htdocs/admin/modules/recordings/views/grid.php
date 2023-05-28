<?php if(empty($langs)) { ?>
	<div class="alert alert-danger"><?php echo sprintf(_("You have no sound packages installed in the %s module. Please install at least one language to use System Recordings"),"<a href='?display=soundlang'>"._("Sound Languages")."</a>")?></div>
<?php } ?>
<script>var langs = <?php echo json_encode($langs)?></script>
<div class="container-fluid">
	<div class="row">
		<div class="col-sm-12">
			<div id="toolbar-all">
				<a class="btn btn-primary" href="?display=recordings&amp;action=add"><i class="fa fa-plus"></i> <?php echo _("Add Recording")?></a>
			</div>
			<table id="mygrid"
				data-url="ajax.php?module=recordings&amp;command=grid"
				data-cache="false"
				data-cookie="true"
				data-cookie-id-table="recordings-all"
				data-toolbar="#toolbar-all"
				data-maintain-selected="true"
				data-show-columns="true"
				data-show-toggle="true"
				data-toggle="table"
				data-pagination="true"
				data-escape="true" 
				data-search="true"
				class="table table-striped">
				<thead>
					<tr>
						<th data-sortable="true" data-field="displayname"><?php echo _("Display Name")?></th>
						<th data-sortable="true" data-field="description"><?php echo _("Description")?></th>
						<th data-sortable="true" data-field="languages"><?php echo _("Supported Languages")?></th>
						<th data-formatter="linkFormatter"><?php echo _("Actions")?></th>
					</tr>
				</thead>
			</table>
		</div>
	</div>
</div>
