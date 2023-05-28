// ***************************************************
// ** Client-side library functions                     **
// ***************************************************

/**
 * This will hide or show all the <select> elements on a page
 * @param {bool} b hide is true, otherwise inherit
 */
function hideSelects(b) {
	var allelems = document.all.tags("SELECT"), i = 0;
	if (allelems !== null) {
		for (i = 0; i < allelems.length; i++) {
			allelems[i].style.visibility = (b ? "hidden" : "inherit");
		}
	}
}

//Resets Drawselects
function resetDrawselects(){
	$(".destdropdown").each(function() {
		$(this).val($("this option:first").val())
		var v = $(this).val(),
				i = $(this).data("id");
		if(v !== "") {
			$(".destdropdown2").not("#" + v + i).addClass("hidden");
			$("#"+ v + i).removeClass("hidden");
		} else {
			$(".destdropdown2").addClass("hidden");
		}
	});
}

/*
 * setDrawselect - Sets draw select location.
 * @id = element id such as goto0
 * @val = destination such as Announcements,s,1
 */
function setDrawselect(id,val){
	var item = destinations[val];
	if(typeof item === "undefined"){
		resetDrawselects();
		return;
	}
	var idx = $('#'+id).data('id');
	$('#'+id+' > option[value="'+item.category.replace(" ","_")+'"]').prop("selected","selected");
	$("#"+id).trigger("change");
	$('#'+item.category+idx+' > option[value="'+item.destination+'"]').prop("selected","selected");
	$('#'+item.category+idx).trigger("change");
}

/**
 * these two 'do' functions are needed to assign to the onmouse events
 * @param {obj} event The jquery event
 */
function doHideSelects(event) {
	hideSelects(true);
}

/**
* these two 'do' functions are needed to assign to the onmouse events
* @param {obj} event The jquery event
*/
function doShowSelects(event) {
	hideSelects(false);
}

/**
 * call this function from forms that include module destinations
 * @param {obj} theForm  The Form
 * @param {int} numForms The number of destination forms to process (usually 1)
 */
function setDestinations(theForm,	numForms) {
	var formNum = 0, whichitem = 0;
	for (formNum; formNum < numForms; formNum++) {
		while (whichitem < theForm["goto" + formNum].length) {
			if (theForm["goto" + formNum][whichitem].checked) {
				theForm["goto" + formNum].value = theForm["goto" + formNum][whichitem].value;
			}
			whichitem++;
		}
	}
}

// ***************************************************
// ** CLIENT-SIDE FORM VALIDATION FUNCTIONS         **
// ***************************************************

// Defaults and Consts for validation functions
var whitespace = " \t\n\r", decimalPointDelimiter = ".", defaultEmptyOK = false;

/**
 * call this function to validate all your destinations
 * @param {obj} theForm   The Form
 * @param {int} numForms  is the number of destinatino forms to process (usually 1)
 * @param {bool} bRequired true|false if user must select something
 */
function validateDestinations(theForm, numForms, bRequired) {
	var valid = true, formNum = 0;

	for (formNum; formNum < numForms && valid === true; formNum++) {
		valid = validateSingleDestination(theForm, formNum, bRequired);
	}

	return valid;
}

/**
 * this will display a message, select the content of the relevent field and
 * then set the focus to that field.  finally return FALSE to the 'onsubmit' event
 *
 * @param {jquery object} theField javascript form object
 * @param {string} s        The Alert message
 * @param {string} stype	Alert Type "info|warning|success|error", if none is defined, an error will be used.
 */
function warnInvalid(theField, s, stype) {
	$(".element-container").removeClass("has-error has-warning has-success");
	$(".element-container .input-warn").remove();
	if (theField) {
		var field = (theField instanceof jQuery) ? theField : $(theField),
				id = field.prop("id"),
				type = 'unknown',
				tab = field.parents(".tab-pane").prop("id"),
				count = 0;
		//while loop here to tab switch through different layers
		while(typeof tab !== "undefined" && count < 5) {
			if($('li.change-tab[data-name="' + tab + '"] a').length) {
				$('li.change-tab[data-name="' + tab + '"] a').one("shown.bs.tab");
				$('li.change-tab[data-name="' + tab + '"] a').tab("show");
				tab = $('li.change-tab[data-name="' + tab + '"] a').parents(".tab-pane").prop("id");
			} else if($('li[role="presentation"] a[href="#' + tab + '"]').length) {
				$('li[role="presentation"] a[href="#' + tab + '"]').one("shown.bs.tab");
				$('li[role="presentation"] a[href="#' + tab + '"]').tab("show");
				tab = $('li[role="presentation"] a[href="#' + tab + '"]').parents(".tab-pane").prop("id");
			} else {
				tab = undefined;
			}
			//prevent spiralling out of control
			count++;
		}
		field.focus();
		field.parents(".element-container").addClass("has-error");
		if(field.is('input')) {
			type = 'input';
		} else if(field.is('select')) {
			type = 'select';
		}
		field.before('<i class="fa fa-exclamation-triangle input-warn" data-type="' + type + '" data-toggle="tooltip" data-placement="left" title="'+s+'"></i>');

		field.one("propertychange change contextmenu keyup input paste", function() {
			$(this).parents(".element-container").removeClass("has-error has-warning has-success");
			$(this).parents(".element-container").find(".input-warn").remove();
		});
	}
	if (typeof s !== "undefined" && s !== null && s !== "") {
		let stype_allow = ['info', 'warning', 'success', 'error'];
		if (typeof stype === "undefined" || stype === null || stype_allow.includes(stype.toLowerCase()) !== true) {
			stype = "error";
		}
		fpbxToast(s, '', stype.toLowerCase());
	}
	return false;
}

/**
 * String Check for Letters and Numbers only!
 * Note: that UTF-8 letters are allowed by calling the isUnicodeLetter check
 * @param {string} s The string to check
 */
function isAlphanumeric(s) {
	var i, c;
	if (isEmpty(s)) {
		if (isAlphanumeric.arguments.length == 1) {
			return defaultEmptyOK;
		} else {
			return (isAlphanumeric.arguments[1] === true);
		}
	}
	for (i = 0; i < s.length; i++) {
		c = s.charAt(i);
		if (!(isUnicodeLetter(c) || isDigit(c))) {
			return false;
		}
	}
	return true;
}

/**
* String Check for Letters and Numbers only!
* Note: that UTF-8 letters are allowed by calling the isUnicodeLetter check
* @param {string} s The string to check
*/
function isAlphanumericDot(s) {
	var i, c;
	if (isEmpty(s)) {
		if (isAlphanumeric.arguments.length == 1) {
			return defaultEmptyOK;
		} else {
			return (isAlphanumeric.arguments[1] === true);
		}
	}
	for (i = 0; i < s.length; i++) {
		c = s.charAt(i);
		if (!(isUnicodeLetter(c) || isDigit(c)) && c != ".") {
			return false;
		}
	}
	return true;
}

/**
 * This is a big regex that whitelists unicode letters
 * @param {string} c The string to check
 */
