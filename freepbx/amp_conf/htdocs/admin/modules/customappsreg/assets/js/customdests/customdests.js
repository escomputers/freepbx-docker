$("#destgrid-side").on("click-row.bs.table", function(row, $element) {
	window.location = "config.php?display=customdests&view=form&destid="+$element.destid;
});
