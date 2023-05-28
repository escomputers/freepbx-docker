//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2015 Sangoma Technologies.
//
$("#conferences-side").on("click-row.bs.table", function(row, $element) {
	window.location = "?display=conferences&view=form&extdisplay="+$element[0];
});
//Javascript moved from page.conferences.php
var theForm = document.editMM;
if(theForm){
	if (theForm.account.value === "") {
		theForm.account.focus();
	} else {
		theForm.name.focus();
	}
}

function checkConf()
{
	var msgInvalidConfNumb = _('Please enter a valid Conference Number');
	var msgInvalidConfName = _('Please enter a valid Conference Name');
	var msgNeedAdminPIN = _('You must set an admin PIN for the Conference Leader when selecting the leader wait option');
	var msgInvalidMuteOnJoin = _('You must set Allow Menu to Yes when not using a Leader or Admin in your conference, otherwise you will be unable to unmute yourself');
	var msgMatchingPins = _('The user and admin can not have the same pin code.');

	defaultEmptyOK = false;
	if (!isInteger(theForm.account.value))
		return warnInvalid(theForm.account, msgInvalidConfNumb);

	if (!isAlphanumeric(theForm.name.value))
		return warnInvalid(theForm.name, msgInvalidConfName);

	// update $options
	var theOptionsFld = $('#options');
	var ops = [];
	$('[id^="opt_"]:checked').each(function(){
		if($(this).val() != "NO"){
			ops.push($(this).val());
		}
	});
	$("#options").val(ops.join(""));


	// not possible to have a 'leader' conference with no adminpin
	if (theForm.options.value.indexOf("w") > -1 && theForm.adminpin.value === "")
		return warnInvalid(theForm.adminpin, msgNeedAdminPIN);
	//Users and Admin should not have the same pin
	if (theForm.adminpin.value == theForm.userpin.value && theForm.adminpin.value.length > 0)
		return warnInvalid(theForm.userpin, msgMatchingPins);

	// should not have a conference with no 'leader', mute on join, and no allow menu, so let's complain
	if ($('[name=opt_m]').val() !== '' && $('[name=adminpin]').val() === '' && !$('[name=opt_s]').val())
		return warnInvalid(theForm.options, msgInvalidMuteOnJoin);

	return true;
}
