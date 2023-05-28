<div id="toolbar-all">
	<a href="?display=customextens" class="btn btn-default"><i class="fa fa-list"></i> <?php echo _("List Custom Extensions")?></a>
	<a href="?display=customextens&amp;view=form" class="btn btn-default"><i class="fa fa-plus"></i> <?php echo _("Add Extension")?></a>
</div>
 <table id="destgrid-side" data-url="ajax.php?module=customappsreg&amp;command=getJSON&amp;jdata=extensgrid" data-cache="false" data-toolbar="#toolbar-all" data-toggle="table" data-search="true" class="table">
    <thead>
            <tr>
            <th data-field="custom_exten" data-sortable="true"><?php echo _("Extension")?></th>
            <th data-field="description"><?php echo _("Description")?></th>
        </tr>
    </thead>
</table>