function isUnicodeLetter(c) {
	return new RegExp(/[ a-zA-Z'\\\/\&\(\)\-\u3000-\u3002\uFF10-\uFF19\xAA\xB5\xBA\xC0-\xD6\xD8-\xF6\xF8-\u02C1\u02C6-\u02D1\u02E0-\u02E4\u02EC\u02EE\u0370-\u0374\u0376\u0377\u037A-\u037D\u0386\u0388-\u038A\u038C\u038E-\u03A1\u03A3-\u03F5\u03F7-\u0481\u048A-\u0527\u0531-\u0556\u0559\u0561-\u0587\u05D0-\u05EA\u05F0-\u05F2\u0620-\u064A\u066E\u066F\u0671-\u06D3\u06D5\u06E5\u06E6\u06EE\u06EF\u06FA-\u06FC\u06FF\u0710\u0712-\u072F\u074D-\u07A5\u07B1\u07CA-\u07EA\u07F4\u07F5\u07FA\u0800-\u0815\u081A\u0824\u0828\u0840-\u0858\u08A0\u08A2-\u08AC\u0904-\u0939\u093D\u0950\u0958-\u0961\u0971-\u0977\u0979-\u097F\u0985-\u098C\u098F\u0990\u0993-\u09A8\u09AA-\u09B0\u09B2\u09B6-\u09B9\u09BD\u09CE\u09DC\u09DD\u09DF-\u09E1\u09F0\u09F1\u0A05-\u0A0A\u0A0F\u0A10\u0A13-\u0A28\u0A2A-\u0A30\u0A32\u0A33\u0A35\u0A36\u0A38\u0A39\u0A59-\u0A5C\u0A5E\u0A72-\u0A74\u0A85-\u0A8D\u0A8F-\u0A91\u0A93-\u0AA8\u0AAA-\u0AB0\u0AB2\u0AB3\u0AB5-\u0AB9\u0ABD\u0AD0\u0AE0\u0AE1\u0B05-\u0B0C\u0B0F\u0B10\u0B13-\u0B28\u0B2A-\u0B30\u0B32\u0B33\u0B35-\u0B39\u0B3D\u0B5C\u0B5D\u0B5F-\u0B61\u0B71\u0B83\u0B85-\u0B8A\u0B8E-\u0B90\u0B92-\u0B95\u0B99\u0B9A\u0B9C\u0B9E\u0B9F\u0BA3\u0BA4\u0BA8-\u0BAA\u0BAE-\u0BB9\u0BD0\u0C05-\u0C0C\u0C0E-\u0C10\u0C12-\u0C28\u0C2A-\u0C33\u0C35-\u0C39\u0C3D\u0C58\u0C59\u0C60\u0C61\u0C85-\u0C8C\u0C8E-\u0C90\u0C92-\u0CA8\u0CAA-\u0CB3\u0CB5-\u0CB9\u0CBD\u0CDE\u0CE0\u0CE1\u0CF1\u0CF2\u0D05-\u0D0C\u0D0E-\u0D10\u0D12-\u0D3A\u0D3D\u0D4E\u0D60\u0D61\u0D7A-\u0D7F\u0D85-\u0D96\u0D9A-\u0DB1\u0DB3-\u0DBB\u0DBD\u0DC0-\u0DC6\u0E01-\u0E30\u0E32\u0E33\u0E40-\u0E46\u0E81\u0E82\u0E84\u0E87\u0E88\u0E8A\u0E8D\u0E94-\u0E97\u0E99-\u0E9F\u0EA1-\u0EA3\u0EA5\u0EA7\u0EAA\u0EAB\u0EAD-\u0EB0\u0EB2\u0EB3\u0EBD\u0EC0-\u0EC4\u0EC6\u0EDC-\u0EDF\u0F00\u0F40-\u0F47\u0F49-\u0F6C\u0F88-\u0F8C\u1000-\u102A\u103F\u1050-\u1055\u105A-\u105D\u1061\u1065\u1066\u106E-\u1070\u1075-\u1081\u108E\u10A0-\u10C5\u10C7\u10CD\u10D0-\u10FA\u10FC-\u1248\u124A-\u124D\u1250-\u1256\u1258\u125A-\u125D\u1260-\u1288\u128A-\u128D\u1290-\u12B0\u12B2-\u12B5\u12B8-\u12BE\u12C0\u12C2-\u12C5\u12C8-\u12D6\u12D8-\u1310\u1312-\u1315\u1318-\u135A\u1380-\u138F\u13A0-\u13F4\u1401-\u166C\u166F-\u167F\u1681-\u169A\u16A0-\u16EA\u1700-\u170C\u170E-\u1711\u1720-\u1731\u1740-\u1751\u1760-\u176C\u176E-\u1770\u1780-\u17B3\u17D7\u17DC\u1820-\u1877\u1880-\u18A8\u18AA\u18B0-\u18F5\u1900-\u191C\u1950-\u196D\u1970-\u1974\u1980-\u19AB\u19C1-\u19C7\u1A00-\u1A16\u1A20-\u1A54\u1AA7\u1B05-\u1B33\u1B45-\u1B4B\u1B83-\u1BA0\u1BAE\u1BAF\u1BBA-\u1BE5\u1C00-\u1C23\u1C4D-\u1C4F\u1C5A-\u1C7D\u1CE9-\u1CEC\u1CEE-\u1CF1\u1CF5\u1CF6\u1D00-\u1DBF\u1E00-\u1F15\u1F18-\u1F1D\u1F20-\u1F45\u1F48-\u1F4D\u1F50-\u1F57\u1F59\u1F5B\u1F5D\u1F5F-\u1F7D\u1F80-\u1FB4\u1FB6-\u1FBC\u1FBE\u1FC2-\u1FC4\u1FC6-\u1FCC\u1FD0-\u1FD3\u1FD6-\u1FDB\u1FE0-\u1FEC\u1FF2-\u1FF4\u1FF6-\u1FFC\u2071\u207F\u2090-\u209C\u2102\u2107\u210A-\u2113\u2115\u2119-\u211D\u2124\u2126\u2128\u212A-\u212D\u212F-\u2139\u213C-\u213F\u2145-\u2149\u214E\u2183\u2184\u2C00-\u2C2E\u2C30-\u2C5E\u2C60-\u2CE4\u2CEB-\u2CEE\u2CF2\u2CF3\u2D00-\u2D25\u2D27\u2D2D\u2D30-\u2D67\u2D6F\u2D80-\u2D96\u2DA0-\u2DA6\u2DA8-\u2DAE\u2DB0-\u2DB6\u2DB8-\u2DBE\u2DC0-\u2DC6\u2DC8-\u2DCE\u2DD0-\u2DD6\u2DD8-\u2DDE\u2E2F\u3005\u3006\u3031-\u3035\u303B\u303C\u3041-\u3096\u309D-\u309F\u30A1-\u30FA\u30FC-\u30FF\u3105-\u312D\u3131-\u318E\u31A0-\u31BA\u31F0-\u31FF\u3400-\u4DB5\u4E00-\u9FCC\uA000-\uA48C\uA4D0-\uA4FD\uA500-\uA60C\uA610-\uA61F\uA62A\uA62B\uA640-\uA66E\uA67F-\uA697\uA6A0-\uA6E5\uA717-\uA71F\uA722-\uA788\uA78B-\uA78E\uA790-\uA793\uA7A0-\uA7AA\uA7F8-\uA801\uA803-\uA805\uA807-\uA80A\uA80C-\uA822\uA840-\uA873\uA882-\uA8B3\uA8F2-\uA8F7\uA8FB\uA90A-\uA925\uA930-\uA946\uA960-\uA97C\uA984-\uA9B2\uA9CF\uAA00-\uAA28\uAA40-\uAA42\uAA44-\uAA4B\uAA60-\uAA76\uAA7A\uAA80-\uAAAF\uAAB1\uAAB5\uAAB6\uAAB9-\uAABD\uAAC0\uAAC2\uAADB-\uAADD\uAAE0-\uAAEA\uAAF2-\uAAF4\uAB01-\uAB06\uAB09-\uAB0E\uAB11-\uAB16\uAB20-\uAB26\uAB28-\uAB2E\uABC0-\uABE2\uAC00-\uD7A3\uD7B0-\uD7C6\uD7CB-\uD7FB\uF900-\uFA6D\uFA70-\uFAD9\uFB00-\uFB06\uFB13-\uFB17\uFB1D\uFB1F-\uFB28\uFB2A-\uFB36\uFB38-\uFB3C\uFB3E\uFB40\uFB41\uFB43\uFB44\uFB46-\uFBB1\uFBD3-\uFD3D\uFD50-\uFD8F\uFD92-\uFDC7\uFDF0-\uFDFB\uFE70-\uFE74\uFE76-\uFEFC\uFF21-\uFF3A\uFF41-\uFF5A\uFF66-\uFFBE\uFFC2-\uFFC7\uFFCA-\uFFCF\uFFD2-\uFFD7\uFFDA-\uFFDC-\u002F-\u005c]/).test(c);
}

/**
 * This is a special case function that is just used for extensions, due to the
 * way that the validation is handled differently there compared to other modules.
 * The correct string length is hardcoded at 50 to match the MySQL item size.
 * @param {string} s The string to check
 */
function isCorrectLengthExtensions(s) {
	return isCorrectLength(s, 50);
}

/**
 * Takes two arguments, the string and an integer, and uses lengthInUtf8Bytesfunction
 * to see that the string doesn't exceed a certain length. This is mostly used to
 * prevent MySQL from having to auto-chop strings that are too long when they
 * are input into the DB
 * @param {string} s The string to check
 * @param {int} l The max length of said string
 */
function isCorrectLength(s, l) {
	var i;
	if (lengthInUtf8Bytes(s) > l) {
		return false;
	} else {
		return true;
	}
}

/**
 * Because multibyte characters like UTF8 don't get counted by the character in
 * MySQL length limits, they are counted by bytes.
 * @param {string} str The string to check
 */
function lengthInUtf8Bytes(str) {
	var m = encodeURIComponent(str).match(/%[89ABab]/g);
	return str.length + (m ? m.length : 0);
}

/**
 * Check to make sure string is an integer, not a float or string
 * @param {string} s The string to check
 */
function isInteger(mixedVar) {
	if (mixedVar.length === 0) {
		return defaultEmptyOK;
	}
	return new RegExp(/^\d+$/).test(mixedVar);
}

/**
* Check to make sure string is a float, not an integer or string
* @param {string} s The string to check
* discuss at: http://phpjs.org/functions/is_float/
* original by: Paulo Freitas
*/
function isFloat(mixedVar) {
	if (isEmpty(mixedVar)) {
		return defaultEmptyOK;
	}
	return new RegExp(/^(?:[1-9]\d*|0)?(?:\.\d+)?$/).test(mixedVar);
}

/**
 * General Number Check
 * TODO: not sure if used anymore?
 * @param {sttring} objectValue The string to check
 */
function checkNumber(objectValue) {
	if (objectValue.length === 0) {
		return true;
	}

	var startFormat = " .+-0123456789",
			numberFormat = " .0123456789",
			checkChar,
			decimal = false,
			trailingBlank = false,
			digits = false,
			i;

	checkChar = startFormat.indexOf(objectValue.charAt(0));
	if (checkChar == 1) {
		decimal = true;
	} else if (checkChar < 1) {
		return false;
	}

	for (i = 1; i < objectValue.length; i++) {
		checkChar = numberFormat.indexOf(objectValue.charAt(i));
		if (checkChar < 0) {
			return false;
		} else if (checkChar == 1) {
			if (decimal) {
				return false;
			} else {
				decimal = true;
			}
		} else if (checkChar === 0) {
			if (decimal || digits) {
				trailingBlank = true;
			}
		} else if (trailingBlank) {
			return false;
		} else {
			digits = true;
		}
	}
	return true;
}

/**
 * Similar to PHP's empty()
 * @param {string} mixedVar The string to check
 * discuss at: http://phpjs.org/functions/empty/
 * original by: Philippe Baumann
 */
function isEmpty(mixedVar) {
	var undef, key, i, len, emptyValues = [ undef, null, false, 0, "", "0" ];

	for (i = 0, len = emptyValues.length; i < len; i++) {
		if (mixedVar === emptyValues[i]) {
			return true;
		}
	}

	if (typeof mixedVar === "object") {
		for (key in mixedVar) {
			// TODO: should we check for own properties only?
			//if (mixedVar.hasOwnProperty(key)) {
			return false;
			//}
		}
		return true;
	}

	return false;
}

/**
 * Checks for all known whitespace
 * @param {string} s The string to check
 */
function isWhitespace(s) {
	var i, c;

	if (isEmpty(s)) {
		return true;
	}

	for (i = 0; i < s.length; i++) {
		c = s.charAt(i);

		if (whitespace.indexOf(c) == -1) {
			return false;
		}
	}

	return true;
}

/**
 * Check for a valud URL
 * @param {string} s The String to check
 */
function isURL(s) {
	var i, c;
	if (isEmpty(s)) {
		if (isURL.arguments.length == 1) {
			return defaultEmptyOK;
		} else {
			return (isURL.arguments[1] === true);
		}
	}

	for (i = 0; i < s.length; i++) {
		// Check that current character is number or letter.
		c = s.charAt(i);
		if (!(isURLChar(c) || isDigit(c))) {
			return false;
		}
	}

	return true;
}

/**
 * List of PIN Numbers followed by ','
 * @param {string} s The string to check
 */
function isPINList(s) {
	var i, c;
	if (isEmpty(s) && s != '0') {
		if (isPINList.arguments.length == 1) {
			return defaultEmptyOK;
		} else {
			return (isPINList.arguments[1] === true);
		}
	}

	for (i = 0; i < s.length; i++) {
		// Check that current character is number.
		c = s.charAt(i);

		if (!isDigit(c) && c != ",") {
			return false;
		}
	}

	return true;
}

/**
 * Must be a valid Caller ID String
 * @param {string} s The string to check
 */
function isCallerID(s) {
	var i, c;
	if (isEmpty(s)) {
		if (isCallerID.arguments.length == 1) {
			return defaultEmptyOK;
		} else {
			return (isCallerID.arguments[1] === true);
		}
	}

	for (i = 0; i < s.length; i++) {
		c = s.charAt(i);
		if (!(isCallerIDChar(c))) {
			return false;
		}
	}

	return true;
}

/**
 * Is a valid dial pattern string
 * @param {string} s The string to check
 */
function isDialpattern(s) {
	var i, c;
	if (isEmpty(s) && s != '0') {
		if (isDialpattern.arguments.length == 1) {
			return defaultEmptyOK;
		} else {
			return (isDialpattern.arguments[1] === true);
		}
	}

	for (i = 0; i < s.length; i++) {
		c = s.charAt(i);
		if (!isDialpatternChar(c)) {
			if (c.charCodeAt(0) != 13 && c.charCodeAt(0) != 10) {
				return false;
			}
		}
	}

	return true;
}

/**
 * Must be a valid dial rule string
 * @param {string} s The string to check
 */
function isDialrule(s) {
	var i, c;

	if (isEmpty(s)) {
		if (isDialrule.arguments.length == 1) {
			return defaultEmptyOK;
		} else {
			return (isDialrule.arguments[1] === true);
		}
	}

	for (i = 0; i < s.length; i++) {
		c = s.charAt(i);
		if ( !isDialruleChar(c) ) {
			if (c.charCodeAt(0) != 13 && c.charCodeAt(0) != 10) {
				return false;
			}
		}
	}
	return true;
}

/**
 * Check for valid dial identifier -- for Dial command
 * @param {string} s The string to check
 */
function isDialIdentifier(s) {
	var i;

	if (isEmpty(s)) {
		if (isDialIdentifier.arguments.length == 1) {
			return defaultEmptyOK;
		} else {
			return (isDialIdentifier.arguments[1] === true);
		}
	}

	for (i = 0; i < s.length; i++) {
		c = s.charAt(i);
		if ( !isDialDigitChar(c) && (c != "w") && (c != "W") ) {
			return false;
		}
	}

	return true;
}

/**
 * Check for Valid Dialable Digit (i.e on a keypad)
 * @param {string} s The string to check
 */
function isDialDigits(s) {
	var i, c;

	if (isEmpty(s)) {
		if (isDialDigits.arguments.length == 1) {
			return defaultEmptyOK;
		} else {
			return (isDialDigits.arguments[1] === true);
		}
	}

	for (i = 0; i < s.length; i++) {
		c = s.charAt(i);
		if (!isDialDigitChar(c)) {
			return false;
		}
	}

	return true;
}

/**
 * Valid IVR input, any keypad char plus some
 * priority i or t if specified by themself's
 *
 * used by legecy ivr, not sure that we need to reimplement this
 *
 * @param {string} s The string to check
 */
function isIVROption(s) {
	var i, c;

	if (isEmpty(s)) {
		if (isIVROption.arguments.length == 1) {
			return defaultEmptyOK;
		} else {
			return (isIVROption.arguments[1] === true);
		}
	}

	if (s.length == 1) { // could be i or t as only one char entered
		c = s.charAt(0);
	}

	if ( (!isDialDigitChar(c)) && (c != "i") && (c != "t") ) {
		return false;
	} else { // numbers only
		for (i = 0; i < s.length; i++) {
			c = s.charAt(i);
			if (!isDialDigitChar(c)) {
				return false;
			}
		}
	}

	return true;
}

/**
 * Check for valid Filename
 * used by recordings page.recordings.php:486
 *
 * @param {string} s The filename to check
 */
function isFilename(s) {
	var i, c;

	if (isEmpty(s)) {
		if (isFilename.arguments.length == 1) {
			return defaultEmptyOK;
		} else {
			return (isFilename.arguments[1] === true);
		}
	}

	for (i = 0; i < s.length; i++) {
		c = s.charAt(i);
		if (!isFilenameChar(c)) {
			return false;
		}
	}

	return true;
}

// ***************************************************
// ** Check if string s contains char c             **
// ***************************************************
/**
 * Check if string contains a character
 * @param {string} s The string to check
 * @param {string} c The character to look for
 */
function isInside(s, c) {
	var i, t;

	if (isEmpty(s)) {
		return false;
	}
	for (i = 0; i < s.length; i++) {
		t = s.charAt(i);
		if (t == c) {
			return true;
		}
	}

	return false;
}

// ***************************************************
// ** Check if valid email address                  **
// ***************************************************
/**
 * Check if valid email address
 * @param {string} s The string to check
 */
function isEmail(s) {
	if (isEmpty(s)) {
		if (isEmail.arguments.length == 1) {
			return defaultEmptyOK;
		} else {
			return (isEmail.arguments[1] === true);
		}
	}
	var emailAddresses = s.split(","),
			pattern = /^.*@..+$/i,
			emailCount = 0;

	for (var e in emailAddresses) {
		emailCount += (pattern.test(emailAddresses[e]) === true) ? 1 : 0;
	}

	if (emailAddresses.length == emailCount) {
		return true;
	}
	return false;
}

// ***************************************************
// ** HELPER FUNCTIONS FOR ABOVE VALIDATIONS        **
// ***************************************************

/**
 * Check if is digit
 * @param {string} c The character to check
 */
function isDigit(c) {
	return new RegExp(/[0-9]/).test(c);
}

/**
 * Check if is letter (character)
 * @param {string} c The character to check
 */
function isLetter(c) {
	return isUnicodeLetter(c) || new RegExp(/[ a-zA-Z'\&\(\)\-\/]/).test(c);
}

/**
 * Check if is a URL Character
 * @param {string} c The character to check
 */
function isURLChar(c) {
	return new RegExp(/[a-zA-Z=:,%#\.\-\/\?\&]/).test(c);
}

/**
 * Check if is Caller ID Character
 * @param {string} c The character to check
 */
function isCallerIDChar(c) {
	return isUnicodeLetter(c) || new RegExp(/[ a-zA-Z0-9:_,-<>\(\)\"&@\.\+\*]/).test(c);
}

/**
 * Check if is Dial Pattern Character
 * @param {string} c The character to check
 */
function isDialpatternChar(c) {
	return new RegExp(/[-0-9\[\]\+\.\|ZzXxNn\*\#_!\/]/).test(c);
}

/**
 * Check if is Dial Rule Character
 * @param {stringq} c The character to check
 */
function isDialruleChar(c) {
	return new RegExp(/[0-9\[\]\+\.\|ZzXxNnWw\*\#\_\/]/).test(c);
}

/**
 * Check if is Dial Digit Character
 * @param {string} c The character to check
 */
function isDialDigitChar(c) {
	return new RegExp(/[0-9\*#]/).test(c);
}

/**
 * Check if is Filename Character
 * @param {string} c The character to check
 */
function isFilenameChar(c) {
	return new RegExp(/[-0-9a-zA-Z\_]/).test(c);
}

/**
 * Validate Single Destination
 * @param {obj} theForm   The Form Object
 * @param {int} formNum   The Form Number
 * @param {bool} bRequired Value is Required or not
 */
function validateSingleDestination(theForm, formNum, bRequired) {
	var gotoType = theForm.elements[ "goto" + formNum ].value, gotoFld, gotoVal;
	if (bRequired && gotoType === "") {
		warnInvalid($("#goto" + formNum), fpbx.msg.framework.validateSingleDestination.required);
		return false;
	} else {
		// check the 'custom' goto, if selected
		if (gotoType == "custom") {
			gotoFld = theForm.elements[ "custom" + formNum ];
			gotoVal = gotoFld.value;
			if (gotoVal.indexOf("custom-") == -1) {
				warnInvalid($("#goto" + formNum), fpbx.msg.framework.validateSingleDestination.error);
				return false;
			}
		}
	}
	return true;
}

/**
 * Check for WeakSecrets and Alert the User about them
 * TODO: Should be UTF8
 */
function weakSecret() {
	var password = document.getElementById("devinfo_secret").value, originalPassword = document.getElementById("devinfo_secret_origional").value;

	if (password == originalPassword) {
		return false;
	}

	if (password.length <= 5) {
		alert(fpbx.msg.framework.weakSecret.length);
		return true;
	}

	if (password.match(/[a-z].*[a-z]/i) === null || password.match(/\d\D*\d/) === null) {
		alert(fpbx.msg.framework.weakSecret.types);
		return true;
	}
	return false;
}

//set up query retreiver
$.urlParam = function(name) {
	var match = new RegExp("[\\?&]" + name + "=([^&#]*)").exec(window.location.search);
	return match && decodeURIComponent(match[1].replace(/\+/g, " "));
};

var popover_box, popover_box_class, popover_box_mod, popover_select_id;
/**
 * Bind Destination Double Selects
 * Note: This is a function so it can be called again through POST AJAX scripts
 */
function bind_dests_double_selects() {
	//destination double dropdown code
	$(".destdropdown").unbind().bind("change", function(e) {
		var id = $(this).data("id"), dest	= $(this).val();
		id = (typeof id == "undefined") ? "" : id; //ensure id isn't set to undefined

		$("[data-id=" + id + "].destdropdown2").addClass("hidden");
		dd2 = $("#" + dest + id + ".destdropdown2");
		cur_val = dd2.removeClass("hidden").prop("disabled", false).val();

		// This was added because a cancel can leave dd2 cur_val to popover
		// even when there are other choices so we force it to 'none'
		if (dd2.children().length > 1 && cur_val == "popover") {
			dd2.val("");
			cur_val = "";
		}
		if (cur_val == "popover") {
			dd2.trigger("change");
		}

	});

	$(".destdropdown2").unbind().bind("change", function() {
		//get last
		var dest = $(this).val();
		if (dest == "popover") {
			var urlStr = $(this).data("url") + "&fw_popover=1", id = $(this).data("id");
			popover_select_id = this.id;
			popover_box_class = $(this).data("class");
			popover_box_mod = $(this).data("mod");
			popover_box = $("<div id=\"popover-box-id\" data-id=\"" + id + "\"></div>")
				.html("<iframe data-popover-class=\"" + popover_box_class + "\" id=\"popover-frame\" frameBorder=\"0\" src=\"" + urlStr + "\" width=\"100%\" height=\"95%\"></iframe>")
				.dialog({
					title: "Add",
					resizable: false,
					modal: true,
					width: window.innerWidth - (window.innerWidth * '.10'),
					height: window.innerHeight - (window.innerHeight * '.10'),
					create: function() {
						$("body").scrollTop(0).css({ overflow: "hidden" });
					},
					close: function(e) {
						if(!runningPopOverActions) {
							//cheating by puttin a data-id on the modal box
							var id = $(this).data("id");
							//dropdown 1
							var par = $("#goto" + id).data("last");
							//reset the original option to the first in the list *if* it wasn't
							//previously selected. This is so we dont get random 'white' (empty)
							//options selected when coming back from a canceled popover
							var name = $("#goto"+id).val();
							if(par !== name) {
								$("#"+name+id)[0].selectedIndex = 0;
							}
							$("#goto" + id).val(par).change();
							if (par !== "") { //Get dropdown2
								var par_id = par.concat(id);
								$("#" + par_id).val($("#" + par_id).data("last")).change();
							}
							$("#popover-frame").contents().find("body").remove();
							$("#popover-box-id").html("");
							$("body").css({ overflow: "inherit" });
						}
						$(e.target).dialog("destroy").remove();
					},
					buttons: [
							{
							text: fpbx.msg.framework.save,
							click: function() {
								pform = $("#popover-frame").contents().find(".popover-form").first();
								if (pform.length === 0) {
									pform = $("#popover-frame").contents().find("form").first();
								}
								pform.submit();
							}
						}, {
							text: fpbx.msg.framework.cancel,
							click: function() {
								$(this).dialog("close");
							}
						}
					]
				});
		} else {
			//if we arent a popover set it, so we have it saved
			var last = $.data(this, "last", dest);
		}
	});

	//hacky way to ensure destinations dropdown is the same background-color as currently selected item
	$(".destdropdown").bind("change", function() {
		if ($(this).find("option:selected").val() == "Error"){
			$(this).css("background-color", "red");
		} else {
			$(this).css("background-color", "");
		}
	});
}

//Reset second level desination drop downs when the form is reset
$('form').on('reset', function() {
	var $this = this;
	var showing_list = new Array();
	setTimeout(function(){
		$($this).find(".destdropdown").each(function() {
			var v = $(this).val(),
					i = $(this).data("id");
			if(v !== "") {
				$(".destdropdown2").not("#" + v + i).each(function(){
					if($.inArray(this.id, showing_list) == -1){
						$(this).addClass("hidden");
					}
				});
				$("#"+ v + i).removeClass("hidden");
				showing_list[showing_list.length] = v + i;
			} else {
				$(".destdropdown2").addClass("hidden");
			}
		});
	}, 50); //50 mil timeout because the form is not reset yet
});

/**
 * Close Popover Window
 * @param {string} drawselects The draw select to replace with new data
 */
var runningPopOverActions = false;
function closePopOver(drawselects) {
	runningPopOverActions = true;
	var options = $("." + popover_box_class + " option", $("<div>" + drawselects + "</div>"));
	$("." + popover_box_class).each(function() {
		dv = $(this).val();
		if (this.id == popover_select_id) {
			dv = options[0].value;
			var currentValues = [];
			for(i = 0; i < this.options.length; i++){
				currentValues.push(this.options[i].value);
			}
			for(i = 0; i < options.length; i++){
				if ($.inArray(options[i].value, currentValues) == -1){
					dv = options[i].value;
					break;
				}
			}
		}
		$(this).empty().append(options.clone()).val(dv);
	});

	// In the case of multi-category destinations, we may have other options
	// to update as well. Example would be adding an extension can result
	// in voicemail destinations being added so we want to udpate those too.
	//
	if (popover_box_class != popover_box_mod) {
		options = {};
		$("." + popover_box_mod).each(function() {
			var data_class = $(this).data("class");
			if (data_class != popover_box_class) {
				if (typeof options[data_class] == "undefined") {
					options[data_class] = $("." + data_class + " option", $("<div>" + drawselects + "</div>"));
				}
				dv = $(this).val();
				$(this).empty().append(options[data_class].clone()).val(dv);
			}
		});
	}

	$("body").css({ overflow: "inherit" });
	$("#popover-box-id").html("");
	popover_box.dialog("close");
	runningPopOverActions = false;
}

/**
 * convert a normal module display page to a format suitable for a destination popOver display.
 * - Remove the rnav
 * - hide the submit buttons
 * - insert a hidden input type fo fw_popover_process into the form
 */
function popOverDisplay() {
	$(".rnav").hide();
	pform = $(".popover-form").first();
	if (pform.length === 0) {
		pform = $("form").first();
	}
	$("[type=\"submit\"]", pform).hide();
	$("<input>").attr({
		type: "hidden",
		name: "fw_popover_process"
	}).val(parent.$("#popover-frame").data("popover-class")).appendTo(pform);
}

/***************************************************
*             FREEPBX RELOAD                      *
***************************************************/
/*
* were using jquery-ui to show the reload dialod
* we position all dialog boxes at the center (x) and 50px from the top (y)
* were also carefull to cleanup all/any remaning dom eelemens that we created
* while in most situations cleanup wont matter much (the dom will be cleared next
* time the user click submit, and there is, arguable at least a 1:1 ratio of
* submit's vs Apply Config's), nevertheless, certain wonkyness seems to be exhibited
* if we dont cleanup, specificaly when showing the 'more info' of an error
*
*/

/**
 * Confirm reload if requested, otherwise just call fpbx_reload
 * @return {[type]} [description]
 */
function fpbx_reload_confirm() {
	if (!fpbx.conf.RELOADCONFIRM) {
		fpbx_reload();
	}

	$("<div></div>")
		.html(sprintf(_("Reloading will apply all configuration changes made in %s to your PBX engine and make them active."),"FreePBX"))
		.dialog({
			title: "Confirm reload",
			resizable: false,
			modal: true,
			close: function (e) {
				$(e.target).dialog("destroy").remove();
			},
			buttons: [
				{
					text: fpbx.msg.framework.continuemsg,
					click: function() {
						$(this).dialog("destroy").remove();
						fpbx_reload();
					}
				},
				{
					text: fpbx.msg.framework.cancel,
					click: function() {
						$(this).dialog("destroy").remove();
					}
				}
			]
		});
}

/**
 * Do the actual Reload
 */
function fpbx_reload() {
	$(document).trigger( "fpbx_reload", {status: "starting", complete: false, errors: false} );
	$("<div></div>").progressbar({ value: 100 });
	var box = $("<div id=\"reloadbox\"></div>")
		.html('<div class="progress"><div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="45" aria-valuemin="0" aria-valuemax="100" style="width: 100%"><span class="sr-only">'+_("Reloading...")+'</span></div></div>')
		.dialog({
			title: _("Reloading..."),
			resizable: false,
			modal: true,
			height: 102,
			closeOnEscape: false,
			open: function(event, ui) {
				$(".ui-dialog-titlebar-close", $(this).parent()).hide();
			},
			close: function(e) {
				$(e.target).dialog("destroy").remove();
			}
		});
	$.ajax({
		type: "POST",
		url: 'ajax.php?command=reload',
		dataType: "json",
		timeout: 1800000, //30 minutes
		success: function(data) {
			box.dialog("destroy").remove();
			if (!data.status) {
				$(document).trigger( "fpbx_reload", {status: "error", complete: true, errors: {type: "object", status: true, data: data}} );
				// there was a problem
				var r = "<h3>" + sprintf(_("There was an error during reload: %s"),data.message) + "<\/h3>" +
				"<pre style=\"max-height:200px\">" + (data.raw.trace ? data.message+"\n"+data.raw.trace : data.message) + "<\/pre>";
				freepbx_reload_error(r);
			} else {
				$(document).trigger( "fpbx_reload", {status: "complete", complete: true, errors: {type: false, status: false, data: {}}} );
				//unless fpbx.conf.DEVELRELOAD is true, hide the reload button
				if (fpbx.conf.DEVELRELOAD != "true") {
					toggle_reload_button("hide");
				}
			}
		},
		error: function(reqObj, status) {
			$(document).trigger( "fpbx_reload", {status: "error", complete: true, errors: {type: "jqXHR", status: true, data: reqObj}} );
			box.dialog("destroy").remove();
			var r = "<p>" + fpbx.msg.framework.invalid_responce + "<\/p>" +
							"<p>XHR response code: " + reqObj.status +
							" XHR responseText: " + reqObj.resonseText +
							" jQuery status: " + status  + "<\/p>";
			freepbx_reload_error(r);
		}
	});
}

//show reload error messages
/**
 * Show Reload Error Messages
 * @param  {string} txt The error Message in Full
 */
function freepbx_reload_error(txt) {
	var box = $("<div></div>")
						.html(txt)
						.dialog({
							title: _("Error!"),
							resizable: false,
							modal: true,
							minWidth: 600,
							close: function (e) {
								$(e.target).dialog("destroy").remove();
							},
							buttons: [
								{
									text: fpbx.msg.framework.retry,
									click: function() {
										$(this).dialog("destroy").remove();
										fpbx_reload();
									}
								},
								{
									text: fpbx.msg.framework.cancel,
									click: function() {
										$(this).dialog("destroy").remove();
									}
								}
							]
						});
						$("#error_more_info").click(function() {
							$(this).next("pre").show();
							$(this).hide();
							return false;
						});
}

//show reload button if needed
/**
 * Show Reload Button if needed
 * @param  {string} action Whether to show or hide the button
 */
function toggle_reload_button(action) {
	switch (action) {
		case "show":
			$("#button_reload").show();
		break;
		case "hide":
			$("#button_reload").hide();
		break;
	}
}

/***************************************************
*             GLOBAL JQUERY CODE                  *
***************************************************/
var kkeys = [], smiles = "38,38,40,40,37,39,37,39,66,65";
$(document).keydown(function(e) {
	kkeys.push( e.keyCode );
	if ( kkeys.toString().indexOf( smiles ) >= 0 ) {
		$(document).unbind("keydown", arguments.callee);
		var kkWindow = window.open("", "kkWindow", "width=340, height=300");
		kkWindow.document.write('<title>:-)</title><canvas id="the-game" width="320" height="240" style="border:1px solid #000000;background:F8F8FF;"></canvas>');
		kkWindow.document.write('<script type="application/javascript" src="'+window.location.origin+'/admin/assets/js/kclc.js"></script>');
	}
});

jQuery.cachedScript = function( url, options ) {

	/// Allow user to set any option except for dataType, cache, and url
	options = $.extend( options || {}, {
		dataType: "script",
		cache: true,
		url: url
	});

	// Use $.ajax() since it is more flexible than $.getScript
	// Return the jqXHR object so we can chain callbacks
	return jQuery.ajax( options );
};

$(document).on('click', '.toggle-password', function() {
	var id = $(this).data("id");
			icon = $(this).find("i");
			type = $("#" + id).prop("type");
	if(type == "password") {
		$("#" + id).prop("type", "text");
		icon.removeClass("fa-eye").addClass("fa-eye-slash");
	} else {
		$("#" + id).prop("type", "password");
		icon.removeClass("fa-eye-slash").addClass("fa-eye");
	}
});
var loadingzxcvbn = false;
$(document).on('keyup', '.password-meter', function() {
	var $this = this;
	if(typeof zxcvbn === "undefined" && !loadingzxcvbn) {
		loadingzxcvbn = true;
		$($this).after('<i id="password-meter-load" class="fa fa-circle-o-notch fa-spin"></i>');
		$.cachedScript( "assets/js/zxcvbn-4.4.0.min.js" ).done(function( script, textStatus ) {
			$("#password-meter-load").remove();
			checkPassword($this);
		});
	} else if(typeof zxcvbn !== "undefined") {
		checkPassword($this);
	}
});
$(document).on('focus', '.password-meter', function() {
	var $this = this,
			val = $(this).val();
	if(val == "******") {
		return false;
	}
	if(typeof zxcvbn === "undefined" && !loadingzxcvbn) {
		loadingzxcvbn = true;
		$($this).after('<i id="password-meter-load" class="fa fa-circle-o-notch fa-spin"></i>');
		$.cachedScript( "assets/js/zxcvbn-4.4.0.min.js" ).done(function( script, textStatus ) {
			$("#password-meter-load").remove();
			checkPassword($this);
		});
	} else {
		checkPassword($this);
	}
});

function checkPassword(el) {
	var jel = $(el);
	var textVal = jel.val(),
			result = zxcvbn(textVal),
			box = jel.parents("div").find(".password-meter-box");
	if(box.length === 0) {
		$(el).after('<div class="password-meter-box"><div class="wording"></div><div class="progress password-meter-progress"><div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%"></div></div></div>');
		box = $(el).parents("div").find(".password-meter-box");
	}
	switch(result.score){
		case 0:
			box.find(".wording").text(_("Really Weak"));
			box.find(".progress-bar").removeClass("progress-bar-warning progress-bar-success progress-bar-info").addClass("progress-bar-danger").css("width", "20%");
		break;
		case 1:
			box.find(".wording").text(_("Weak"));
			box.find(".progress-bar").removeClass("progress-bar-warning progress-bar-success progress-bar-info").addClass("progress-bar-danger").css("width", "40%");
		break;
		case 2:
			box.find(".wording").text(_("So-So"));
			box.find(".progress-bar").removeClass("progress-bar-danger progress-bar-success progress-bar-info").addClass("progress-bar-warning").css("width", "60%");
		break;
		case 3:
			box.find(".wording").text(_("Good"));
			box.find(".progress-bar").removeClass("progress-bar-warning progress-bar-danger progress-bar-success").addClass("progress-bar-info").css("width", "80%");
		break;
		case 4:
			box.find(".wording").text(_("Strong"));
			box.find(".progress-bar").removeClass("progress-bar-warning progress-bar-danger progress-bar-info").addClass("progress-bar-success").css("width", "100%");
		break;
	}
}

/**
* Lock the action bar to the bottom of the screen
* @author Bryan Walters <bryan ! walters (at) schmoozecom (dot) com
*/
function positionActionBar() {
	if ($("#action-bar").length > 0) {
		$("#action-bar").removeClass("locked");

		var css = {},
			pageHeight = parseInt($("#page").innerHeight()),
			actionBarOffset = parseInt($("#action-bar").offset().top) + parseInt($("#action-bar").innerHeight()) + parseInt($("#footer").innerHeight()) + parseInt($("#action-bar").css("padding-bottom"));

		if (pageHeight - actionBarOffset <= 0) {
			$("#action-bar").addClass("locked");
		}
	}
}

function resizeRightNav() {
	if ($("#floating-nav-bar").length > 0) {
		var win = $(window).height() - $(".freepbx-navbar .navbar").outerHeight() - 100;
		if ($("#action-bar").length > 0) {
			win = win - $("#action-buttons").outerHeight();
		}
		$("#floating-nav-bar").css("max-height",(win)+"px");
		$("#floating-nav-bar .floating-nav-bar-contents").css("max-height",(win-3)+"px");
	}
}
function regExpEscape(literal_string) {
	return literal_string.replace(/[-[\]{}()*+!<=:?.\/\\^$|#\s,]/g, '\\$&');
}

$(document).ready(function() {
	$.ajax({
		type: "POST",
		url: 'ajax.php?command=navbarToogle',
		dataType: "json",
		success: function(data) {
			if (data.status) {
				if(data.message == "yes"){
					$("#floating-nav-bar").addClass("show");
					$(".nav-bar-show").addClass("active");
				}
				else{
					$("#floating-nav-bar").removeClass("show");
					$(".nav-bar-show").removeClass("active");
				}					
			}
		},
		error: function(reqObj, status) {
			var err = 	"<p>XHR response code: " + reqObj.status +
						" XHR responseText: " + reqObj.resonseText +
						" jQuery status: " + status  + "<\/p>";
			fpbxToast(err,'Error','danger');
		}
	});	
	$(".nav-bar-show").click( function(){
		$.ajax({
			type: "POST",
			url: 'ajax.php?command=navbarToogle&click=true',
			dataType: "json",
			success: function(data) {
				if(data.status) {
					if(data.message == "yes"){
						$("#floating-nav-bar").addClass("show");
						$(".nav-bar-show").addClass("active");
					}
					else{
						$("#floating-nav-bar").removeClass("show");
						$(".nav-bar-show").removeClass("active");
					}					
				} 
			},
			error: function(reqObj, status) {
				var err = 	"<p>XHR response code: " + reqObj.status +
							" XHR responseText: " + reqObj.resonseText +
							" jQuery status: " + status  + "<\/p>";
				fpbxToast(err,'Error','danger');
			}
		});
	});

	$(".fpbx-usageinfo .panel-heading a").click(function() {
		if($(this).hasClass("collapsed")) {
			$.cookie("destinationUsage",1);
		} else {
			$.cookie("destinationUsage",0);
		}
	});
	if($("#fpbxsearch").hasClass("in")) {
		$("#fpbxsearch input").blur();
	}
	//when clicking the magnifying glass on the search bar focus on the search input
	$("#fpbxsearch .fa-search").click(function() {
		$("#fpbxsearch input").focus();
	});
	$("#fpbxsearch input").blur(function() {
		$("#fpbxsearch").removeClass("in");
		Cookies.set("searchHide",1,{ path: '' });
	});
	$("#search-btn").click(function() {
		if(!$("#fpbxsearch").hasClass("in")) {
			$("#fpbxsearch").one("transitionend", function() {
				$("#fpbxsearch input").focus();
			});
			$("#fpbxsearch").addClass("in");
			Cookies.set("searchHide",0,{ path: '' });
		}
	});
	$(window).keydown(function(e){
		if(e.keyCode === 27 && $("#fpbxsearch").hasClass("in")) {
			$("#fpbxsearch input").blur();
			$("#floating-nav-bar").removeClass("show");
			Cookies.set("searchHide",1,{ path: '' });
		}
	});
	if ($(".fpbx-container").length > 0) {
		//Show tab if location hash matches data-name
		var loc = window.location.hash.replace("#", "");
		if (loc !== "" && $(".fpbx-container li[data-name=" + loc + "] a").length > 0) {
			$(".fpbx-container li[data-name=" + loc + "] a").tab('show');
		}

		//Hover over (?) bubbles, they will lock until container is exited
		$(".fpbx-container i.fpbx-help-icon").on("mouseenter", function() {
			var id = $(this).data("for"), container = $(this).parents(".element-container");
			$(".fpbx-help-block").removeClass("active");
			var tmp_id_help = '#' + regExpEscape(id) + "-help";
			$(tmp_id_help).addClass("active");
			container.one("mouseleave", function(event) {
				if(event.relatedTarget && (event.relatedTarget.type == "submit" || event.relatedTarget.type == "button")){
					return;
				}
				var act = $(tmp_id_help).data("activate");
				if(typeof act !== "undefined" && act == "locked") {
					return;
				}

				$(tmp_id_help).fadeOut("slow",function() {
					$(this).removeClass("active").css("display","");
				});
				$(this).off("mouseleave");
			});
		});

		//Help for input boxes
		/*
		$(".fpbx-container .form-control").focus(function() {
			$(".fpbx-help-block").removeClass("active");
			var id = $(this).prop("id");
			$("#" + id + "-help").addClass("active");
			$("#" + id + "-help").data("activate","locked");
		});
		$(".fpbx-container .form-control").blur(function(event) {
			if(event.relatedTarget && (event.relatedTarget.type == "submit" || event.relatedTarget.type == "button")){
				return;
			}
			$("#" + id + "-help").data("activate","");
			var id = $(this).prop("id");
			$("#" + id + "-help").fadeOut("slow",function() {
				$(this).removeClass("active").css("display","");
			});
		});
		*/
	}

	positionActionBar();
	resizeRightNav();

	$(window).scroll(function() {
		positionActionBar();
		resizeRightNav();
	});
	$(window).resize(function() {
		positionActionBar();
		resizeRightNav();
	});

	$(document).on("shown.bs.tab", 'a[data-toggle="tab"]', function(e) {
		positionActionBar();
	});

	$("#action-bar-hide").click(function() {
		$("#action-bar").toggleClass("shrink");
		var fa = $(this).find(".fa");
		if(fa.hasClass("fa-angle-double-right")) {
			fa.removeClass("fa-angle-double-right");
			fa.addClass("fa-angle-double-left");
		} else {
			fa.removeClass("fa-angle-double-left");
			fa.addClass("fa-angle-double-right");
		}
	});

	/**
	* Perform form actions on a given page based on what action-bar button is clicked
	* @author Bryan Walters <bryan ! walters (at) schmoozecom (dot) com
	*/

	$(document).on("click", "#action-bar input[type=submit]", function(e) {
		e.preventDefault();

		var fpbxForm = $(".fpbx-submit:visible");
			formName = fpbxForm.attr("name");
			buttonName = $(this).attr("name").toLowerCase();

		switch (buttonName) {
			case "reset":
				document.forms[formName].reset();
			break;
			case "submit":
				if(!fpbxForm[0].checkValidity()){
					for(i = 0; i < fpbxForm[0].elements.length; i++){
						if(fpbxForm[0].elements[i].willValidate && !fpbxForm[0].elements[i].validity.valid){
							var alertText = $(fpbxForm[0].elements[i]).data("invalid");
							warnInvalid(fpbxForm[0].elements[i], alertText);
							break;
						}
					}
					return false;
				}else{
					fpbxForm.submit();
					return true;
				}
			break;
			case "delete":
				if (confirm(_("Are you sure you want to delete this?"))) {
					delLink = fpbxForm.data("fpbx-delete");
					location.href = delLink;
				}
				break;
			case "duplicate":
				fpbxForm.submit();
				break;
			default:
				console.warn("There is no action for button named " + buttonName);
				break;
			}
	});

	$(".global-message-banner .close").click(function() {
		var hash = $(this).data("hash"), m = Cookies.get("bannerMessages"), messages = [];
		if (typeof m != "undefined") {
			messages = JSON.parse(m);
			messages.push(hash);
		} else {
			messages = [ hash ];
		}
		Cookies.set("bannerMessages", JSON.stringify(messages), { expires: 365 , path: '' });
	});
	bind_dests_double_selects();

	//help tags
	$("a.info").each(function() {
		var span = $(this).find("span");
		$(this).after("<span class=\"help\"><i class=\"fa fa-question-circle\"></i><span>" + span.html() + "</span></span>");
		span.remove();
		//this gets rid of the <a> tags surrounding the promp text
		$(this).replaceWith($(this).html());
	});

	$(document).on("mouseenter", '.help', function() {
		side = fpbx.conf.text_dir == "lrt" ? "left" : "right";
		var pos = $(this).offset(), offset = (200 - pos.side) + "px";
		//left = left > 0 ? left : 0;
		$(this).find("span").css(side, offset).stop(true, true).delay(500).animate({ opacity: "show" }, 750);
	}).on("mouseleave", '.help', function(){
		$(this).find("span").stop(true, true).animate({opacity: "hide"}, "fast");
	});

	//show/hide a gui_eleements section
	//new
	$(".section-title").click(function() {
		var id = $(this).data("for"),
				icon = $(this).find("i.fa");
		if (icon.length > 0) {
			icon.toggleClass("fa-minus").toggleClass("fa-plus");
			$(".section[data-id='" + id + "']").slideToggle("slow", function() {
				positionActionBar();
			});
		}
	});

	//old
	$(".guielToggle").click(function() {
		var txt = $(this).find(".guielToggleBut"),
				el = $(this).data("toggle_class"),
				section = $.urlParam("display") + "#" + el;

		//true = hide
		//false = dont hide
		switch (txt.text().replace(/ /g, "")) {
			case "-":
				txt.text("+ ");
				$("." + el).hide();

				//set cookie of hidden section
				guielToggle = $.parseJSON(Cookies.get("guielToggle")) || {};
				guielToggle[section] = false;
				Cookies.set("guielToggle", JSON.stringify(guielToggle),{ path: '' });
			break;
			case "+":
				txt.text("-  ");
				$("." + el).show();

				//set cookie of hidden section
				guielToggle = $.parseJSON(Cookies.get("guielToggle")) || {};
				if (guielToggle.hasOwnProperty(section)){
					guielToggle[section] = true;
					Cookies.set("guielToggle", JSON.stringify(guielToggle),{ path: '' });
				}
			break;
		}
	});

	//set language on click
	$("#fpbx_lang > li").click(function() {
		Cookies.set("lang", $(this).data("lang"), { path: '' });
		window.location.reload();
	});

	//show reload button if neede
	if (fpbx.conf.reload_needed) {
		toggle_reload_button("show");
	}

	//allow click on checkbox and surrounding area
	$(".sortable:not(.th-inner) li").click(function(event) {
		if ($(event.target).is(":checkbox")) {
			return true;
		}
		var checkbox = $(this).find("input");
		checkbox.prop("checked", !checkbox[0].checked);
		return false;
	});

	//stop propagation when clicking on a checkbox in an a link
	$(".audio-codecs").click(function(event) {
		event.stopPropagation();
	});

	//reload
	$("#button_reload").click(function(){
		if (fpbx.conf.RELOADCONFIRM == "true") {
			fpbx_reload_confirm();
		} else {
			fpbx_reload();
		}
	});

	//logo icon
	$("#MENU_BRAND_IMAGE_TANGO_LEFT").click(function() {
		window.open($(this).data("brand_image_freepbx_link_left"), "_newtab");
	});

	$("#fixed-list-button").click(function(){
		$("#floating-nav-bar").toggleClass("show");
	});

	$(".navbar-toggle").click(function(){
		if($(this).data("target") == "#fpbx-menu-collapse"){
			if(!$(this).hasClass("collapsed")){
				$("#fpbxsearch").show();
				$(".stuck-right").show();
			}else {
				$("#fpbxsearch").hide();
				$(".stuck-right").hide();
			}
		}
	});

	/**
	 * Search for fields marked as class .extdisplay or the common button types.
	 * Add a span so we can warn when they are using a duplicate extension,
	 * adding a duplicate class also for styling options.
	 * TODO: get feedback on a different image
	 */
	$('input.extdisplay.form-control,input[type=text][name=extension].form-control,input[type=number][name=extension].form-control,input[type=text][name=extdisplay].form-control,input[type=text][name=account].form-control').each(function() {
		var val = $(this).val(), data = $(this).data("extdisplay");
		if (typeof data == "undefined") {
			$(this).data("extdisplay", val);
		} else if (typeof extmap[val] != "undefined") {
			val++;
			while (typeof extmap[val] != "undefined") {
				val++;
			}
		}
	});
	$(document).on('keyup', 'input.extdisplay.form-control,input[type=text][name=extension].form-control,input[type=number][name=extension].form-control,input[type=text][name=extdisplay].form-control,input[type=text][name=account].form-control', function() {
		var val = $(this).val(), data = $(this).data("extdisplay"), $this = this;
		var lskip = $(this).data("no-duplicate-check");
		if(lskip){
			return true;
		}
		if(typeof val !== "undefined" && val !== "") {
			if(typeof extmap[val] == "undefined" || $(this).data("extdisplay") == val) {
				$(this).removeClass("duplicate-exten").parents(".form-group").removeClass("has-warning").find(".input-warn").remove();
			} else {
				$(this).addClass("duplicate-exten").before('<i class="fa fa-exclamation-triangle input-warn" data-toggle="tooltip" data-placement="left" title="'+ val + fpbx.msg.framework.validation.duplicate + extmap[val]+'"></i>').parents(".form-group").addClass("has-warning");
				$(this).parents(".form-group").find(".input-warn").tooltip();
			}
		}
		//remove previous binds so we don't duplicate
		$(this).parents("form").off("submit.extdisplay");
		$(this).parents("form").on("submit.extdisplay",function(e) {
			// If a previous submit handler has cancelled this don't bother
			if (e.isDefaultPrevented()) {
				return false;
			}
			exten = $('.duplicate-exten', this);
			if (exten.length > 0) {
				extnum = exten.val();
				warnInvalid($($this),extnum + fpbx.msg.framework.validation.duplicate + extmap[extnum]);
				return false;
			}
			return true;
		});
	});
	var extselector = $("input.extdisplay,input[type=text][name=extension],input[type=text][name=extdisplay],input[type=text][name=account]").not("input.noextmap").not("input.form-control");
	if (extselector.length > 0) {
		extselector.after(" <span style='display:none'><a href='#'><img src='images/notify_critical.png'/></a></span>").keyup(function() {
			if (typeof extmap[this.value] == "undefined" || $(this).data("extdisplay") == this.value) {
				$(this).removeClass("duplicate-exten").next("span").hide();
			} else {
				$(this).addClass("duplicate-exten").next("span").show().children("a").attr("title", extmap[this.value]);
			}
		}).each(function() {
			/**
			 * we automatically add a data-extdisplay data tag to the element if it is
			 * not already there and set the value that was loaded at page load time.
			 * This allows modules who are trying to guess at an extension value to
			 * preset so we don't pre-determine a value is safe when the generating
			 * code may be flawed, such as ringgroups and vmblast groups.
			 */
			if (typeof $(this).data("extdisplay") == "undefined") {
				$(this).data("extdisplay", this.value);
			} else if (typeof extmap[this.value] != "undefined") {
				this.value++;
				while (typeof extmap[this.value] != "undefined") {
					this.value++;
				}
			}
		}).parents("form").submit(function(e) {
			// If there is a duplicate-exten class on an element then validation fails, don't let the form submit */
			// If a previous submit handler has cancelled this don't bother
			if (e.isDefaultPrevented()) {
				return false;
			}
			exten = $('.duplicate-exten', this);
			if (exten.length > 0) {
				extnum = exten.val();
				warnInvalid($(this),extnum + fpbx.msg.framework.validation.duplicate + extmap[extnum]);
				return false;
			}
			return true;
		});
	}

	//shortcut keys
	//show modules
	$(document).bind("keydown", "meta+shift+a", function() {
		$("#modules_button").trigger("click");
	});

	//submit button
	$(document).bind("keydown", "ctrl+shift+s", function() {
		$("input[type=submit][name=Submit]").click();
	});

	//reload
	$(document).bind("keydown", "ctrl+shift+a", function() {
		fpbx_reload();
	});

	//logout button
	$("#user_logout").click(function() {
		url = window.location.pathname;
		$.get(url + "?logout=true", function() {
			Cookies.remove("PHPSESSID");
			window.location = url;
		});
	});

	/**
	 * Used in freepbx_helpers.php function fpbx_form_input_check() to
	 * enable/disable the text box as the check box is checked
	 * and retain the value and swap with the default value provided.
	 */
	$(".input_checkbox_toggle_true, .input_checkbox_toggle_false").click(function() {
		checked = $(this).hasClass("input_checkbox_toggle_true") ? this.checked : !this.checked;
		$(this).prev().prop("disabled", checked);
		if (checked) {
			$(this).data("saved", $(this).prev().val());
			$(this).prev().val($(this).data("disabled"));
		} else {
			$(this).prev().val($(this).data("saved"));
		}
	});

	//ajax spinner
	$(document).ajaxSend(function(event,jqhxr,settings) {
		settings.longpoll = settings.longpoll || false;
		if(!settings.longpoll){
			$("#settings-cog").addClass("fa-spin");
			$("#settings-cog").css("color","");
			$("#settings-cog").prop("title","");
		}
	});

	$(document).ajaxComplete(function() {
		$("#settings-cog").removeClass("fa-spin");
	});

	$( document ).ajaxError(function(event, jqxhr, settings, thrownError) {
		// Some modules *expect* errors, so we need to be able to disable
		// alerts when this happens
		if ( jqxhr.status== 401 ) {
			var url = window.location.pathname;
			$.get(url + "?logout=true", function() {
				$.removeCookie("PHPSESSID", { path: "/" });
				window.location = url;
			});
			return;
		}

		if ((typeof jqxhr.suppresserrors !== 'undefined' && jqxhr.suppresserrors === true) || (typeof window.supresserrors !== 'undefined' && window.supresserrors === true)) {
			return;
		}
		if(typeof jqxhr.responseJSON !== "undefined" && jqxhr.responseJSON !== null) {
			if(typeof jqxhr.responseJSON.error.message !== "undefined") {
				fpbxToast(jqxhr.responseJSON.error.message+"<br>"+_("File")+":"+jqxhr.responseJSON.error.file+":"+jqxhr.responseJSON.error.line,jqxhr.responseJSON.error.type,"exception");

			} else {
				fpbxToast(jqxhr.responseJSON.error,_("Error"),"exception");
			}
			if (window.console) {
				console.warn(jqxhr.responseJSON);
			}
		} else {
			msg = jqxhr.responseText;
			if(typeof msg === 'undefined') {
				msg = "Ajax request broken or aborted for an unexpected reason. Please check console logs for more details.";
			}
			fpbxToast(msg,_("Error"),"exception");
			
			if (window.console) {
				console.warn(msg);
			}
		}
		$("#settings-cog").css("color","red");
		$("#settings-cog").prop("title",_("Ajax Error, check the console for more information"));
	});

	$("#login_admin").click(function() {
		var form = $("#login_form").html();
		$("<div></div>")
			.html(form)
			.dialog({
				title: _("Login"),
				resizable: false,
				width: 400,
				modal: true,
				close: function(e) {
					$(e.target).dialog("destroy").remove();
				},
				buttons: [
					{
						text: fpbx.msg.framework.continuemsg,
						click: function () {
							if (typeof checkPasswordReminder === "function") {
								if ($('div.ui-dialog-buttonpane.ui-widget-content.ui-helper-clearfix > div > button:nth-child(1)').hasClass("resetPasswordButton")) {
									resetAdminPassswordWithToken(this).then(value => {
										handleMFAFunc(value, this);
									})
								} else {
									checkPasswordReminder(this).then(value => {
										handleMFAFunc(value, this);
									})
								}
							} else {
								handleMFAFunc(true, this);
							}
						}
					},
					{
						text: fpbx.msg.framework.cancel,
						click: function() {
							$(this).dialog("destroy").remove();
						}
					}
				],
				focus: function() {
					$(":input", this).keyup(function(event) {
						if (event.keyCode == 13) {
							$(".ui-dialog-buttonpane button:first").click();
						}
					});
				}
			});
	});

	function handleMFAFunc(response, thisPointer) {
		if (response) {
			if (typeof checkMFAenabled === "function") {
				checkMFAenabled(false, false, false, '', thisPointer);
			} else {
				$(thisPointer).find("form").trigger("submit");
			}
		}
	}

	/**
	 * Remove all hidden secondary dropdown boxes for destinations if
	 * previous submit handlers have not already cancelled the submit
	 * so that we try to prevent the max input box limits of PHP.
	 * This should be kept as much last as possible so it gets fired
	 * after all other submit actions.
	 */
	$("form").submit(function(e) {
		// If the page isn't going to submit then don't remove the elements
		if (e.result) {
				$(".destdropdown2").filter(".hidden").remove();
		}
	});

	/**
	 * Scroll Minimal Function
	 * http://stackoverflow.com/questions/4217962/scroll-to-an-element-using-jquery
	 * @param {bool} smooth True if Smooth Scroll, False if doesnt matter
	 * @param {int} offset Page offset to scroll to
	 */
	jQuery.fn.scrollMinimal = function(smooth, offset) {
		var cTop = this.offset().top - offset,
				cHeight = this.outerHeight(true),
				windowTop = $(window).scrollTop(),
				visibleHeight = $(window).height();

		if (cTop < windowTop) {
			if (smooth) {
				$("body").animate({ "scrollTop": cTop }, "slow", "swing");
			} else {
				$(window).scrollTop(cTop);
			}
		} else if (cTop + cHeight > windowTop + visibleHeight) {
			if (smooth) {
				$("body").animate({ "scrollTop": cTop - visibleHeight + cHeight }, "slow", "swing");
			} else {
				$(window).scrollTop(cTop - visibleHeight + cHeight);
			}
		}
	};

	setTimeout(function () {
		if ($('#floating-nav-bar').hasClass('show')) {
			$("#floating-nav-bar").removeClass("show");
		}
	}, 10000);
});

/**
 * This is used to make input fields "bootstrappy"
 * <span class="btn btn-default btn-file">
 * 	Browse <input type="file" class="form-control" name="privatekey" id="privatekey">
 * </span>
 * <span class="filename"></span>
 */
 $(document).on('change', '.btn-file :file', function() {
		var input = $(this),
				label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
				input.parent().siblings(".filename").html(label);
});
$(document).ready(function(){
	//lock number inputs to only be numeric
	$("input[type=number]").numeric();

	/**
	 * This is used to provide a "maxsize" field addon for text inputs
	 * Example: <input type="text" name="foo" id="foo" class="form-control maxlen" maxlength="25" value="bar">
	 * This would show Foo Field: [bar        ][3/25]
	 * Input must have a maxlength and id attribute
	 *
	 */
	$(".maxlen").each(function(){
		var curid = $(this).attr('id');
		var maxl = $(this).attr('maxlength');
		var curl = $(this).val().length;
		$(this).wrap('<div class="input-group"></div>');
		$(this).after('<span class="input-group-addon" id="basic-addon-'+curid+'">'+curl+'/'+maxl+'</span>');
	});
	//Enable textarea autosizer
	autosize($('textarea.autosize'));

	//enable bootstrap multiselect
	$('select[multiple].bsmultiselect').multiselect({
		maxHeight: 300,
		includeSelectAllOption: true,
		enableFiltering: true,
		enableCaseInsensitiveFiltering: true
	});

	$('select[multiple].chosenmultiselect').chosen({
		width: '100%',
		search_contains: true
	});
	$('select.chosenselect').chosen({
		width: '100%',
		search_contains: true
	});
	/*
	 * Handle loooong bootnavs.
	 */
	if ($('div.bootnav').length && $('div.bootnav a.active').length){
		$('div.bootnav .list-group').scrollTop($('div.bootnav a.active').position().top);
	}

	if($(window).width() > 991) {
		$(".freepbx-navbar .dropdown-menu").css("max-height",$(window).height()-50);
		resizeMode = 'desktop';
	} else {
		resizeMode = 'mobile';
		$('table[data-toggle=table]').each(function() {
			if(!$(this).bootstrapTable('getOptions').cardView) {
				$(this).bootstrapTable('toggleView');
			}
		});
	}

	/*
	 * Scrolling Nav-Tabs
	 * Original thought taken from: http://www.bootply.com/l2ChB4vYmC
	 * Rewritten to work with multple tab groups and better scrolling
	 */
	if($(".nav-container").length > 0) {
		var scrollTab = function(container, direction) {
			var move = 0;
			container.find(".wrapper li:not(.hidden)").each(function() {
				var width = $(this).parents(".wrapper")[0].getBoundingClientRect().width,
						list = $(this).parents(".list"),
						scrollerright = $(this).parents(".nav-container").find(".scroller-right")[0].getBoundingClientRect().width,
						scrollerleft = $(this).parents(".nav-container").find(".scroller-left")[0].getBoundingClientRect().width,
						tabdimensions = $(this)[0].getBoundingClientRect(),
						seen = ((width - (tabdimensions.left + tabdimensions.width) + scrollerright) >=0);

				if(!seen && direction == "right") {
					move = -($(this).position().left - scrollerleft);
					return false;
				} else if(seen && direction == "left") {
					if(list.position().left + ($(this).position().left + scrollerright + width) > 0) {
						move = 0;
					} else {
						var test = $(this)[0].getBoundingClientRect().width;
						move = list.position().left + ($(this).position().left + scrollerright + width - test);
					}
					return false;
				}
			});
			return move;
		};

		var widthOfList = function(container){
			var itemsWidth = 0;
			container.find('.list li').each(function(){
				var itemWidth = $(this).outerWidth();
				itemsWidth+=itemWidth;
			});
			return itemsWidth;
		};

		var reAdjust = function(){
			$(".nav-container").each(function() {
				var container = $(this),
						t = container.find('.wrapper').outerWidth(),
						p = container.find('.list').position().left,
						w = widthOfList(container);
				if((w - t + p) < 0) {
					container.find('.scroller-right i').hide();
				} else {
					container.find('.scroller-right i').show();
				}
				if(p >= 0) {
					container.find('.scroller-left i').hide();
				} else {
					container.find('.scroller-left i').show();
				}
			});
		};

		reAdjust();
		$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
			reAdjust();
		});
		$(window).on('resize',function(e){
			reAdjust();
		});

		$('.nav-container .scroller-right i').click(function() {
			var $this = this,
					container = $($this).parents(".nav-container"),
					moving = container.data("moving"),
					t = container.find('.wrapper').outerWidth(),
					p = container.find('.list').position().left,
					w = widthOfList(container),
					final = -(t);

			if(typeof moving !== "undefined" && moving) {
				return;
			}
			var move = scrollTab(container, "right");

			container.data("moving", true);
			$($this).addClass("moving");
			container.find('.scroller-left i').fadeIn('slow');

			if((w - (-move+t)) <= 0) {
				$($this).fadeOut('slow');
			}
			container.find('.list').animate({left:move+"px"},'slow',function(){
				container.data("moving", false);
				$($this).removeClass("moving");
			});
		});

		$('.nav-container .scroller-left i').click(function() {
			var $this = this,
					container = $($this).parents(".nav-container"),
					moving = container.data("moving");

			if(typeof moving !== "undefined" && moving) {
				return;
			}
			var move = scrollTab(container, "left");

			container.data("moving", true);
			$($this).addClass("moving");
			container.find('.scroller-right i').fadeIn('slow');

			if(move >= 0) {
				$($this).fadeOut('slow');
			}
			container.find('.list').animate({left:move+"px"},'slow',function(){
				container.data("moving", false);
				$($this).removeClass("moving");
			});
		});
	}
});
$(".maxlen").keyup(function(){
		var curid = $(this).attr('id');
		var maxl = $(this).attr('maxlength');
		var curl = $(this).val().length;
		$('#basic-addon-'+curid).html(curl+'/'+maxl);
});

/*
 * Confirm Deletion on grid actions. Add class "delAction to delete links."
 */
$(document).on('click','.delAction', function(e){
	var confirmed = confirm(_("Are you sure you want to delete this item?"));
	return confirmed;
});

//Prevent links from jumping to the top of the page
$(document).on('click','.clickable', function(e){
	e.preventDefault();
});

var resizeMode = 'desktop';
//Menu autoresize to height of browser viewport
$(window).resize(function() {
	if($(window).width() < 992) {
		$(".freepbx-navbar .dropdown-menu").css("max-height","");
		if(resizeMode != 'mobile') {
			$('table[data-toggle=table]').each(function() {
				if(!$(this).bootstrapTable('getOptions').cardView) {
					$(this).bootstrapTable('toggleView');
				}
			});
		}
		resizeMode = 'mobile';
	} else if($(window).width() > 991) {
		$(".freepbx-navbar .dropdown-menu").css("max-height",$(window).height()-50);
		if(resizeMode != 'desktop') {
			$('table[data-toggle=table]').each(function() {
				if($(this).bootstrapTable('getOptions').cardView) {
					$(this).bootstrapTable('toggleView');
				}
			});
		}
		resizeMode = 'desktop';
	}
});


/*
$.fn.overflown = function() {
	var e = this[0];
	console.log(e.scrollHeight);
	console.log(e.clientHeight);
	return e.scrollHeight > e.clientHeight || e.scrollWidth > e.clientWidth;
};

$(".freepbx-navbar .dropdown-toggle").hover(function(e){
	var el = $(e.currentTarget).parents(".dropdown").children(".dropdown-menu"),
			parent = $(e.currentTarget).parents(".dropdown");
	if(typeof el !== "undefined" && !parent.hasClass("open")) {
		el.show();
		$(this).trigger( "show.bs.dropdown", e );
	}
}, function(e) {
	var el = $(e.currentTarget).parents(".dropdown").children(".dropdown-menu"),
			parent = $(e.currentTarget).parents(".dropdown"),
			$this = this;
	if(typeof el !== "undefined" && !$(e.relatedTarget).hasClass("dropdown-menu") && !parent.hasClass("open")) {
		el.hide();
		$(this).trigger( "hide.bs.dropdown", e );
	} else if($(e.relatedTarget).hasClass("dropdown-menu")) {
		$(e.relatedTarget).one('mouseleave', function() {
			if(!parent.hasClass("open")) {
				el.hide();
				$($this).trigger( "hide.bs.dropdown", e );
			}
		});
	}
});

$('.freepbx-navbar').on('show.bs.dropdown', function () {
	console.log("yes");
});

$('.freepbx-navbar').on('hide.bs.dropdown', function () {
	console.log("no");
});
*/
function fpbxToast(message,title,level){
	level = typeof level !== 'undefined'?level:'info';
	title = typeof title !== 'undefined'?title:'';
	switch (level) {
		case 'info':
			notie.alert(4,message,3);
		break;
		case 'warning':
			notie.alert(2,message,3);
		break;
		case 'success':
			notie.alert(1,message,3);
		break;
		case 'error':
			notie.alert(3,message,3);
		break;
		case 'exception':
			notie.alert(3,message, 4);
		break;
	}
}

function fpbxConfirm(question, truetext, falsetext, callbackfn){
	notie.confirm(question, truetext, falsetext, callbackfn);
}

$(".custom-select").change(function() {
	if($(this).is("select") && $(this).val() == "custom") {
		$(this).replaceWith('<input type="text" id="'+$(this).attr("id")+'" name="'+$(this).attr("id")+'" value="" class="form-control">');
	}
});

// Add class that sets an input to readonly until the user clicks on it. This should prevent autofilling of things like passwords and usernames.
$( document ).ready(function() {
	$('.clicktoedit').prop('readonly',true);
});
$(document).on('click','.clicktoedit',function(){
	$(this).prop('readonly',false);
	$(this).focus();
});

(function authPing(){
	setTimeout(function(){
		if($('.admin-btn').length > 1){
			$.get('ajax.php?command=authping');
			authPing(); //loop
		}
	}, 130000);
})();
