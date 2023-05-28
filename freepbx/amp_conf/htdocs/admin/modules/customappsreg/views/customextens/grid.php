<div id="toolbar-all">
  <a href="?display=customextens&amp;view=form" class="btn btn-default"><i class="fa fa-plus"></i> <?php echo _("Add Extension")?></a>
</div>
 <table id="destgrid" data-url="ajax.php?module=customappsreg&amp;command=getJSON&amp;jdata=extensgrid" data-cache="false" data-toolbar="#toolbar-all" data-maintain-selected="true" data-show-columns="true" data-show-toggle="true" data-toggle="table" data-pagination="true" data-search="true" class="table table-striped">
    <thead>
            <tr>
            <th data-field="custom_exten" data-sortable="true"><?php echo _("Extension")?></th>
            <th data-field="description"><?php echo _("Description")?></th>
            <th data-field="custom_exten" data-formatter="linkFormatter"><?php echo _("Actions")?></th>
        </tr>
    </thead>
</table>

<script type="text/javascript">
function linkFormatter(value){
  var html = '<a href="?display=customextens&view=form&extdisplay='+encodeURIComponent(value)+'"><i class="fa fa-pencil"></i></a>';
  html += '&nbsp;<a href="?display=customextens&action=delete&extdisplay='+encodeURIComponent(value)+'" class="delAction"><i class="fa fa-trash"></i></a>';
  return html;
}
</script>
