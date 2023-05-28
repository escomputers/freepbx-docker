$("#destgrid-side").on("click-row.bs.table", function(row, $element) {
	window.location = "config.php?display=customextens&view=form&extdisplay="+$element.custom_exten;
});
