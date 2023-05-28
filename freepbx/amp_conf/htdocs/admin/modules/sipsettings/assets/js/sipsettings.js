var changed = false,
		theForm = document.editSip;

$(document).ready(function() {
	$('.sortable').sortable(	{
	   update: function(event, ui) {
			//console.log(ui.item.find('input').val(), ui.item.index())
			ui.item.unbind("click");
			ui.item.find('input').val(ui.item.index());
	   }
	});
	$("form").submit(function() {
		if(changed) {
			alert(_("Port/Bind Address has changed. This requires an Asterisk restart after Apply Config"));
		}
		var extip = document.getElementById("externip1");
		if(typeof extip !== "undefined" && extip !== null){
			if(extip.checked){
				if($("#externip").val().length < 1 && $("#externip_val").val().length < 1){
						warnInvalid($("#externip_val"),_("External IP can not be blank when NAT Mode is set to Static and no default IP address provided on the main page"));
						return false;
				}
			}
		}
		return checkBindConflicts();
	});
	$("#sip #bindaddr").bind('input propertychange', function() {
		changed = true;
	});
	$("#sip #bindport").bind('input propertychange', function() {
		changed = true;
	})
	$(".port").bind('input propertychange', function() {
		changed = true;
	})

	/* Add a Local Network / Mask textbox */
	$("#localnet-add").click(function(){
		addLocalnet("","");
	});

	/* Add a Custom Var / Val textbox */
	$("#sip-custom-add").click(function(){
		addCustomField("","");
	});

	$("#ice-host-candidates-add").click(function(e){
		e.preventDefault();
		var idx = $(".ice-host-candidate").size(),
				idxp = idx - 1;

		$("#ice-host-candidates-buttons").before('\
			<div class="form-group form-inline">\
				<input type="hidden" id="ice_host_candidates_count" name="ice_host_candidates_count[]" value="'+idx+'"> \
				<input type="text" id="ice_host_candidates_local_'+idx+'" name="ice_host_candidates_local_'+idx+'" class="form-control ice-host-candidate" value=""> =>\
				<input type="text" id="ice_host_candidates_advertised_'+idx+'" name="ice_host_candidates_advertised_'+idx+'" class="form-control" value="">\
			</div>\
		');
	});

	$("#ice-blacklist-add").click(function(e){
		e.preventDefault();
		var idx = $(".ice-blacklist").size(),
				idxp = idx - 1;

		$("#ice-blacklist-buttons").before('\
			<div class="form-group form-inline">\
				<input type="hidden" id="ice_blacklist_count" name="ice_blacklist_count[]" value="'+idx+'"> \
				<input type="text" id="ice_blacklist_ip_'+idx+'" name="ice_blacklist_ip_'+idx+'" class="form-control ice-blacklist" value=""> /\
				<input type="text" id="ice_blacklist_subnet_'+idx+'" name="ice_blacklist_subnet_'+idx+'" class="form-control ice-blacklist" value=""> \
			</div>\
		');
	});

	/* Initialize Nat GUI and respond to radio button presses */
	/* FREEPBX-13792 detect network settings fails when only using pjsip channel driver*/
	if(document.getElementById("externhost") !=null){
		if (document.getElementById("externhost").checked) {
			$(".externip").hide();
		} else if (document.getElementById("externip1").checked) {
			$(".externhost").hide();
		} else {
			$(".nat-settings").hide();
		}
	}
	$("#nat-none").click(function(){
		$(".nat-settings").hide();
	});
	$("#externip1").click(function(){
		$(".nat-settings").show();
		$(".externhost").hide();
	});
	$("#externhost").click(function(){
		$(".nat-settings").show();
		$(".externip").hide();
	});

	/* Initialize Video Support settings and show/hide */
	/* FREEPBX-13792 detect network settings fails when only using pjsip channel driver*/
	if(document.getElementById("videosupport-no") != null){
		if (document.getElementById("videosupport-no").checked) {
			$(".video-codecs").hide();
		}
	}
		$("#videosupport-yes").click(function(){
			$(".video-codecs").show();
		});
		$("#videosupport-no").click(function(){
			$(".video-codecs").hide();
		});

		/* Initialize Jitter Buffer settings and show/hide */
	/* FREEPBX-13792 detect network settings fails when only using pjsip channel driver*/
	if(document.getElementById("jbenable-no") != null){
		if (document.getElementById("jbenable-no").checked) {
			$(".jitter-buffer").hide();
		}
	}
		$("#jbenable-yes").click(function(){
			$(".jitter-buffer").show();
		});
		$("#jbenable-no").click(function(){
			$(".jitter-buffer").hide();
		});

		$("#autodetect").click(function(e) { e.preventDefault(); detectExtern() });
		var path = window.location.pathname.toString().split('/');
		path[path.length - 1] = 'ajax.php';
		// Oh look, IE. Hur Dur, I'm a bwowsah.
		if (typeof(window.location.origin) == 'undefined') {
			window.location.origin = window.location.protocol+'//'+window.location.host;
		}
		window.ajaxurl = window.location.origin + path.join('/');
		// This assumes the module name is the first param.
		window.modulename = window.location.search.split(/\?|&/)[1].split('=')[1];

	$("#nat-auto-configure").click(function(){
		$.ajax({
			type: 'POST',
			url: "config.php",
			data: "quietmode=1&skip_astman=1&handler=file&module=sipsettings&file=natget.html.php",
			dataType: 'json',
			timeout: 10000,
			success: function(data) {
				if (data.status == 'success') {
					$('.netmask').attr("value","");
					$('.localnet').attr("value","");
					$('#externip_val').attr("value",data.externip);
					/*  Iterate through each localnet:netmask pair. Put them into any fields on the form
					 *  until we have no more, than create new ones
					 */
					var fields = $(".localnet").size();
					var cnt = 0;
					$.each(data.localnet, function(loc,mask){
						if (cnt < fields) {
							$('#localnet_'+cnt).attr("value",loc);
							$('#netmask_'+cnt).attr("value",mask);
						} else {
							//addLocalnet(loc,mask);
						}
						cnt++;
					});
				} else {
					alert(data.status);
				}
			},
			error: function(data) {
				alert(_("An Error occurred trying fetch network configuration and external IP address"));
			},
		});
		return false;
	});


	// If someone clicks on a '0.0.0.0' pjsip selector, we automatically turn off
	// any OTHER selectors for that protocol, as pjsip ignores them once 0.0.0.0 is
	// enabled.
	$(".btn-all[value=on]").on("click", function(e) {
		var proto = $(e.target).data('proto');
		$(".btn-notall.btn-proto-"+proto+"[value=on]").prop('checked', false);
		$(".btn-notall.btn-proto-"+proto+"[value=off]").prop('checked', true);
	});
	// Additionally, if someone turns on a selector that is NOT 0.0.0.0, we turn off
	// 0.0.0.0, if it's on.
	$(".btn-notall[value=on]").on("click", function(e) {
		var proto = $(e.target).data('proto');
		$(".btn-all.btn-proto-"+proto+"[value=on]").prop('checked', false);
		$(".btn-all.btn-proto-"+proto+"[value=off]").prop('checked', true);
	});

	$( "#pjsip_identifers_sortable" ).sortable({
		placeholder: "sortable-placeholder",
		tolerance: "pointer",
		cursorAt: {top:25, left:15}
	});

	$("form").submit(function() {
		var sortedIDs = $( "#pjsip_identifers_sortable" ).sortable( "toArray" );
		$($(this)).append("<input type='hidden' name='pjsip_identifers_order' value='"+JSON.stringify(sortedIDs)+"' />");
	});

});

