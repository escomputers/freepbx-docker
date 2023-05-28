function checkCallRecording(theForm) {
	var msgInvalidDescription =  _('Invalid description specified');
	var duplicate = _('Description name already exists ');
	// set up the Destination stuff
	setDestinations(theForm, '_post_dest');

	// form validation
	defaultEmptyOK = false;
	if (isEmpty(theForm.description.value)){
		return warnInvalid(theForm.description, msgInvalidDescription);
	}

	if (callrecordings.indexOf(theForm.description.value) >= 0) {
		return warnInvalid(theForm.description,duplicate);
	}
	if (!validateDestinations(theForm, 1, true)){
		return false;
	}

	if($.inArray(theForm.description.value, description) != -1){
		return warnInvalid($('input[name=description]'),  sprintf(_("%s already used, please use a different description."),theForm.description.value));
	}
	return true;
}

function linkFormatter(value, row, index) {
	return decodeHTML(value);
}

function decodeHTML(data) {
	var textArea = document.createElement('textarea');
	textArea.innerHTML = data;
	return textArea.value;
}
