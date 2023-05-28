var CdrC = UCPMC.extend({
	init: function() {
		this.playing = null;
	},
	resize: function(widget_id) {
		$(".grid-stack-item[data-id='"+widget_id+"'] .cdr-grid").bootstrapTable('resetView',{height: $(".grid-stack-item[data-id='"+widget_id+"'] .widget-content").height()-1});
	},
	poll: function(data, url) {

	},
	displayWidget: function(widget_id, dashboard_id) {
		var self = this,
				extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");

		$(".grid-stack-item[data-id='"+widget_id+"'] .cdr-grid").one("post-body.bs.table", function() {
			setTimeout(function() {
				self.resize(widget_id);
			},250);
		});

		$('.grid-stack-item[data-id='+widget_id+'] .cdr-grid').on("post-body.bs.table", function () {
			self.bindPlayers(widget_id);
			$(".cdr-grid .clickable").click(function(e) {
				var text = $(this).text();
				if (UCP.validMethod("Contactmanager", "showActionDialog")) {
					UCP.Modules.Contactmanager.showActionDialog("number", text, "phone");
				}
			});
		});
	},
	formatDescription: function (value, row, index) {
		var icons = '';
		if(typeof row.icons !== "undefined") {
			$.each(row.icons, function(i, v) {
				icons += '<i class="fa '+v+'"></i> ';
			});
		}
		return icons + " " + value;
	},
	formatActions: function (value, row, index) {
		var settings = UCP.Modules.Cdr.staticsettings;
		if(row.recordingfile === '' || settings.showDownload === "0") {
			return '';
		}
		var link = '<a class="download" alt="'+_("Download")+'" href="'+UCP.ajaxUrl+'?module=cdr&amp;command=download&amp;msgid='+row.uniqueid+'&amp;type=download&amp;ext='+row.requestingExtension+'"><i class="fa fa-cloud-download"></i></a>';
		return link;
	},
	formatPlayback: function (value, row, index) {
		var settings = UCP.Modules.Cdr.staticsettings,
				rand = Math.floor(Math.random() * 10000);
		if(row.recordingfile.length === 0 || settings.showPlayback === "0") {
			return '';
		}
		return '<div id="jquery_jplayer_'+row.niceUniqueid+'-'+rand+'" class="jp-jplayer" data-container="#jp_container_'+row.niceUniqueid+'-'+rand+'" data-id="'+row.uniqueid+'"></div><div id="jp_container_'+row.niceUniqueid+'-'+rand+'" data-player="jquery_jplayer_'+row.niceUniqueid+'-'+rand+'" class="jp-audio-freepbx" role="application" aria-label="media player">'+
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
	formatDuration: function (value, row, index) {
		return row.niceDuration;
	},
	formatDate: function(value, row, index) {
		return UCP.dateTimeFormatter(value);
	},
	bindPlayers: function(widget_id) {
		var extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");
		$(".grid-stack-item[data-id="+widget_id+"] .jp-jplayer").each(function() {
			var container = $(this).data("container"),
					player = $(this),
					id = $(this).data("id");
			$(this).jPlayer({
				ready: function() {
					$(container + " .jp-play").click(function() {
						if($(this).parents(".jp-controls").hasClass("recording")) {
							var type = $(this).parents(".jp-audio-freepbx").data("type");
							$this.recordGreeting(type);
							return;
						}
						if(!player.data("jPlayer").status.srcSet) {
							$(container).addClass("jp-state-loading");
							$.ajax({
								type: 'POST',
								url: "index.php?quietmode=1",
								data: {module: "cdr", command: "gethtml5", id: id, ext: extension},
								dataType: 'json',
								timeout: 30000,
								success: function(data) {
									if(data.status) {
										player.on($.jPlayer.event.error, function(event) {
											$(container).removeClass("jp-state-loading");
											console.log(event);
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
				supplied: UCP.Modules.Cdr.staticsettings.supportedHTML5,
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
});
