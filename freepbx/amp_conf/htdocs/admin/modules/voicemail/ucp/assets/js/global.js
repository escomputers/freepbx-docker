var VoicemailC = UCPMC.extend({
	init: function() {
		this.loaded = null;
		this.recording = false;
		this.recorder = null;
		this.recordTimer = null;
		this.startTime = null;
		this.soundBlobs = {};
		this.placeholders = [];
	},
	resize: function(widget_id) {
		$(".grid-stack-item[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('resetView',{height: $(".grid-stack-item[data-id='"+widget_id+"'] .widget-content").height()});
	},
	findmeFollowState: function() {
		if (!$("#vmx-p1_enable").is(":checked") && $("#ddial").is(":checked") && $("#vmx-state").is(":checked")) {
			$("#vmxerror").text(_("Find me Follow me is enabled when VmX locator option 1 is disabled. This means VmX Locator will be skipped, instead going directly to Find Me/Follow Me")).addClass("alert-danger").fadeIn("fast");
		} else {
			$("#vmxerror").fadeOut("fast");
		}
	},
	saveVmXSettings: function(ext, key, value) {
		var data = { ext: ext, settings: { key: key, value: value } };
		$.post( UCP.ajaxUrl + "?module=voicemail&command=vmxsettings", data, function( data ) {
			if (data.status) {
				$("#vmxmessage").text(data.message).addClass("alert-" + data.alert).fadeIn("fast", function() {

				});
			} else {
				return false;
			}
		});
	},
	poll: function(data) {
		if (typeof data.boxes === "undefined") {
			return;
		}

		var notify = false;
		var self = this;

		/**
		 * Check all extensions and boxes at once.
		 */
		$.ajax({
			type: "POST",
			url: UCP.ajaxUrl + "?module=voicemail&command=checkextensions",
			async: false,
			data: data.boxes,
			success: function(vm_data){				
				window.vm_data = vm_data;
			},
			error: function (xhr, ajaxOptions, thrownError) {
                console.error('Unable to check extensions', thrownError, xhr);
            },
		  });

		async.forEachOf(window.vm_data, function (value, extension, callback) {	
			var el = $(".grid-stack-item[data-rawname='voicemail'][data-widget_type_id='"+extension+"'] .mailbox");
			self.refreshFolderCount(extension);
			if(el.length && el.data("inbox").status != value.status || window.update_table == true) {
				notify = false;
				if(el.data("inbox") < value){
					notify = true;
				}
				el.data("inbox",value);	
				if((typeof Cookies.get('vm-refresh-'+extension) === "undefined" && (typeof Cookies.get('vm-refresh-'+extension) === "undefined" || Cookies.get('vm-refresh-'+extension) == 1)) || Cookies.get('vm-refresh-'+extension) == 1) {
					$(".grid-stack-item[data-rawname='voicemail'][data-widget_type_id='"+extension+"'] .voicemail-grid").bootstrapTable('refresh',{silent: true});
				}
			}			
			callback();
		}, function(err) {
			if( err ) {
			} else if(notify) {
				voicemailNotification = new Notify("Voicemail", {
					body: _("You have a new voicemail"),
					icon: "modules/Voicemail/assets/images/mail.png"
				});
				if (UCP.notify) {
					voicemailNotification.show();
				}
			}
		});
	},
	displayWidgetSettings: function(widget_id, dashboard_id) {
		var self = this,
				extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");

		/* Settings changes binds */
		$("#widget_settings .widget-settings-content input[type!='checkbox'][id!=vm-refresh]").change(function() {
			$(this).blur(function() {
				self.saveVMSettings(extension);
				$(this).off("blur");
			});
		});
		$("#widget_settings .widget-settings-content input[type='checkbox'][id!=vm-refresh]").change(function() {
			self.saveVMSettings(extension);
		});

		$("#widget_settings .widget-settings-content input[id=vm-refresh]").change(function() {
			Cookies.remove('vm-refresh-'+extension, {path: ''});
			if($(this).is(":checked")) {
				Cookies.set('vm-refresh-'+extension, 1);
			} else {
				Cookies.set('vm-refresh-'+extension, 0);
			}
		});
		if((typeof Cookies.get('vm-refresh-'+extension) === "undefined" && (typeof Cookies.get('vm-refresh-'+extension) === "undefined" || Cookies.get('vm-refresh-'+extension) == 1)) || Cookies.get('vm-refresh-'+extension) == 1) {
			$("#widget_settings .widget-settings-content input[id=vm-refresh]").prop("checked",true);
		} else {
			$("#widget_settings .widget-settings-content input[id=vm-refresh]").prop("checked",false);
		}
		$("#widget_settings .widget-settings-content input[id=vm-refresh]").bootstrapToggle('destroy');
		$("#widget_settings .widget-settings-content input[id=vm-refresh]").bootstrapToggle({
			on: _("Enable"),
			off: _("Disable")
		});
		this.greetingsDisplay(extension);
		this.bindGreetingPlayers(extension);
		$("#widget_settings .vmx-setting").change(function() {
			var name = $(this).attr("name"),
					val = $(this).val();
			if($(this).attr("type") == "checkbox") {
				self.saveVmXSettings(extension, name, $(this).is(":checked"));
			} else {
				self.saveVmXSettings(extension, name, val);
			}

		});
	},
	displayWidget: function(widget_id, dashboard_id) {
		var self = this,
				extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");
		$(".grid-stack-item[data-id='"+widget_id+"'] .voicemail-grid").one("post-body.bs.table", function() {
			setTimeout(function() {
				self.resize(widget_id);
			},250);
		});

		$("div[data-id='"+widget_id+"'] .voicemail-grid").on("post-body.bs.table", function (e) {
			$("div[data-id='"+widget_id+"'] .voicemail-grid a.listen").click(function() {
				var id = $(this).data("id"),
						select = '';
				$.each(self.staticsettings.extensions, function(i,v) {
					select = select + "<option value='"+v+"'>"+v+"</option>";
				});
				UCP.showDialog(_("Listen to Voicemail"),
					_("On") + ':</label><select class="form-control" data-toggle="select" id="VMto">'+select+"</select>",
					'<button class="btn btn-default" id="listenVM">' + _("Listen") + "</button>",
					function() {
						$("#listenVM").click(function() {
							var recpt = $("#VMto").val();
							self.listenVoicemail(id,extension,recpt);
						});
						$("#VMto").keypress(function(event) {
							if (event.keyCode == 13) {
								var recpt = $("#VMto").val();
								self.listenVoicemail(id,extension,recpt);
							}
						});
					}
				);
			});
			$("div[data-id='"+widget_id+"'] .voicemail-grid .clickable").click(function(e) {
				var text = $(this).text();
				if (UCP.validMethod("Contactmanager", "showActionDialog")) {
					UCP.Modules.Contactmanager.showActionDialog("number", text, "phone");
				}
			});
			$("div[data-id='"+widget_id+"'] .voicemail-grid a.forward").click(function() {
				var id = $(this).data("id"),
						select = '';

				$.each(self.staticsettings.mailboxes, function(i,v) {
					select = select + "<option value='"+v+"'>"+v+"</option>";
				});
				UCP.showDialog(_("Forward Voicemail"),
					_("To")+':</label><select class="form-control" id="VMto">'+select+'</select>',
					'<button class="btn btn-default" id="forwardVM">' + _("Forward") + "</button>",
					function() {
						$("#forwardVM").click(function() {
							var recpt = $("#VMto").val();
							self.forwardVoicemail(id,extension,recpt, function(data) {
								if(data.status) {
									UCP.showAlert(sprintf(_("Successfully forwarded voicemail to %s"),recpt));
									UCP.closeDialog();
								}
							});
						});
						$("#VMto").keypress(function(event) {
							if (event.keyCode == 13) {
								var recpt = $("#VMto").val();
								self.forwardVoicemail(id,extension,recpt, function(data) {
									if(data.status) {
										UCP.showAlert(sprintf(_("Successfully forwarded voicemail to %s"),recpt));
										UCP.closeDialog();
									}
								});
							}
						});
					}
				);
			});
			$("div[data-id='"+widget_id+"'] .voicemail-grid a.delete").click(function() {
				var extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");
				var id = $(this).data("id");
				UCP.showConfirm(_("Are you sure you wish to delete this voicemail?"),'warning',function() {
					self.deleteVoicemail(id, extension, function(data) {
						if(data.status) {
							$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('remove', {field: "msg_id", values: [String(id)]});
						}
					});
				});
			});
			self.bindPlayers(widget_id);
		});
		$("div[data-id='"+widget_id+"'] .voicemail-grid").on("check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table", function () {
			var sel = $(this).bootstrapTable('getAllSelections'),
					dis = true;
			if(sel.length) {
				dis = false;
			}
			$("div[data-id='"+widget_id+"'] .delete-selection").prop("disabled",dis);
			$("div[data-id='"+widget_id+"'] .forward-selection").prop("disabled",dis);
			$("div[data-id='"+widget_id+"'] .move-selection").prop("disabled",dis);
		});

		$("div[data-id='"+widget_id+"'] .folder").click(function() {
			$("div[data-id='"+widget_id+"'] .folder").removeClass("active");
			$(this).addClass("active");
			folder = $(this).data("folder");
			$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('refreshOptions',{
				url: UCP.ajaxUrl+'?module=voicemail&command=grid&folder='+folder+'&ext='+extension
			});
		});

		$("div[data-id='"+widget_id+"'] .move-selection").click(function() {
			var opts = '', cur = (typeof $.url().param("folder") !== "undefined") ? $.url().param("folder") : "INBOX", sel = $("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('getAllSelections');
			$.each($("div[data-id='"+widget_id+"'] .folder-list .folder"), function(i, v){
				var folder = $(v).data("folder");
				if(folder != cur) {
					opts += '<option>'+$(v).data("name")+'</option>';
				}
			});
			UCP.showDialog(_("Move Voicemail"),
				_("To")+':</label><select class="form-control" data-toggle="select" id="VMmove">'+opts+"</select>",
				'<button class="btn btn-default" id="moveVM"><span id="spin"></span>&nbsp;&nbsp;' + _("Move") + "</button>",
				function() {
					var total = sel.length;
					$("#moveVM").click(function() {
						$("#moveVM").prop("disabled",true);
						$("#spin").html('<i class="fa fa-spinner fa-spin"></i>')
						setTimeout(function () {
							let data = [];
							Object.keys(sel).forEach(key => {
								data.push({
									msg: sel[key].msg_id,
									folder: $("#VMmove").val(),
									ext: extension
								});
							})
							self.moveVoicemailBulk(data, extension, function (data) {
								if (data.status) {
									self.rebuildVM(extension);
									if (data.moveStatus.includes(false)) {
										UCP.showAlert('Not able to move some of the voicemails.');
									}
									setTimeout(function () {
										UCP.closeDialog();
									}, 2000);
								} else {
									$("#moveVM").prop("disabled", false);
									UCP.showAlert(data.error);
								}
							})
						}, 50);
					});
					$("#VMmove").keypress(function(event) {
						if (event.keyCode == 13) {
							$("#moveVM").prop("disabled",true);
							async.forEachOf(sel, function (v, i, callback) {
								self.moveVoicemail(v.msg_id, $("#VMmove").val(), extension, function(data) {
									if(data.status) {
										$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('remove', {field: "msg_id", values: [String(v.msg_id)]});
									}
									callback();
								})
							}, function(err) {
								if( err ) {
									$("#moveVM").prop("disabled",false);
									UCP.showAlert(err);
								} else {
									UCP.closeDialog();
									self.rebuildVM(extension);
								}
							});
						}
					});
					$(".delete-selection").prop("disabled",true);
					$(".forward-selection").prop("disabled",true);
					$(".move-selection").prop("disabled",true);
				}
			);
		});
		$("div[data-id='" + widget_id + "'] .delete-selection").click(function () {
			$('#modal_confirm_button').attr("data-dismiss", '');
			UCP.showConfirm(_("Are you sure you wish to delete these voicemails?"),'warning',function() {
				var extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");
				var sel = $("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('getAllSelections');
				var accept = $("#modal_confirm_button").text();
				$("#modal_confirm_button").html('<i class="fa fa-spinner fa-spin"></i>&nbsp;'+ accept);
				setTimeout(function () {
					let data = [];
					Object.keys(sel).forEach(key => {
						data.push({
							msg: sel[key].msg_id,
							folder: $("#VMmove").val(),
							ext: extension
						});
					});
					self.deleteVoicemailBulk(data, extension, function (data) {
						if (data.status) {
							self.rebuildVM(extension);
							if (data.deleteStatus.includes(false)) {
								UCP.showAlert('Not able to delete some of the voicemails.');
							}
							setTimeout(function () {
								$("#modal_confirm_button").html(accept);
								$("#confirm_modal").modal('toggle');
							}, 2000);
						} else {
							UCP.showAlert(data.error);
						}
					});
				}, 50);
				$(".delete-selection").prop("disabled",true);
				$(".forward-selection").prop("disabled",true);
				$(".move-selection").prop("disabled",true);
			});
		});
		$("div[data-id='"+widget_id+"'] .forward-selection").click(function() {
			var sel = $("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('getAllSelections');
			UCP.showDialog(_("Forward Voicemail"),
				_("To")+":</label><input type='text' class='form-control' id='VMto'>",
				'<button class="btn btn-default" id="forwardVM">' + _("Forward") + "</button>",
				function() {
					$("#forwardVM").click(function() {
						setTimeout(function() {
							var recpt = $("#VMto").val();
							$.each(sel, function(i, v){
								self.forwardVoicemail(v.msg_id,extension,recpt, function(data) {
									if(data.status) {
										UCP.showAlert(sprintf(_("Successfully forwarded voicemail to %s"),recpt));
										$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('uncheckAll');
										UCP.closeDialog();
									}
								});
							});
						}, 50);
					});
					$("#VMto").keypress(function(event) {
						if (event.keyCode == 13) {
							var recpt = $("#VMto").val();
							$.each(sel, function(i, v){
								self.forwardVoicemail(v.msg_id,extension,recpt, function(data) {
									if(data.status) {
										UCP.showAlert(sprintf(_("Successfully forwarded voicemail to %s"),recpt));
										$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('uncheckAll');
										UCP.closeDialog();
									}
								});
							});
						}
					});
				}
			);
		});


		$("div[data-id='"+widget_id+"'] .voicemail-grid .clickable").click(function(e) {
			var text = $(this).text();
			if (UCP.validMethod("Contactmanager", "showActionDialog")) {
				UCP.Modules.Contactmanager.showActionDialog("number", text, "phone");
			}
		});
	},
	greetingsDisplay: function(extension) {
		var self = this;
		$("#widget_settings .recording-controls .save").click(function() {
			var id = $(this).data("id");
			self.saveRecording(extension,id);
		});
		$("#widget_settings .recording-controls .delete").click(function() {
			var id = $(this).data("id");
			self.deleteRecording(extension,id);
		});
		$("#widget_settings .file-controls .record, .jp-record").click(function() {
			var id = $(this).data("id");
			self.recordGreeting(extension,id);
		});
		$("#widget_settings .file-controls .delete").click(function() {
			var id = $(this).data("id");
			self.deleteGreeting(extension,id);
		});
		$("#widget_settings .filedrop").on("dragover", function(event) {
			if (event.preventDefault) {
				event.preventDefault(); // Necessary. Allows us to drop.
			}
			$(this).addClass("hover");
		});
		$("#widget_settings .filedrop").on("dragleave", function(event) {
			$(this).removeClass("hover");
		});

		$("#widget_settings .greeting-control .jp-audio-freepbx").on("dragstart", function(event) {
			event.originalEvent.dataTransfer.effectAllowed = "move";
			event.originalEvent.dataTransfer.setData("type", $(this).data("type"))
			$(this).fadeTo( "fast", 0.5);
		});
		$("#widget_settings .greeting-control .jp-audio-freepbx").on("dragend", function(event) {
			$(this).fadeTo( "fast", 1.0);
		});
		$("#widget_settings .filedrop").on("drop", function(event) {
			if (event.originalEvent.dataTransfer.files.length === 0) {
				if (event.stopPropagation) {
					event.stopPropagation(); // Stops some browsers from redirecting.
				}
				if (event.preventDefault) {
					event.preventDefault(); // Necessary. Allows us to drop.
				}
				$(this).removeClass("hover");
				var target = $(this).data("type"),
				source = event.originalEvent.dataTransfer.getData("type");
				if (source === "") {
					alert(_("Not a valid Draggable Object"));
					return false;
				}
				if (source == target) {
					alert(_("Dragging to yourself is not allowed"));
					return false;
				}
				var data = { ext: extension, source: source, target: target },
				message = $(this).find(".message");
				message.text(_("Copying..."));
				$.post( UCP.ajaxUrl + "?module=voicemail&command=copy", data, function( data ) {
						if (data.status) {
							$("#"+target+" .filedrop .pbar").css("width", "0%");
							$("#"+target+" .filedrop .message").text($("#"+target+" .filedrop .message").data("message"));
							$("#freepbx_player_" + target).removeClass("greet-hidden");
							self.toggleGreeting(target, true);
						} else {
							return false;
						}
				});
			} else {}
		});
		$("#widget_settings .greeting-control").each(function() {
			var id = $(this).attr("id");
			$("#"+id+" input[type=\"file\"]").fileupload({
				url: UCP.ajaxUrl + "?module=voicemail&command=upload&type="+id+"&ext=" + extension,
				dropZone: $("#"+id+" .filedrop"),
				dataType: "json",
				add: function(e, data) {
					//TODO: Need to check all supported formats
					var sup = "\.("+self.staticsettings.supportedRegExp+")$",
							patt = new RegExp(sup),
							submit = true;
					$.each(data.files, function(k, v) {
						if(!patt.test(v.name)) {
							submit = false;
							UCP.showAlert(_("Unsupported file type"));
							return false;
						}
					});
					if(submit) {
						$("#"+id+" .filedrop .message").text(_("Uploading..."));
						data.submit();
					}
				},
				done: function(e, data) {
					if (data.result.status) {
						$("#"+id+" .filedrop .pbar").css("width", "0%");
						$("#"+id+" .filedrop .message").text($("#"+id+" .filedrop .message").data("message"));
						$("#freepbx_player_"+id).removeClass("greet-hidden");
						self.toggleGreeting(id, true);
					} else {
						console.warn(data.result.message);
					}
				},
				progressall: function(e, data) {
					var progress = parseInt(data.loaded / data.total * 100, 10);
					$("#"+id+" .filedrop .pbar").css("width", progress + "%");
				},
				drop: function(e, data) {
					$("#"+id+" .filedrop").removeClass("hover");
				}
			});
		});
		//If browser doesnt support get user media requests then just hide it from the display
		if (!Modernizr.getusermedia) {
			$("#widget_settings .jp-record-wrapper").hide();
			$("#widget_settings .record-greeting-btn").hide();
		} else {
			$("#widget_settings .jp-record-wrapper").show();
			$("#widget_settings .jp-stop-wrapper").hide();
			$("#widget_settings .record-greeting-btn").show();
		}
	},
	//Delete a voicemail greeting
	deleteGreeting: function(extension,type) {
		var self = this, data = { msg: type, ext: extension };
		$.post( UCP.ajaxUrl + "?module=voicemail&command=delete", data, function( data ) {
			if (data.status) {
				$("#freepbx_player_" + type).jPlayer( "clearMedia" );
				self.toggleGreeting(type, false);
			} else {
				return false;
			}
		});
	},
	refreshFolderCount: function(extension) {
		var data = window.vm_data[extension];
		if(data.status) {
			window.update_table = false;
			$.each(data.folders, function(i,v) {		
				cur_val = $(".grid-stack-item[data-rawname='voicemail'][data-widget_type_id="+extension+"] .mailbox .folder-list .folder[data-name='"+v.name+"'] .badge").text();
				if(cur_val != v.count){
					window.update_table = true;
				}
				$(".grid-stack-item[data-rawname='voicemail'][data-widget_type_id="+extension+"] .mailbox .folder-list .folder[data-name='"+v.name+"'] .badge").text(v.count);				
			});
		}

	},
	rebuildVM: function(extension){
		var data = {
			ext: extension
		},
		self = this;
		$.ajax({
			type: "POST",
			url: UCP.ajaxUrl + "?module=voicemail&command=rebuildVM",
			data: data,
			success: function(data) {
				self.refreshFolderCount(extension);
				if(typeof callback === "function") {
					callback(data);
				}	
			},
			error: function(data) {
				if(typeof callback === "function") {
					callback({status: false});
				}
			}
		});
	},
	moveVoicemail: function(msgid, folder, extension, callback) {
		var data = {
			msg: msgid,
			folder: folder,
			ext: extension
		},
		self = this;
		$.ajax({
			type: "POST",
			url: UCP.ajaxUrl + "?module=voicemail&command=moveToFolder",
			data: data,
			async: false,
			success: function(data) {
				self.refreshFolderCount(extension);
				if(typeof callback === "function") {
					callback(data);
				}	
			},
			error: function(data) {
				if(typeof callback === "function") {
					callback({status: false});
				}
			}
		});
	},
	moveVoicemailBulk: function (data, extension, callback) {
		var formData = new FormData();
		formData.append('data', JSON.stringify(data));
		self = this;
		$.ajax({
			type: "POST",
			enctype: 'multipart/form-data',
			url: UCP.ajaxUrl + "?module=voicemail&command=moveToFolderBulk",
			data: formData,
			async: false,
			processData: false,
			contentType: false,
			success: function (data) {
				self.refreshFolderCount(extension);
				if (typeof callback === "function") {
					callback(data);
				}
			},
			error: function (data) {
				if (typeof callback === "function") {
					callback({ status: false, error: data });
				}
			}
		});
	},
	forwardVoicemail: function(msgid, extension, recpt, callback) {
		var data = {
			id: msgid,
			to: recpt
		};
		$.post( UCP.ajaxUrl + "?module=voicemail&command=forward&ext="+extension, data, function(data) {
			if(typeof callback === "function") {
				callback(data);
			}
		}).fail(function() {
			if(typeof callback === "function") {
				callback({status: false});
			}
		});
	},
	//Used to delete a voicemail message
	deleteVoicemail: function(msgid, extension, callback) {
		var data = {
			msg: msgid,
			ext: extension
		},
		self = this;
		$.ajax({
			type: "POST",
			url: UCP.ajaxUrl + "?module=voicemail&command=delete",
			data: data,
			async: false,
			success: function(data) {
				self.refreshFolderCount(extension);
				if(typeof callback === "function") {
					callback(data);
				}	
			},
			error: function(data) {
				if(typeof callback === "function") {
					callback({status: false});
				}
			}
		});
	},
	deleteVoicemailBulk: function (data, extension, callback) {
		var formData = new FormData();
		formData.append('data', JSON.stringify(data));
		self = this;
		$.ajax({
			type: "POST",
			enctype: 'multipart/form-data',
			url: UCP.ajaxUrl + "?module=voicemail&command=deleteBulk",
			data: formData,
			async: false,
			processData: false,
			contentType: false,
			success: function (data) {
				self.refreshFolderCount(extension);
				if (typeof callback === "function") {
					callback(data);
				}
			},
			error: function (data) {
				if (typeof callback === "function") {
					callback({ status: false });
				}
			}
		});
	},
	//Toggle the html5 player for greeting
	toggleGreeting: function(type, visible) {
		if (visible === true) {
			$("#" + type + " button.delete").show();
			$("#jp_container_" + type).removeClass("greet-hidden");
			$("#freepbx_player_"+ type).jPlayer( "clearMedia" );
		} else {
			$("#" + type + " button.delete").hide();
			$("#jp_container_" + type).addClass("greet-hidden");
		}
	},
	//Save Voicemail Settings
	saveVMSettings: function(extension) {
		$("#message").fadeOut("slow");
		var data = { ext: extension };
		$("div[data-rawname='voicemail'] .widget-settings-content input[type!='checkbox']").each(function( index ) {
			data[$( this ).attr("name")] = $( this ).val();
		});
		$("div[data-rawname='voicemail'] .widget-settings-content input[type='checkbox']").each(function( index ) {
			data[$( this ).attr("name")] = $( this ).is(":checked");
		});
		$.post( UCP.ajaxUrl + "?module=voicemail&command=savesettings", data, function( data ) {
			if (data.status) {
				$("#message").addClass("alert-success");
				$("#message").text(_("Your settings have been saved"));
				$("#message").fadeIn( "slow", function() {
					setTimeout(function() { $("#message").fadeOut("slow"); }, 2000);
				});
			} else {
				$("#message").addClass("alert-error");
				$("#message").text(data.message);
				return false;
			}
		});
	},
	recordGreeting: function(extension,type) {
		var self = this;
		if (!Modernizr.getusermedia) {
			UCP.showAlert(_("Direct Media Recording is Unsupported in your Broswer!"));
			return false;
		}
		counter = $("#jp_container_" + type + " .jp-current-time");
		title = $("#jp_container_" + type + " .title-text");
		filec = $("#" + type + " .file-controls");
		recc = $("#" + type + " .recording-controls");
		var controls = $("#jp_container_" + type + " .jp-controls");
		controls.toggleClass("recording");
		if (self.recording) {
			clearInterval(self.recordTimer);
			title.text(_("Recorded Message"));
			self.recorder.stop();
			self.recorder.exportWAV(function(blob) {
				self.soundBlobs[type] = blob;
				var url = (window.URL || window.webkitURL).createObjectURL(blob);
				$("#freepbx_player_" + type).jPlayer( "clearMedia" );
				$("#freepbx_player_" + type).jPlayer( "setMedia", {
					wav: url
				});
			});
			self.recording = false;
			recc.show();
			filec.hide();
		} else {
			window.AudioContext = window.AudioContext || window.webkitAudioContext;

			var context = new AudioContext();

			var gUM = Modernizr.prefixed("getUserMedia", navigator);
			gUM({ audio: true }, function(stream) {
				var mediaStreamSource = context.createMediaStreamSource(stream);
				self.recorder = new Recorder(mediaStreamSource,{ workerPath: "assets/js/recorderWorker.js" });
				self.recorder.record();
				self.startTime = new Date();
				self.recordTimer = setInterval(function () {
					var mil = (new Date() - self.startTime);
					var temp = (mil / 1000);
					var min = ("0" + Math.floor((temp %= 3600) / 60)).slice(-2);
					var sec = ("0" + Math.round(temp % 60)).slice(-2);
					counter.text(min + ":" + sec);
				}, 1000);
				title.text(_("Recording..."));
				self.recording = true;
				$("#jp_container_" + type).removeClass("greet-hidden");
				recc.hide();
				filec.show();
			}, function(e) {
				UCP.showAlert(_("Your Browser Blocked The Recording, Please check your settings"));
				self.recording = false;
			});
		}
	},
	saveRecording: function(extension,type) {
		var self = this,
				filec = $("#" + type + " .file-controls"),
				recc = $("#" + type + " .recording-controls");
				title = $("#" + type + " .title-text");
		if (self.recording) {
			UCP.showAlert(_("Stop the Recording First before trying to save"));
			return false;
		}
		if ((typeof(self.soundBlobs[type]) !== "undefined") && self.soundBlobs[type] !== null) {
			$("#" + type + " .filedrop .message").text(_("Uploading..."));
			var data = new FormData();
			data.append("file", self.soundBlobs[type]);
			$.ajax({
				type: "POST",
				url: UCP.ajaxUrl + "?module=voicemail&command=record&type=" + type + "&ext=" + extension,
				xhr: function()
				{
					var xhr = new window.XMLHttpRequest();
					//Upload progress
					xhr.upload.addEventListener("progress", function(evt) {
						if (evt.lengthComputable) {
							var percentComplete = evt.loaded / evt.total,
							progress = Math.round(percentComplete * 100);
							$("#" + type + " .filedrop .pbar").css("width", progress + "%");
						}
					}, false);
					return xhr;
				},
				data: data,
				processData: false,
				contentType: false,
				success: function(data) {
					$("#" + type + " .filedrop .message").text($("#" + type + " .filedrop .message").data("message"));
					$("#" + type + " .filedrop .pbar").css("width", "0%");
					self.soundBlobs[type] = null;
					$("#freepbx_player_" + type).jPlayer("supplied",self.staticsettings.supportedHTML5);
					$("#freepbx_player_" + type).jPlayer( "clearMedia" );
					title.text(title.data("title"));
					filec.show();
					recc.hide();
				},
				error: function() {
					//error
					filec.show();
					recc.hide();
				}
			});
		}
	},
	deleteRecording: function(extension,type) {
		var self = this,
				filec = $("#" + type + " .file-controls"),
				recc = $("#" + type + " .recording-controls");
		if (self.recording) {
			UCP.showAlert(_("Stop the Recording First before trying to delete"));
			return false;
		}
		if ((typeof(self.soundBlobs[type]) !== "undefined") && self.soundBlobs[type] !== null) {
			self.soundBlobs[type] = null;
			$("#freepbx_player_" + type).jPlayer("supplied",self.staticsettings.supportedHTML5);
			$("#freepbx_player_" + type).jPlayer( "clearMedia" );
			title.text(title.data("title"));
			filec.show();
			recc.hide();
			self.toggleGreeting(type, false);
		} else {
			UCP.showAlert(_("There is nothing to delete"));
		}
	},
	//This function is here solely because firefox caches media downloads so we have to force it to not do that
	generateRandom: function() {
		return Math.round(new Date().getTime() / 1000);
	},
	dateFormatter: function(value, row, index) {
		return UCP.dateTimeFormatter(value);
	},
	listenVoicemail: function(msgid, extension, recpt) {
		var data = {
			id: msgid,
			to: recpt
		};
		$.post( UCP.ajaxUrl + "?module=voicemail&command=callme&ext="+extension, data, function( data ) {
			UCP.closeDialog();
		});
	},
	playbackFormatter: function (value, row, index) {
		var settings = UCP.Modules.Voicemail.staticsettings,
				rand = Math.floor(Math.random() * 10000);
		if(settings.showPlayback == "0" || row.duration === 0) {
			return '';
		}
		return '<div id="jquery_jplayer_'+row.msg_id+'-'+rand+'" class="jp-jplayer" data-container="#jp_container_'+row.msg_id+'-'+rand+'" data-id="'+row.msg_id+'"></div><div id="jp_container_'+row.msg_id+'-'+rand+'" data-player="jquery_jplayer_'+row.msg_id+'-'+rand+'" class="jp-audio-freepbx" role="application" aria-label="media player">'+
			'<div class="jp-type-single">'+
				'<div class="jp-gui jp-interface">'+
					'<div class="jp-controls">'+
						'<i class="fa fa-play jp-play"></i>'+
						'<i class="fa fa-undo jp-restart"></i>'+
					'</div>'+
					'<div class="jp-progress">'+
						'<div class="jp-seek-bar progress">'+
							'<div class="jp-current-time" role="timer" aria-label="time">&nbsp;</div>'+
							'<div class="progress-bar progress-bar-striped active" style="width: 100%;"></div>'+
							'<div class="jp-play-bar progress-bar"></div>'+
							'<div class="jp-play-bar">'+
								'<div class="jp-ball"></div>'+
							'</div>'+
							'<div class="jp-duration" role="timer" aria-label="duration">&nbsp;</div>'+
						'</div>'+
					'</div>'+
					'<div class="jp-volume-controls">'+
						'<i class="fa fa-volume-up jp-mute"></i>'+
						'<i class="fa fa-volume-off jp-unmute"></i>'+
					'</div>'+
				'</div>'+
				'<div class="jp-no-solution">'+
					'<span>Update Required</span>'+
					sprintf(_("You are missing support for playback in this browser. To fully support HTML5 browser playback you will need to install programs that can not be distributed with the PBX. If you'd like to install the binaries needed for these conversions click <a href='%s'>here</a>"),"http://wiki.freepbx.org/display/FOP/Installing+Media+Conversion+Libraries")+
				'</div>'+
			'</div>'+
		'</div>';
	},
	durationFormatter: function (value, row, index) {
		return (typeof UCP.durationFormatter === 'function') ? UCP.durationFormatter(value) : sprintf(_("%s seconds"),value);
	},
	controlFormatter: function (value, row, index) {
		var html = '<a class="listen" alt="'+_('Listen on your handset')+'" data-id="'+row.msg_id+'"><i class="fa fa-phone"></i></a>'+
						'<a class="forward" alt="'+_('Forward')+'" data-id="'+row.msg_id+'"><i class="fa fa-share"></i></a>';
		var settings = UCP.Modules.Voicemail.staticsettings;
		if(settings.showDownload == "1") {
			html += '<a class="download" alt="'+_('Download')+'" href="'+ UCP.ajaxUrl +'?module=voicemail&amp;command=download&amp;msgid='+row.msg_id+'&amp;ext='+row.origmailbox+'"><i class="fa fa-cloud-download"></i></a>';
		}

		html += '<a class="delete" alt="'+_('Delete')+'" data-id="'+row.msg_id+'"><i class="fa fa-trash-o"></i></a>';

		return html;
	},
	bindPlayers: function(widget_id) {
		var extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");
		$(".grid-stack-item[data-id="+widget_id+"] .jp-jplayer").each(function() {
			var container = $(this).data("container"),
					player = $(this),
					msg_id = $(this).data("id");
			$(this).jPlayer({
				ready: function() {
					$(container + " .jp-play").click(function() {
						if($(this).parents(".jp-controls").hasClass("recording")) {
							var type = $(this).parents(".jp-audio-freepbx").data("type");
							self.recordGreeting(extension,type);
							return;
						}
						if(!player.data("jPlayer").status.srcSet) {
							$(container).addClass("jp-state-loading");
							$.ajax({
								type: 'POST',
								url: UCP.ajaxUrl,
								data: {module: "voicemail", command: "gethtml5", msg_id: msg_id, ext: extension},
								dataType: 'json',
								timeout: 30000,
								success: function(data) {
									if(data.status) {
										player.on($.jPlayer.event.error, function(event) {
											$(container).removeClass("jp-state-loading");
											console.warn(event);
										});
										player.one($.jPlayer.event.canplay, function(event) {
											$(container).removeClass("jp-state-loading");
											player.jPlayer("play");
										});
										player.jPlayer( "setMedia", data.files);
									} else {
										UCP.showAlert(data.message);
										$(container).removeClass("jp-state-loading");
									}
								}
							});
						}
					});
					var self = this;
					$(container).find(".jp-restart").click(function() {
						if($(self).data("jPlayer").status.paused) {
							$(self).jPlayer("pause",0);
						} else {
							$(self).jPlayer("play",0);
						}
					});
				},
				timeupdate: function(event) {
					$(container).find(".jp-ball").css("left",event.jPlayer.status.currentPercentAbsolute + "%");
				},
				ended: function(event) {
					$(container).find(".jp-ball").css("left","0%");
				},
				swfPath: "/js",
				supplied: UCP.Modules.Voicemail.staticsettings.supportedHTML5,
				cssSelectorAncestor: container,
				wmode: "window",
				useStateClassSkin: true,
				remainingDuration: true,
				toggleDuration: true
			});
			$(this).on($.jPlayer.event.play, function(event) {
				$(this).jPlayer("pauseOthers");
			});
		});

		var acontainer = null;
		$('.grid-stack-item[data-rawname=voicemail] .jp-play-bar').mousedown(function (e) {
			acontainer = $(this).parents(".jp-audio-freepbx");
			updatebar(e.pageX);
		});
		$(document).mouseup(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
				acontainer = null;
			}
		});
		$(document).mousemove(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
			}
		});

		//update Progress Bar control
		var updatebar = function (x) {
			var player = $("#" + acontainer.data("player")),
					progress = acontainer.find('.jp-progress'),
					maxduration = player.data("jPlayer").status.duration,
					position = x - progress.offset().left,
					percentage = 100 * position / progress.width();

			//Check within range
			if (percentage > 100) {
				percentage = 100;
			}
			if (percentage < 0) {
				percentage = 0;
			}

			player.jPlayer("playHead", percentage);

			//Update progress bar and video currenttime
			acontainer.find('.jp-ball').css('left', percentage+'%');
			acontainer.find('.jp-play-bar').css('width', percentage + '%');
			player.jPlayer.currentTime = maxduration * percentage / 100;
		};
	},
	bindGreetingPlayers: function(extension) {
		var settings = UCP.Modules.Voicemail.staticsettings,
				supportedHTML5 = settings.supportedHTML5,
				self = this;

		if(Modernizr.getusermedia) {
			supportedHTML5 = supportedHTML5.split(",");
			if(supportedHTML5.indexOf("wav") === -1) {
				supportedHTML5.push("wav");
			}
			supportedHTML5 = supportedHTML5.join(",");
		}

		$("#widget_settings .jp-jplayer, .grid-stack-item[data-rawname=voicemail] .jp-jplayer").each(function() {
			var container = $(this).data("container"),
					player = $(this),
					msg_id = $(this).data("id");
			$(this).jPlayer({
				ready: function() {
					$(container + " .jp-play").click(function() {
						if($(this).parents(".jp-controls").hasClass("recording")) {
							var type = $(this).parents(".jp-audio-freepbx").data("type");
							self.recordGreeting(extension,type);
							return;
						}
						if(!player.data("jPlayer").status.srcSet) {
							$(container).addClass("jp-state-loading");
							$.ajax({
								type: 'POST',
								url: UCP.ajaxUrl,
								data: {module: "voicemail", command: "gethtml5", msg_id: msg_id, ext: extension},
								dataType: 'json',
								timeout: 30000,
								success: function(data) {
									if(data.status) {
										player.on($.jPlayer.event.error, function(event) {
											$(container).removeClass("jp-state-loading");
											console.warn(event);
										});
										player.one($.jPlayer.event.canplay, function(event) {
											$(container).removeClass("jp-state-loading");
											player.jPlayer("play");
										});
										player.jPlayer( "setMedia", data.files);
									} else {
										UCP.showAlert(data.message);
										$(container).removeClass("jp-state-loading");
									}
								}
							});
						}
					});
					var self = this;
					$(container).find(".jp-restart").click(function() {
						if($(self).data("jPlayer").status.paused) {
							$(self).jPlayer("pause",0);
						} else {
							$(self).jPlayer("play",0);
						}
					});
				},
				timeupdate: function(event) {
					$(container).find(".jp-ball").css("left",event.jPlayer.status.currentPercentAbsolute + "%");
				},
				ended: function(event) {
					$(container).find(".jp-ball").css("left","0%");
				},
				swfPath: "/js",
				supplied: supportedHTML5,
				cssSelectorAncestor: container,
				wmode: "window",
				useStateClassSkin: true,
				remainingDuration: true,
				toggleDuration: true
			});
			$(this).on($.jPlayer.event.play, function(event) {
				$(this).jPlayer("pauseOthers");
			});
		});

		var acontainer = null;
		$('#widget_settings .jp-play-bar, .grid-stack-item[data-rawname=voicemail] .jp-play-bar').mousedown(function (e) {
			acontainer = $(this).parents(".jp-audio-freepbx");
			updatebar(e.pageX);
		});
		$(document).mouseup(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
				acontainer = null;
			}
		});
		$(document).mousemove(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
			}
		});

		//update Progress Bar control
		var updatebar = function (x) {
			var player = $("#" + acontainer.data("player")),
					progress = acontainer.find('.jp-progress'),
					maxduration = player.data("jPlayer").status.duration,
					position = x - progress.offset().left,
					percentage = 100 * position / progress.width();

			//Check within range
			if (percentage > 100) {
				percentage = 100;
			}
			if (percentage < 0) {
				percentage = 0;
			}

			player.jPlayer("playHead", percentage);

			//Update progress bar and video currenttime
			acontainer.find('.jp-ball').css('left', percentage+'%');
			acontainer.find('.jp-play-bar').css('width', percentage + '%');
			player.jPlayer.currentTime = maxduration * percentage / 100;
		};
	}
});