/**
 * Insert a sip_settings/sip_value pair of text boxes
 * @param {string} key The custom field key
 * @param {string} val The custom field value
 */
function addCustomField(key, val) {
	var idx = $(".sip-custom").size(),
			idxp = idx - 1;

	$("#sip-custom-buttons").before('\
		<div class="form-group form-inline">\
			<input type="text" id="sip_custom_key_'+idx+'" name="sip_custom_key_'+idx+'" class="sip-custom" value="'+key+'"> =\
			<input type="text" id="sip_custom_val_'+idx+'" name="sip_custom_val_'+idx+'" value="'+val+'">\
		</div>\
	');
}

/**
 * Check for port conflicts
 * @return {bool} true if we can proceed, false otherwise
 */
function checkBindConflicts() {
	if($("#sip").length > 0 && $("#pjsip").length > 0) {
		var sipaddr = $("#sip #bindaddr").val(),
			sipport = $("#sip #bindport").val(),
			submit = true;

		sipaddr = (sipaddr.trim() != "") ? sipaddr : '0.0.0.0';
		sipport = (sipport.trim() != "") ? sipport : '5060';

		$(".port").each(function() {			
			var ip = $(this).data("ip");
			if(sipaddr == ip && sipport == $(this).val()) {
				submit = false;
				warnInvalid($(this),_("PJSIP transport port conflicts with SIP port"));
				return false;
			}
		})
	}
	return submit;
}

/**
 * Detect External addresses
 */
function detectExtern() {
	$("#externip").val("").attr("placeholder", _('Loading')+"...").attr("disabled", true);
	$(".localnet").prop("disabled", true);
	$(".netmask").prop("disabled", true);
	$.ajax({
		url: window.ajaxurl,
		data: { command: 'getnetworking', module: window.modulename }
	}).done(function(data) {
		var placeholder = typeof data.externipmesg !== "undefined" ? data.externipmesg : _("Enter IP Address");
		$("#externip").val("").prop("placeholder", placeholder);
		$("#externip").one("click", function() {
			$(this).prop("placeholder",_("Enter IP Address"));
		})
		if(data.status) {
			updateAddrAndRoutes(data);
		} else {
			alert( sprintf(_("Error: %s"), data.message) );
		}
	}).fail(function(err) {
		alert( sprintf(_("Error: %s"), err.responseJSON.error) );
		$("#externip").val("").prop("placeholder", _("Enter IP Address"));
	}).always(function() {
		$("#externip").prop("disabled", false);
		$(".localnet").prop("disabled", false);
		$(".netmask").prop("disabled", false);
	});
}

/**
 * Update Addresses and Routes
 * @param  {[type]} data [description]
 */
function updateAddrAndRoutes(data) {
	window.d = data;
	if (data.externip != false) {
		$("#externip").val(data.externip);
	}

	// Now, go through our detected networks, and see if we need to add them.
	$.each(d.routes, function() {
		var sel = ".network[value='"+this[0]+"']";
		if (!$(sel).length) {
			// Add it
			addLocalnet(this[0], this[1]);
		}
	});
}

/**
 * Insert a localnet/netmask pair of text boxes
 * @param {string} localnet The localhost
 * @param {string} netmask  The netmask
 */
function addLocalnet(net, cidr) {
	// We'd like a new one, please.
	var last = $(".lnet:last"),
			ourid = last.data('nextid'),
			nextid = ourid + 1;

	var html = "<div class = 'lnet form-group form-inline' data-nextid="+nextid+">";
	html += "<input type='text' name='localnets["+ourid+"][net]' class='form-control localnet network validate-ip' value='"+net+"'> / ";
	html += "<input type='text' name='localnets["+ourid+"][mask]' class='form-control localnet cidr validate-netmask' value='"+cidr+"'>";
	html += "</div>\n";

	last.after(html);
}
