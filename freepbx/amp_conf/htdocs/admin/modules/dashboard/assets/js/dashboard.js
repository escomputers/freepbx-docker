window.observers = {};

var DashboardC = Class.extend({
	init: function(){
		// initialize
		this.initalized = false;
		this.currentval = 0;

		// Make sure we're at the top of the page on a <reload> button push.
		$(window).scrollTop(0);

		// If modulename isn't already defined...
		if (typeof(window.modulename) == "undefined") {
			window.modulename = "dashboard";
		}
		//TODO: this should go away eventually
		window.ajaxurl = "ajax.php";

		// Only mark the first item of the first page as first.
		this.firstpage = false;
		// Only preload the first page.
		this.preloaddone = false;
		// Oh, and this is the page we're loading
		this.currentpage = "page_Main";
		$.ajax({
			url: "ajax.php",
			data: { command: "gethooks", module: window.modulename},
			success: function(data) { Dashboard.parseInfo(data); },
		},{longpoll: true});
	},
	parseInfo: function(pages) {
		// Count how many things we have to load on the Main Page.
		this.items = Object.keys(pages[0].entries).length;
		this.multiplier = Math.ceil(100/Dashboard.items);
		// Now we know. So. Add them, now.
		$.each(pages, function(i, v) { Dashboard.addPage(v); });
		this.loadPage("page_Main", true);
		$(".reload").on('click', function(x) { Dashboard.reloadClicked(this); });
	},
	addPage: function(pageArr) {
		var pageid = "page_"+pageArr.pagename;
		var h = '<div class="page" id="'+ pageid +'"></div>';
		$("#mainpages").append(h);

		// Add Page to sidebar
		var sb = $("#sidebar");
		sb.append('<h4>'+pageArr.pagename+'</h4>'); // This is a bit derpy, but seems to work.
		var p = $("#"+pageid);
		$.each(pageArr.entries, function(i,val) {
			var s = "";
			var id = pageid+"_"+val.rawname+"_"+val.section;
			s += "<div class='item' style='width:"+val.width+"'><div id='"+id+"' class='displaybox' data-rawname='"+val.rawname+"' data-section='"+val.section+"'";
			if (typeof(val.module) != "undefined") {
				s += " data-module='"+val.module+"'";
			}
			s += "><div class='shadow'></div><div class='title-bar'>"+val.title+"";
			s += "<span class='reload text-center' data-pagename='"+pageid+"' data-rawname='"+val.rawname+"' data-section='"+val.section+"'>";
			s += "<i class='fa fa-refresh'></i></span></div><div class='content'></div></div></div>";
			p.append(s);
		});
		if ($('.page').length > 0) {
			$('.page').packery({
				itemSelector: '.item',
				gutter: 5,
				columnWidth: 10,
				rowHeight: 10,
			});
			$('.page').packery( 'on', 'dragItemPositioned', Dashboard.orderItems );
			$.each($('.page').packery('getItemElements'),function(i,v) {
				var draggie = new Draggabilly( v, {handle: '.title-bar'} );
				draggie.on( 'dragStart', function(instance, event, pointer) {
					$(instance.element).css('z-index','5000');
				});
				draggie.on( 'dragEnd', function(instance, event, pointer) {
					$(instance.element).css('z-index', '');
				});
				$('.page').packery( 'bindDraggabillyEvents', draggie );
			});
		} else {
			setTimeout(function(){
				$('.page').packery({
					itemSelector: '.item',
					gutter: 5,
					columnWidth: 10,
					rowHeight: 10,
				});
				$('.page').packery( 'on', 'dragItemPositioned', Dashboard.orderItems );
				$.each($('.page').packery('getItemElements'),function(i,v) {
					var draggie = new Draggabilly( v, {handle: '.title-bar'} );
					draggie.on( 'dragStart', function(instance, event, pointer) {
						$(instance.element).css('z-index','5000');
					});
					draggie.on( 'dragEnd', function(instance, event, pointer) {
						$(instance.element).css('z-index', '');
					});
					$('.page').packery( 'bindDraggabillyEvents', draggie );
				});
			}, 500);
		}
	},
	orderItems: function(pckryInstance, laidOutItems) {
		// items are in order within the layout
		var itemElems = $('.page').packery('getItemElements');
		// for this demo, let's set text based on their order
		var order = {};
		for ( var i=0, len = itemElems.length; i < len; i++ ) {
			var elem = itemElems[i];
			var section = $(elem).find('.displaybox').data('section');
			order[section] = (i + 1);
		}
		$.post( "ajax.php", {command: 'saveorder', module: 'dashboard', order: order},function( data ) {

		});
	},
	loadPage: function(pagename) {
		this.currentpage = pagename;
		$.each($("#"+pagename).find(".displaybox"), function() { Dashboard.updateBox(pagename, $(this)); });
	},
	updateBox: function(pagename, divobj) {
		var mod = window.modulename;
		if (typeof(divobj.data('module')) != "undefined") {
			mod = divobj.data('module');
		}
		$.ajax({
			url: "ajax.php",
			data: { command: "getcontent", module: mod, rawname:  divobj.data('rawname'), section:  divobj.data('section')},
			success: function(data) {
				if(data.status) {
					Dashboard.loadIntoBox(divobj, data.content);
				} else {
					Dashboard.loadIntoBox(divobj, data.message);
				}
			},
			error: function() {
				Dashboard.loadIntoBox(divobj, "There was an error asking for " + divobj.data('rawname') + ". Check the error logs for more information.");
				divobj.find('.fa-spin').removeClass('fa-spin');
			}
		}).done(function() {
			divobj.find('.fa-spin').not('.dontremovespin').removeClass('fa-spin');
		});
	},
	loadIntoBox: function(divobj, html) {
		// This removes any hooks from charts that are being replaced.
		// Doesn't appear to break anything else.
		try {
			$("#"+divobj.data('rawname')).chart('clear');
		} catch(err) {}
		divobj.children(".content").html(html);
		$('.page').packery();
		this.updateProgressBar();
		divobj.find('.shadow').fadeOut('fast');
	},
	updateProgressBar: function() {
		var messages = ["Loading"];
		if (this.finishedloading) {
			return;
		}

		var cur = this.currentval + this.multiplier;

		if (cur >= 100) {
			cur = 100;
			this.finishedloading = true;
			$('.page').packery();
			$("#welcomepage").fadeOut();
			return;
		}
		this.currentval = cur;

		$(".progress-bar").width(this.currentval + "%");
		var message = messages[Math.floor(Math.random()*messages.length)];
		$(".progress-bar>span").text(message + " " + this.currentval + "%");
	},
	reloadClicked: function(x) {
		var myobj = $(x);
		var pagename = myobj.data('pagename');
		var divobj = $("#"+pagename+"_"+myobj.data('rawname')+"_"+myobj.data('section'));
		myobj.children(".fa-refresh").addClass("fa-spin");
		divobj.find('.shadow').fadeIn('fast');
		this.updateBox(myobj.data('pagename'), divobj);
	}
});

var Dashboard = null;
$(function() {
	Dashboard = new DashboardC();
	$( document ).ajaxComplete(function() {
		$('.page').packery();
	});
});
