<script type="text/javascript">
    $(document).ready(function () {
        let obj = {};
        $("input[name='voicemail_vmrecords']").click(function(){
            obj = {
        	        'voicemail': $("#modulesetting_voicemail").serializeArray()
        	}
            $('#backup_items').val(JSON.stringify(processItems(undefined, obj)));

        });
        $("input[name='voicemail_vmgreetings']").click(function(){
            obj = {
                    'voicemail': $("#modulesetting_voicemail").serializeArray()
            }
            $('#backup_items').val(JSON.stringify(processItems(undefined, obj)));
        });
	    var dbRecValue = <?php echo json_encode($voicemail_vmrecords) ?>;
	    var dbGreValue = <?php echo json_encode($voicemail_vmgreetings) ?>;
	    var items =  $("input[name='backup_items']").val();
        var mod = JSON.parse(items).find(item => item.modulename === "voicemail");
        var vmRecToggle = (mod && mod.settings.length > 0) ? mod.settings[0].value : dbRecValue;
        var vmGreetToggle =  (mod && mod.settings.length > 0) ? mod.settings[1].value : dbGreValue;
        (vmRecToggle && vmRecToggle === "yes") ? $('#voicemail_vmrecordsyes').attr('checked', true) : $('#voicemail_vmrecordsno').attr('checked', true);
        (vmGreetToggle && vmGreetToggle === "yes") ? $('#voicemail_vmgreetingsyes').attr('checked', true) : $('#voicemail_vmgreetingsno').attr('checked', true);
	});
</script>

<!--Restore Advanced Settings-->
<div class="element-container">
	<div class="row">
		<div class="form-group">
			<div class="col-md-6">
				<label class="control-label" for="voicemail_vmrecords"><?php echo _("Exclude VM Recordings?") ?></label>
			</div>
			<div class="col-md-6">
				<span class="radioset">
                    <?php $voicemail_vmrecords = isset($voicemail_vmrecords) ? $voicemail_vmrecords : 'no'?>
					<input type="radio" name="voicemail_vmrecords" id="voicemail_vmrecordsyes" value="yes" <?php echo ($voicemail_vmrecords == "yes"?"CHECKED":"") ?> >
					<label for="voicemail_vmrecordsyes"><?php echo _("Yes");?></label>
					<input type="radio" name="voicemail_vmrecords" id="voicemail_vmrecordsno" value="no" <?php echo ($voicemail_vmrecords == "yes"?"":"CHECKED") ?> >
					<label for="voicemail_vmrecordsno"><?php echo _("No");?></label>
				</span>
			</div>
		</div>
	</div><br/>

	<div class="row">
    		<div class="form-group">
    			<div class="col-md-6">
    				<label class="control-label" for="voicemail_vmgreetings"><?php echo _("Exclude VM Greetings?") ?></label>
    			</div>
    			<div class="col-md-6">
    				<span class="radioset">
                        <?php $voicemail_vmgreetings = isset($voicemail_vmgreetings) ? $voicemail_vmgreetings : 'no'?>
    					<input type="radio" name="voicemail_vmgreetings" id="voicemail_vmgreetingsyes" value="yes" <?php echo ($voicemail_vmgreetings == "yes"?"CHECKED":"") ?> >
    					<label for="voicemail_vmgreetingsyes"><?php echo _("Yes");?></label>
    					<input type="radio" name="voicemail_vmgreetings" id="voicemail_vmgreetingsno" value="no" <?php echo ($voicemail_vmgreetings == "yes"?"":"CHECKED") ?> >
    					<label for="voicemail_vmgreetingsno"><?php echo _("No");?></label>
    				</span>
    			</div>
    		</div>
    </div>
</div>
<!--END Restore Advanced Settings-->
