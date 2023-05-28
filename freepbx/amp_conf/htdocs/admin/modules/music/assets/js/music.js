$("form").submit(function() {
	if($("#category").length) {
		var name = $("#category").val();
		if (!isAlphanumeric(name)) {
			return warnInvalid($("#category"), _('Please enter a valid Category Name'));
		}

		if (name == "default" || name == "none" || name == ".nomusic_reserved") {
			return warnInvalid($("#category"), _('Categories: "none" and "default" are reserved names. Please enter a different name'));
		}
	}
});
$("#moheditform").submit(function(e) {
	if($("#type").val() == "custom") {
		if (isEmpty($("#application").val())) {
			return warnInvalid($("#application"), _('Please enter a valid application command and arguments'));
		}
	}
	e.preventDefault();
	var data = {
		module: "music",
		command: "save",
		id: $("input[name=id]").val()
	};
	data.type = $("#type").val();
	data.format = $("#format").val();
	data.application = $("#application").val();
	data.erand = $("input[name=erand]:checked").val();
	data.codecs = [];
	if($("input[type=checkbox].codec").length > 0) {
		$("input[type=checkbox].codec:checked").each(function() {
			data.codecs.push($(this).val());
		});
	} else {
		$("input[type=hidden].codec").each(function() {
			data.codecs.push($(this).val());
		});
	}


	if(display_mode == "advanced" && $("#type").val() != "custom" && !confirm(_("If you are doing media conversions this can take a very long time, is that ok?"))) {
		return false;
	}

	$("#action-buttons input").prop("disabled",true);
	$.ajax({
		type: 'POST',
		url: "ajax.php",
		data: data,
		dataType: 'json',
		timeout: 240000
	}).done(function(data) {
		if(data.status) {
			window.location = "?display=music";
		} else {
			alert(data.message);
			console.log(data.errors);
		}
	}).fail(function() {
		alert(_("An Error occurred trying to submit this document"));
	}).always(function() {
		$("#action-buttons input").prop("disabled", false);
	});
});

