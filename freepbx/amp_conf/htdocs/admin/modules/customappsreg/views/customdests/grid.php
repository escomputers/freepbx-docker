<?php
$dataurl = "ajax.php?module=customappsreg&command=getJSON&jdata=destgrid";
?>
<div id="toolbar-all">
<a href="?display=customdests&view=form" class="btn btn-default"><i class="fa fa-plus"></i> <?php echo _("Add Destination")?></a>
</div>
 <table id="destgrid" data-url="<?php echo $dataurl?>" data-cache="false" data-toolbar="#toolbar-all" data-maintain-selected="true" data-show-columns="true" data-show-toggle="true" data-toggle="table" data-pagination="true" data-search="true" class="table table-striped">
    <thead>
            <tr>
            <th data-field="description"><?php echo _("Destinations")?></th>
            <th data-field="destid" data-formatter="linkFormatter"><?php echo _("Actions")?></th>
        </tr>
    </thead>
</table>

<script type="text/javascript">
function linkFormatter(value){
  var html = '<a href="?display=customdests&view=form&destid='+value+'"><i class="fa fa-pencil"></i></a>';
  html += '&nbsp;<a href="?display=customdests&action=delete&destid='+value+'" class="delAction"><i class="fa fa-trash"></i></a>';
  return html;
}
</script>
