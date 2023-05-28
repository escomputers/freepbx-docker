<div id="moh-rnav">
<a href="?display=music" class="btn btn-default"><i class="fa fa-th-list"></i> <?php echo _("List MoH Categories")?></a>
<a class="btn" href="?display=music&amp;action=add"><i class="fa fa-plus"></i> <?php echo _("Add Category")?></a>
</div>

<table data-escape="true" data-url="ajax.php?module=music&amp;command=getJSON&amp;jdata=categories" data-cache="false" data-toggle="table" data-search="true" data-toolbar="#moh-rnav" class="table" id="table-all-side">                                                                                                                                              
    <thead>
        <tr>
	    <th data-field="category" data-sortable="true"><?php echo _("Category")?></th>
        </tr>
    </thead>
</table>
<script type="text/javascript">
  $("#table-all-side").on('click-row.bs.table',function(e,row,elem){
    window.location = '?display=music&action=edit&id='+row['id'];
  })
</script>