$(document).on("keyup paste", ".name-check", function(e) {
	if (e.keyCode == 37 || e.keyCode == 38 || e.keyCode == 39 || e.keyCode == 40) {
		return;
	}
	var i = $(this).val().replace(/\s|&|<|>|\.|`|'|\*|\?|"|\/|\\|\|/g, '');
	$(this).val(i);
});

$("#type").change(function() {
	if($(this).val() == "files") {
		$("#application-container").addClass("hidden");
		$("#files-container").removeClass("hidden");
	} else {
		$("#application-container").removeClass("hidden");
		$("#files-container").addClass("hidden");
	}
});

function formatFormatter(val,row){
	return row.formats.join();
}
function linkFormat(val,row){
	var type = 'files';
	if(row.type == 'custom'){
		type = 'custom';
	}
	var html = '<a href="?display=music&id='+row.id+'&action=edit"><i class="fa fa-pencil"></i></a>';
	if(row.category !== 'default'){
		html += '&nbsp;<a href="?display=music&id='+row.id+'&action=delete"" class="delAction"><i class="fa fa-trash"></i></a>';
	}
	return html;
}

function playFormatter(val,row){
	return '<div id="jquery_jplayer_'+row.id+'" class="jp-jplayer" data-filename="'+row.filename+'" data-categoryid="'+row.categoryid+'" data-container="#jp_container_'+row.id+'"></div><div id="jp_container_'+row.id+'" data-player="jquery_jplayer_'+row.id+'" class="jp-audio-freepbx" role="application" aria-label="media player">'+
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
}

function musicFormat(value,row){
	html = '<a data-name="'+row.name+'" data-id="'+row.id+'" data-categoryid="'+row.categoryid+'" class="clickable delMusic"><i class="fa fa-trash"></i></a>';
	return html;
}

$('#musicgrid').on("post-body.bs.table", function () {
	bindPlayers();
});

//Make sure at least one codec is selected
$(".codec").change(function() {
	if(!$(".codec").is(":checked")) {
		alert(_("At least one codec must be checked"));
		$(this).prop("checked", true);
	}
});

/**
 * Drag/Drop/Upload Files
 */
$('#dropzone').on('drop dragover', function (e) {
	e.preventDefault();
});
$('#dropzone').on('dragleave drop', function (e) {
	$(this).removeClass("activate");
});
$('#dropzone').on('dragover', function (e) {
	$(this).addClass("activate");
});
$('#fileupload').fileupload({
	dataType: 'json',
	dropZone: $("#dropzone"),
	add: function (e, data) {
		//TODO: Need to check all supported formats
		var sup = "\.("+supportedRegExp+")$",
				patt = new RegExp(sup),
				submit = true;
		$.each(data.files, function(k, v) {
			if(!patt.test(v.name.toLowerCase())) {
				submit = false;
				alert(_("Unsupported file type"));
				return false;
			}
			if(v.size > max_size) {
				submit = false;
				alert(sprintf(_("File size is too large. Max size is %s bytes"),max_size));
				return false;
			}
			var s = v.name.replace(/\.[^/.]+$/, "").replace(/\s+|'+|\"+|\?+|\*+/g, '-').toLowerCase();
			if(mohConflict(s)) {
				alert(sprintf(_("File '%s' will overwrite a file (%s) that already exists in this category"),v.name, s));
				submit = false;
				return false;
			}
		});
		if(submit) {
			$("#upload-progress .progress-bar").addClass("progress-bar-striped active");
			data.submit();
		}
	},
	drop: function () {
		$("#upload-progress .progress-bar").css("width", "0%");
	},
	dragover: function (e, data) {
	},
	change: function (e, data) {
	},
	done: function (e, data) {
		$("#upload-progress .progress-bar").removeClass("progress-bar-striped active");
		$("#upload-progress .progress-bar").css("width", "0%");
		if(data.result.status) {
			$('#musicgrid').bootstrapTable('refresh',{
				silent: true
			});
			files.push(data.result.name.toLowerCase());
		} else {
			alert(data.result.message);
		}
	},
	progressall: function (e, data) {
		var progress = parseInt(data.loaded / data.total * 100, 10);
		$("#upload-progress .progress-bar").css("width", progress+"%");
	},
	fail: function (e, data) {
	},
	always: function (e, data) {
	}
});

function mohConflict(s) {
	return files.indexOf(s) > -1;
}

function bindPlayers() {
	$(".jp-jplayer").each(function() {
		var container = $(this).data("container"),
				player = $(this),
				file = player.data("filename"),
				categoryid = player.data("categoryid");
		$(this).jPlayer({
			ready: function() {
				$(container + " .jp-play").click(function() {
					if(!player.data("jPlayer").status.srcSet) {
						$(container).addClass("jp-state-loading");
						$.ajax({
							type: 'POST',
							url: "ajax.php",
							data: {module: "music", command: "gethtml5", file: file, categoryid: categoryid},
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
									alert(data.message);
									$(container).removeClass("jp-state-loading");
								}
							}
						});
					}
				});
				var $this = this;
				$(container).find(".jp-restart").click(function() {
					if($($this).data("jPlayer").status.paused) {
						$($this).jPlayer("pause",0);
					} else {
						$($this).jPlayer("play",0);
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
			autoBlur: false,
			keyEnabled: true,
			remainingDuration: true,
			toggleDuration: true
		});
		$(this).on($.jPlayer.event.play, function(event) {
			$(this).jPlayer("pauseOthers");
		});
	});

	var acontainer = null;
	$('.jp-play-bar').mousedown(function (e) {
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

$(document).on("click", ".delMusic", function() {
	if(!confirm(_("Are you sure you want to delete this item?"))) {
		return;
	}
	var id = $(this).data("id"),
			name = $(this).data("name");
	id = parseInt(id);
	$.post( "ajax.php", {module: "music", command: "deletemusic", name: name, categoryid: $(this).data("categoryid")}, function( data ) {
		if(data.status) {
			$('#musicgrid').bootstrapTable('remove', {field: 'id', values: [id]});
			var index = files.indexOf();
			files.splice(name, 1);
		} else {
			alert(data.message);
		}
	});
});

$("table").on("post-body.bs.table", function () {
	positionActionBar();
});
