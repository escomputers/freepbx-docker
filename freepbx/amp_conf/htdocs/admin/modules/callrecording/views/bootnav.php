<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2015 Sangoma Technologies.
//?>
<div id="toolbar-recrnav">
  <a href="config.php?display=callrecording" class="btn btn-default"><i class="fa fa-list"></i>&nbsp; <?php echo _("List Call Recordings") ?></a>
  <a href="config.php?display=callrecording&view=form" class="btn btn-default"><i class="fa fa-plus"></i>&nbsp; <?php echo _("Add Call Recording") ?></a>
</div>
<table data-url="ajax.php?module=callrecording&amp;command=getJSON&amp;jdata=grid"
  data-cache="false"
  data-toolbar="#toolbar-recrnav"
  data-toggle="table"
  data-search="true"
  data-escape="true" 
  class="table"
  id="table-all-side">
    <thead>
        <tr>
            <th data-sortable="true" data-field="description"><?php echo _('Rule')?></th>
        </tr>
    </thead>
</table>
<script type="text/javascript">
  $("#table-all-side").on('click-row.bs.table',function(e,row,elem){
    window.location = '?display=callrecording&view=form&extdisplay='+row['callrecording_id'];
  })
</script>
