var playing = null;
function cdr_play(rowNum, uid) {
	var playerId = (rowNum - 1);
	if (playing !== null && playing != playerId) {
		$("#jquery_jplayer_" + playing).jPlayer("stop", 0);
		playing = playerId;
	} else if (playing === null) {
		playing = playerId;
	}
	$("#jquery_jplayer_" + playerId).jPlayer({
		ready: function() {
			var $this = this;
			$("#jp_container_" + playerId + " .jp-restart").click(function() {
				if($($this).data("jPlayer").status.paused) {
					$($this).jPlayer("pause",0);
				} else {
					$($this).jPlayer("play",0);
				}
			});
		},
		timeupdate: function(event) {
			$("#jp_container_" + playerId).find(".jp-ball").css("left",event.jPlayer.status.currentPercentAbsolute + "%");
		},
		ended: function(event) {
			$("#jp_container_" + playerId).find(".jp-ball").css("left","0%");
		},
		swfPath: "/js",
		supplied: supportedHTML5,
		cssSelectorAncestor: "#jp_container_" + playerId,
		wmode: "window",
		useStateClassSkin: true,
		autoBlur: false,
		keyEnabled: true,
		remainingDuration: true,
		toggleDuration: true
	});
	$(".playback").hide("fast");
	$("#playback-" + playerId).slideDown("fast", function(event) {
		$("#jp_container_" + playerId).addClass("jp-state-loading");
		$.ajax({
			type: 'POST',
			url: "ajax.php",
			data: {module: "cdr", command: "gethtml5", uid: uid},
			dataType: 'json',
			timeout: 30000,
			success: function(data) {
				var player = $("#jquery_jplayer_" + playerId);
				if(data.status) {
					player.on($.jPlayer.event.error, function(event) {
						$("#jp_container_" + playerId).removeClass("jp-state-loading");
						console.log(event);
					});
					player.one($.jPlayer.event.canplay, function(event) {
						player.jPlayer("play");
						$("#jp_container_" + playerId).removeClass("jp-state-loading");
					});
					player.on($.jPlayer.event.play, function(event) {
						player.jPlayer("pauseOthers", 0);
					});
					player.jPlayer( "setMedia", data.files);
				}
			}
		});
	});
	$("#jquery_jplayer_" + playerId).on($.jPlayer.event.play, function(event) {
		$(this).jPlayer("pauseOthers");
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
