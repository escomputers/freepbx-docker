/**
 * UI Read Log
 * 
 * @author Javier Pastor (VSC55)
 * @license GPLv3
 */

var global_module_logfiles_timeout_resume   = null;
var global_module_logfiles_refresh_interval = null;
var global_module_logfiles_id_tail          = null;
var global_module_logfiles_i18n             = i18nGet('logs');

$(document).ready(function()
{
	$(window).resize(function() { log_view_resize(); });
	
	// Detect when the global banner closes and run resize.
	// Mitigation of "offset().top" change detection problem.
	$("#page_body > .global-message-banner").on("remove", function () { log_view_resize(); });

	var toolbar 		= $('#logfiles_toolbar');
	var log_area		= $("#log_view");
	var btn_reload_list = $('.btn-reload-list', toolbar);
	var btn_reload_file	= $('.btn-reload-file', toolbar);
	var btn_highlight	= $('.btn-highlight-apply', toolbar);
	
	var select_files	= $("select[name='filename_log']", toolbar);
	

	select_files.on('change', function() 	{ if (select_files.val()) { btn_reload_file.click(); } });

	btn_reload_list.on("click", function(e) { e.preventDefault(); log_update_list_files(toolbar); });
	btn_reload_file.on("click", function(e) { e.preventDefault(); log_read_file(toolbar, log_area, false); });

	$('.btn-filter-apply', 		 toolbar).on("click", function(e) { e.preventDefault(); btn_reload_file.click(); });
	$('.btn-file-export', 		 toolbar).on("click", function(e) { e.preventDefault(); export_log(toolbar); });
	$('.btn-file-delte', 		 toolbar).on("click", function(e) { e.preventDefault(); delete_log(toolbar); });

	$("input[name='fileter_log']", toolbar).on("keyup", delay(500, function ()
	{
		if (! select_files.is(':disabled') )
		{
			btn_reload_file.click();
		} 
	}));

	$("input[name='lines_log']", toolbar).bind("input propertychange", delay(500, function ()
	{
		if (! select_files.is(':disabled') )
		{
			btn_reload_file.click();
		}
	}));

	$('.btn-filter-clean', toolbar).on("click", function(e)
	{
		e.preventDefault();
		var input = toolbar.find("input[name='fileter_log']");
		if ( input.val() )
		{
			input.val("");
			btn_reload_file.click();
		}
	});

	$('.refresh-interval-item', toolbar).on("click", function(e)
	{
		e.preventDefault();
		var t = e.target || e.srcElement;
		var new_val = $(t).closest("li").find("input").val();

		var process = new read_file_process(toolbar, log_area, true);
		process.setTimerInterval(new_val);
	});

	$("input[name='highlight_log']", toolbar).keyup(delay(500, function () { btn_highlight.click(); }));

	$('.btn-highlight-clean', toolbar).on("click", function(e)
	{
		e.preventDefault();
		toolbar.find("input[name='highlight_log']").val("");
		btn_highlight.click();
	});

	btn_highlight.on("click", function(e)
	{
		e.preventDefault();
		var process = new read_file_process(toolbar, log_area, true, false);
		process.applyHighlight();
	});

	$("input:radio[name='show_only_rows_highlight']", toolbar).on("click", function(e)
	{
		btn_highlight.click();
	});


	$('.btn-fullscreen', toolbar).on("click", function(e)
	{
		e.preventDefault();
		var process = new read_file_process(toolbar, log_area, true, false);
		process.fullScreen(true);
	});

	$(document).keydown(function(event)
	{
		var keycode = (event.keyCode ? event.keyCode : event.which);
		// console.log(keycode);
		if(keycode == '27')
		{
			var process = new read_file_process(toolbar, log_area, true, false);
			process.fullScreen(false);
		}
	});
	


	$(document).on("click", ".copy-line", function(e)
	{
		t = e.target || e.srcElement;
		var txt = $(t).closest('.line_log').find('.line_log_data').text();
		copyToClipboard(txt);
		fpbxToast( i18n_mod('COPY_CLIPBOARD_OK'), '', 'success');
	});
	
	$("input:radio[name='show_col_num_line']", toolbar).on("click", function(e)
	{
		// Not run "preventDefault" because it stops the selection of the radio
		var process = new read_file_process(toolbar, log_area, true, false);
		process.updateShowColNumLine();
	});
	
	$("input:radio[name='show_line_spacing']", toolbar).on("click", function(e)
	{
		// Not run "preventDefault" because it stops the selection of the radio
		var process = new read_file_process(toolbar, log_area, true, false);
		process.updateLineSeparation();
	});

	$("input[name='size_buffer']", toolbar).bind("input propertychange", delay(500, function ()
	{
		var process = new read_file_process(toolbar, log_area, true, false);
		process.controlBuffer();
	}));


	//run only once at startup
	log_view_resize();
	$("input:radio[name='show_only_rows_highlight']", toolbar).val(["on"]);
	$("input:radio[name='show_col_num_line']", 		  toolbar).val(["on"]);
	$("input:radio[name='show_line_spacing']", 		  toolbar).val(["on"]);
	$("input:radio[name='auto_scroll']", 	   		  toolbar).val(["on"]);
	$("input[name='size_buffer']", 	   		   		  toolbar).val("0");
	$("input[name='lines_log']", 	   		   		  toolbar).val("500");
	$('.btn-reload-list').click();
});



//https://hackernoon.com/copying-text-to-clipboard-with-javascript-df4d4988697f
function copyToClipboard (txt)
{
	var el = document.createElement('textarea');
	el.value = txt;
	el.setAttribute('readonly', '');
	el.style.position = 'absolute';
	el.style.left = '-9999px';
	document.body.appendChild(el);
	
	el.select();
	document.execCommand('copy');
	document.body.removeChild(el);
}



function delay(ms, fn) 
{
	let timer = 0
	return function(...args) 
	{
		clearTimeout(timer)
		timer = setTimeout(fn.bind(this, ...args), ms || 0)
	}
}



function log_view_resize()
{
	//TODO: It would be good if this works if the offset.top of #logfiles_header changed, but jquery does not
	//		detect the offset change, you would have to create a timer that would monitor the offset every x time.

	var new_size = ($(window).height() - $('#footer').height() - $('div.logfiles_header').height() - $('div.logfiles_header').offset().top);
	if (new_size < 200) {
		new_size = 200;
	}
	$('#log_view.log_area').css({
		'min-height': new_size,
		'max-height': new_size
	});
}



function export_log(toolbar)
{
	var process   = new list_files_process(toolbar);
	var name_file = process.getValue();
	if (name_file)
	{
		$url = window.FreePBX.ajaxurl + "?module=logfiles&command=log_file_export&log_file="+name_file;
		window.location.assign($url);
	}
}

function delete_log(toolbar)
{
	var process   = new list_files_process(toolbar);
	var name_file = process.getValue();
	if (name_file) 
	{
		fpbxConfirm(
			sprintf( i18n_mod("QUESTION_CONFIRM_DELETE_FILE"), name_file ),
			i18n_mod("YES"), i18n_mod("NO"),
			function()
			{
				var post_data = {
					module: 'logfiles',
					command: 'log_file_destory',
					log_file: name_file
				};
				process.begin();
				$.post(window.FreePBX.ajaxurl, post_data, function(data)
				{
					process.setStatusAjax("AJAX_SEND_QUERY");
				})
				.done(function(data) 
				{
					fpbxToast(data.message, '', (data.status ? 'success' : 'error') );
					
					if (data.status) { process.setStatusAjax("DONE"); } 
					else 			 { process.setStatusAjax("STATUS_FAILED"); }
				})
				.fail(function(err) { process.setStatusAjax("AJAX_ERROR"); })
				.always(function()
				{
					process.finish();
					if ( process.getStatusAjax() == "DONE" )
					{
						//sleep 5ms before reloading file listing
						setTimeout( function() { process.getToolBar().find('.btn-reload-list').click(); }, 500);
					}
				});
			}
		);
	}
}

function log_update_list_files(toolbar)
{
	var process = new list_files_process(toolbar);
	var post_data = {
		module: 'logfiles',
		command: 'log_files'
	};
	process.begin();
	$.post(window.FreePBX.ajaxurl, post_data, function(data) 
	{
		process.setStatusAjax("AJAX_SEND_QUERY");
	})
	.done(function(data)
	{
		if (data.status)
		{
			process.addOptions(data.data, "full", true, true);
			process.setStatusAjax("DONE");
		}
		else
		{
			process.setStatusAjax("STATUS_FAILED");
			fpbxToast(data.message, '', 'error');
		}
	})
	.fail(function(err) { process.setStatusAjax("AJAX_ERROR"); })
	.always(function()  { process.finish(); });
}

function list_files_process(toolbar)
{
	this.toolbar	 = toolbar;

	this.status_ajax = null;
	this.status 	 = null;
	
	this.name_select_filename = 'filename_log';
	this.name_count_files 	  = '.count_files_in_list';
	this.name_btn_reload_list = '.btn-reload-list';
}

list_files_process.prototype = {
	begin: function() 
	{
		if (this.status === true) { return; }
		this.disabledControls(true);
		this.status = true;
	},
	finish: function() 
	{
		if (this.status === false) { return; }

		if (this.getCountOptions() > 0)
		{
			this.disabledControls(false);
		}
		else
		{
			this.getToolBar().find(this.name_btn_reload_list).removeClass('disabled');
		}
		this.status = false;
	},
	setStatusAjax: function(new_status)
	{
		this.status_ajax = new_status;
	},
	getStatusAjax: function()
	{
		return this.status_ajax;
	},
	getToolBar: function()
	{
		return this.toolbar;
	},
	findByName: function(name)
	{
		return this.getToolBar().find("[name='" + name + "']");
	},
	getSelect: function() 
	{
		return this.findByName(this.name_select_filename);
	},
	getValue: function()
	{
		return this.getSelect().val();
	},
	setValue: function(new_value, onChange = false)
	{	
		// $("option[value='"+ oldval +"']", select).attr("selected", true);
		this.getSelect().val(new_value);
		if (onChange)
		{
			this.getSelect().change();
		}
	},
	cleanList: function()
	{
		this.getSelect().empty();
	},
	isExistOption: function(option) 
	{
		if ( $("option[value='"+ option+"']", this.getSelect() ).length != 0)
		{
			return true;
		}
		return false;
	},
	addOptions: function(data, default_val = "", clean = true, preserve = true, onChangeForce = false)
	{
		var old_value = this.getValue();
		var new_value = default_val;
		var onChange  = true;

		this.setCount(i18n_mod("?"));

		if (clean)
		{
			this.cleanList();
		}
		select = this.getSelect();
		data.forEach(function(file)
		{
			select.append(new Option(file , file));
		});
		if (preserve)
		{	
			if (old_value && this.isExistOption(old_value)) 
			{
				new_value = old_value;
			}
		}

		if (new_value) 				{ this.setValue(new_value); }
		
		if (old_value == new_value) { onChange = false; }
		if (onChangeForce) 			{ onChange = true;  }
		if (onChange) 				{ this.getSelect().change(); }

		this.setCount(this.getCountOptions());
	},
	getCountOptions: function() 
	{
		return $("option", this.getSelect() ).length;
	},
	setCount: function(count) 
	{
		this.getToolBar().find(this.name_count_files).text(count);
	},
	disabledControls: function(new_status)
	{
		this.getToolBar().find('input, select, button').attr('disabled', new_status);
		if (new_status)
		{
			this.getToolBar().find('a.btn').addClass('disabled');
		}
		else
		{
			this.getToolBar().find('a.btn').removeClass('disabled');
		}
	}
}




function log_read_file(toolbar, log_area, resume)
{
	var process = new read_file_process(toolbar, log_area, resume, true);
	var post_data = {
		module: 'logfiles',
		command: 'log_file_read',
		log_file: process.getFileName(),
		log_lines: process.getNumLines(),
		log_filter: process.getFilter(),
		log_resume: process.getResume(),
		log_session: global_module_logfiles_id_tail
	};
	process.begin();
	$.post(window.FreePBX.ajaxurl, post_data, function(data)
	{
		process.setStatusAjax("AJAX_SEND_QUERY");
	})
	.done(function(data)
	{
		if (data.status)
		{
			var info = data.info;
			global_module_logfiles_id_tail = info.session_id;

			// We establish the value of the line number of the 
			// first new line that has been obtained.
			var init_count = 0;
			switch (info.type)
			{
				case 'FIRS_READ':
				case 'CLEAN_FILE':
					// init_count = 0;
					if ( info.count_new > info.count_read )
					{
						// Subtract 1, because at the beginning of the for
						// is where the init_count value is increased.
						init_count = info.count_new - info.count_read -1;
					}
					break;

				case 'NEW_LINES':
					init_count = info.count_all - info.count_new;
					break;

				// case 'EQUAL':
				// 	init_count = 0;
				//  	break;
			}

			process.setTypeReturn(info.type);
			process.addLinesLogArea(data.data, function(line, self)
			{
				init_count++;

				// Line numbering types:
				//
				// {auto} 			 => Read the value from the previous line and increase the value +1, starting from 1.
				//						$("<span/>", { 'class': 'line_log_num' } ).text("{{auto}}"),
				//
				// init_count 		 => We use line numbering from the original file.
				//						$("<span/>", { 'class': 'line_log_num' } ).text(init_count),
				//
				// self.countLines() => We count the number of lines that are added.
				//						$("<span/>", { 'class': 'line_log_num' } ).text(self.countLines()),
				//

				var new_line = $('<div/>', { 'class': 'line_log' })
				.append(
					$("<span/>", { 'class': 'line_log_num' }	 ).text(init_count),
					$("<span/>", { 'class': 'line_log_data' }	 ).append(line),
					$("<span/>", { 'class': 'line_log_acctions' })
					.append(
						$("<i/>", { 'class': 'fa fa-files-o copy-line'})
					)
				);
				
				// A jQuery object has to be returned.
				// The next line converts the html code into a jQuery object.
				// new_line = $.parseHTML(new_line);

				return new_line;
			});
			switch ( process.getTypeReturn() )
			{
				case "FIRS_READ":
					fpbxToast( i18n_mod('LOAD_LOG_OK'), '', 'success');
					break;
					  
				case "CLEAN_FILE":
					fpbxToast( i18n_mod('LESS_LINES_RELOAD_LOG_OK'), '', 'warning');
					break;
			}
			process.setStatusAjax("DONE");
		}
		else
		{
			process.setStatusAjax("STATUS_FAILED");
			fpbxToast(data.message, '', 'error');
		}		
	})
	.fail(function(err) { process.setStatusAjax("AJAX_ERROR"); })
	.always(function()	{ process.finish(); });

}

function read_file_process(toolbar, log_area, resume, stoptimer = true)
{
	this._debug 	= false;

	this.toolbar	= toolbar;
	this.log_area	= log_area;
	this.resume	 	= resume;

	this.status_ajax = null;
	this.status 	 = null;
	this.type_return = null;

	this.refresh_interval 		  = null;
	this.refresh_interval_default = 3;		// default value that is used if no interval has been defined.
	this.auto_scroll_animation 	  = 1000;	// Time it takes to scroll to the end (time in milliseconds).

	this.new_line_level_max		  = 3;		// Number of levels that are applied to new lines.
	this.new_line_expire 		  = 60;		// Seconds before newline styles are removed, setting 0 will not remove styles.
	this.new_line_enabled		  = true;	// TRUE styles are applied to new lines, FALSE styles are not applied.

	this.name_select_filename				 = 'filename_log';
	this.name_input_filter					 = 'fileter_log';
	this.name_input_num_lines				 = 'lines_log';
	this.name_input_highlight				 = 'highlight_log';
	this.name_size_buffer					 = 'size_buffer';
	this.name_radio_autoscroll				 = 'auto_scroll';
	this.name_radio_show_col_num			 = 'show_col_num_line';
	this.name_radio_show_line_spacing		 = 'show_line_spacing';
	this.name_radio_show_only_rows_highlight = 'show_only_rows_highlight';
	this.name_txt_refres_interval			 = '.refresh-interval-time-now';
	this.name_txt_count_highlight			 = '.count_highlight';
	

	this.class_txt_highlight      = 'highlight_log';
	this.class_line_log			  = 'line_log';
	this.class_line_log_num		  = 'line_log_num';
	this.class_line_log_data	  = 'line_log_data';

	this.class_loading			  = '.ico_loading';
	this.class_lines_log		  = '.lines_log';
	this.class_new_line_level	  = 'newline_leve_';
	this.class_fullscreen		  = 'fullscreenlog';

	this.class_line_spacing		  = 'line_spacing';
	
	this.init(stoptimer);
}

read_file_process.prototype = {

	init: function(stoptimer)
	{
		if (stoptimer) { this.stopTimer(); }

		if (this.getGlobalTimerInterval() === null)
		{
			//Defines the default value if it has not been started
			this.setTimerInterval(this.refresh_interval_default);
		}
		else 
		{
			//Sync From Global value.
			this.syncFromGlobalTimerInterval();
			this.drawRefreshInterval();
		}
	},
	debug: function(txt) 
	{
		if (this._debug) { console.log(txt); }
	},
	isNumeric: function(num)
	{
		return !isNaN(parseFloat(num)) && isFinite(num);
	},
	begin: function() 
	{
		if ( this.status === true ) { return; }
		this.status = true;

		if ( ! this.getResume() ) 
		{
			this.cleanLogArea();
			this.showIconLoading(true);
		}
	},
	finish: function()
	{
		if (this.status === false) { return; }
		this.status = false;

		if ( this.getStatusAjax() == "DONE" )
		{
			if ( this.isAutoRefresh() )
			{
				this.startTimer();
			}
		} 
		else
		{
			this.setTimerInterval(0);
		}

		this.showIconLoading(false);
	},
	setStatusAjax: function(new_status)
	{
		this.status_ajax = new_status;
	},
	getStatusAjax: function()
	{
		return this.status_ajax;
	},
	getToolBar: function()
	{
		return this.toolbar;
	},
	getLogArea: function() 
	{
		return this.log_area;
	},
	getResume: function()
	{
		return this.resume;
	},
	findByName: function(name)
	{
		return this.getToolBar().find("[name='" + name + "']");
	},
	getValByName: function(name)
	{
		return this.findByName(name).val();
	},
	getFileName: function()
	{
		return this.getValByName(this.name_select_filename);
	},
	getFilter: function()
	{
		return this.getValByName(this.name_input_filter);
	},
	getNumLines: function()
	{
		return this.getValByName(this.name_input_num_lines);
	},
	isAutoRefresh: function()
	{
		return (this.getTimerInterval() > 0 ? true : false);
	},
	drawRefreshInterval: function()
	{
		var timer = this.getTimerInterval();
		var new_txt = i18n_mod('DISABLED');
		if (timer > 0)
		{
			new_txt = (timer / 1000) + " " + i18n_mod('SECONDS');
		}
		var box_txt = this.getToolBar().find(this.name_txt_refres_interval);
		if ( new_txt != box_txt.text() )
		{
			box_txt.text(new_txt);
		}
	},
	setTimerInterval: function(new_timer)
	{
		var new_val = ( this.isNumeric(new_timer) ? parseFloat(new_timer) * 1000 : 0 );
		this.setGlobalTimerInterval(new_val);
		this.syncFromGlobalTimerInterval();
		this.drawRefreshInterval();
		this.resetTimer();
	},
	syncFromGlobalTimerInterval: function()
	{
		this.refresh_interval = this.getGlobalTimerInterval();
	},
	getTimerInterval: function()
	{
		return (this.isNumeric(this.refresh_interval) && this.refresh_interval > 0 ? this.refresh_interval : 0);
	},
	setTypeReturn: function(new_type)
	{
		this.type_return = new_type;
	},
	getGlobalTimerInterval: function()
	{
		return global_module_logfiles_refresh_interval;
	},
	setGlobalTimerInterval: function(new_timer)
	{
		global_module_logfiles_refresh_interval = new_timer;
	},
	getTypeReturn: function()
	{
		return this.type_return;
	},


	isAutoScroll: function()
	{
		return (this.getToolBar().find("[name='"+ this.name_radio_autoscroll +"']:checked").val() == "on" ? true : false);
	},
	runAutoScroll: function(force = false, animation = null)
	{
		if ( this.isAutoScroll() || force )
		{
			if (animation === null) { animation = this.auto_scroll_animation; }

			if ( this.auto_scroll_animation > 0)
			{
				this.getLogArea().animate({ scrollTop: this.getLogArea().prop("scrollHeight")}, animation ); 
			}
			else
			{
				this.getLogArea().scrollTop(this.getLogArea().prop("scrollHeight"));
			}
			// log.animate({
			// 	scrollTop: log[0].scrollHeight - log[0].clientHeight
			//   }, 1000);
		}
	},


	getLogAreaLines: function()
	{
		return this.getLogArea().find(this.class_lines_log);
	},

	showIconLoading: function(new_status)
	{
		var box_loading = this.getLogArea().find(this.class_loading);

		if (new_status) { box_loading.show(); }
		else 			{ box_loading.hide(); }
	},

	cleanLogArea: function() 
	{
		this.setTextLogArea("");
	},
	setTextLogArea: function(text)
	{
		this.getLogAreaLines().html(text);
	},
	addLineLogArea: function(element_line)
	{
		// Control buffer
		this.controlBuffer();
		
		// Auto numbering lines
		var num_line = $(element_line).find('span.' + this.class_line_log_num);
		if ( num_line.text().trim() === "{{auto}}" )
		{
			var new_num = this.getNumberLastLine() + 1;
			num_line.text(new_num);
		}

		// Check if column number is to be show
		if ( ! this.isShowColNumLine() ) { num_line.hide(); }

		// Check if you have to add the separation line
		if ( this.isShowLineSpacing() )  { element_line.addClass(this.class_line_spacing); }

		// Add line
		this.getLogAreaLines().append(element_line);
	},
	addLinesLogArea: function(new_lines, callback_proces = null)
	{
		if (this.getTypeReturn() == "FIRS_READ" || this.getTypeReturn() == "CLEAN_FILE")
		{
			this.cleanLogArea();
		}

		this.updateClassLevelNewLine();
		
		var self = this;
		new_lines.forEach( function( line )
		{
			var new_line =  $( $.parseHTML( line ) );
		 	if (typeof callback_proces === "function")
		 	{
				new_line = $( callback_proces(new_line, self) );
			}
			new_line = $( self.newClassLevelNewLine(new_line) );
			new_line = $( self.applyHighlightLine(new_line) );
			
		 	self.addLineLogArea(new_line);
		});

		if ( this.getTypeReturn() != "EQUAL" )
		{
			this.updateCountyHighlight();
			this.runAutoScroll();
		}
	},
	countLines: function()
	{
		return this.getLogAreaLines().find("div." + this.class_line_log).length + 1;
	},
	getNumberLastLine: function(val_default = 0)
	{
		var last_num = this.getLogAreaLines().find('div.' + this.class_line_log + ':last').find('span.' + this.class_line_log_num).text().trim();
		var new_num = val_default;
		if ( this.isNumeric(last_num) )
		{
			new_num = parseInt(last_num);
		}
		return new_num;
	},
	isShowColNumLine: function()
	{
		return (this.getToolBar().find("[name='" + this.name_radio_show_col_num + "']:checked").val() == "on" ? true : false);
	},
	updateShowColNumLine: function() {
		
		var col_num = this.getLogAreaLines().find('.' + this.class_line_log_num);

		if ( this.isShowColNumLine() ) 	{ col_num.show(); }
		else 							{ col_num.hide(); }
	},

	isShowLineSpacing: function()
	{
		return (this.getToolBar().find("[name='" + this.name_radio_show_line_spacing + "']:checked").val() == "on" ? true : false);
	},
	updateLineSeparation: function()
	{
		var lines = this.getLogAreaLines().find("." + this.class_line_log);
		
		if ( this.isShowLineSpacing() ) { lines.addClass(this.class_line_spacing); }
		else 							{ lines.removeClass(this.class_line_spacing); }
	},
	
	
	isTimerRunning: function()
	{
		return (global_module_logfiles_timeout_resume != null ? true : false);
	},
	startTimer: function(timer = null)
	{
		if ( ! this.isTimerRunning() ) 
		{
			if ( this.getFileName() )
			{
				if (! timer || timer <= 0)
				{
					timer = this.getTimerInterval();
				}
				if (timer > 0 )
				{
					this.debug("Info: Starting timer with an interval of " + timer + " ms.");

					var self = this;
					global_module_logfiles_timeout_resume = setTimeout(function() { log_read_file( self.getToolBar(), self.getLogArea(), true) }, timer);
				}
				else { this.debug("Info: The start of the timer is omitted since the interval is 0 or null."); }
			}
			else { this.debug("Notice: The timer start is skipped since no file has been defined."); }
		}
		else { this.debug("Notice: No need to start the timer is already started."); }
	},
	stopTimer: function()
	{
		if ( this.isTimerRunning() )
		{
			this.debug("Info: Stop Timer");

			clearTimeout(global_module_logfiles_timeout_resume);
			global_module_logfiles_timeout_resume = null;
		}
		else { this.debug("Notice: No need to stop the timer as it is stopped."); }
	},
	resetTimer: function()
	{
		this.debug("Info: Restart Timer");

		this.stopTimer();
		this.startTimer();
	},
	

	isShowOnlyRowsHighlight: function()
	{
		return (this.getToolBar().find("[name='"+ this.name_radio_show_only_rows_highlight +"']:checked").val() == "on" ? true : false);
	},
	getHighlight: function()
	{
		return this.getValByName(this.name_input_highlight);
	},
	isSetHighlight: function()
	{
		return (this.getHighlight() ? true : false );
	},
	cleanHighlight: function(update_count = true, show = true)
	{
		$(this.getLogAreaLines()).removeHighlight();

		if (show) 		  { this.getLogAreaLines().find('div.' + this.class_line_log).show(); }
		if (update_count) { this.updateCountyHighlight(); }
	},
	applyHighlight: function()
	{
		if ( this.isSetHighlight() )
		{
			this.cleanHighlight(false, false);
			this.updateCountyHighlight( i18n_mod('SEARCHING') );

			var self = this;
			this.getLogAreaLines().find('div.' + this.class_line_log).each(function()
			{
				self.applyHighlightLine(this);
			});
			this.updateCountyHighlight();
		} 
		else
		{
			this.cleanHighlight();
			this.debug("Notice: applyHighlight is ignored, no text has been defined to search.");
		}
		this.runAutoScroll( false, 0 );
	},
	applyHighlightLine: function(element_line)
	{
		if ( this.isSetHighlight() )
		{
			$(element_line).textHighlight( this.getHighlight(), {
				element: 'span',
				class: this.class_txt_highlight,
				caseSensitive: false
			});

			if ( this.isShowOnlyRowsHighlight() )
			{
				if ( this.isLineHighlight(element_line) ) 	{ $(element_line).show(); }
				else 										{ $(element_line).hide(); }
			}
			else
			{
				$(element_line).show();
			}
		}
		return element_line;
	},
	isLineHighlight: function(element_line)
	{
		return ($(element_line).find('span.' + this.class_txt_highlight).length > 0 ? true : false );
	},
	updateCountyHighlight: function(txt = null)
	{
		var label_count = this.getToolBar().find(this.name_txt_count_highlight);
		var new_txt = i18n_mod('NONE');
		if (txt)
		{
			new_txt = txt;
		}
		else if ( this.isSetHighlight() )
		{
			new_txt = this.getLogAreaLines().find('span.' + this.class_txt_highlight).length;
		}
		label_count.text( new_txt );
	},


	getNameClassLevelNewLine: function(level)
	{
		return this.class_new_line_level + level;
	},
	enabledClassLevelNewLine: function()
	{
		return this.new_line_enabled;
	},
	updateClassLevelNewLine: function(force = false)
	{
		if ( this.getTypeReturn() != "EQUAL" && this.enabledClassLevelNewLine() || force)
		{
			for (var i = this.new_line_level_max; i >= 0; i--)
			{
				var newline 	 = this.getNameClassLevelNewLine(i);
				var newline_next = this.getNameClassLevelNewLine(i + 1);
				var toggleClass  = newline + ( i < this.new_line_level_max ? ' ' + newline_next : '' );
				this.getLogAreaLines().find('.' + newline).toggleClass(toggleClass);
			}
		}
	},
	cleanClassLevelNewLine: function(element_line)
	{
		var class_remove = "";
		for (var i = 0; i <= this.new_line_level_max; i++)
		{
			class_remove += " " + this.getNameClassLevelNewLine(i);
		}
		element_line.removeClass(class_remove);
	},
	newClassLevelNewLine: function(element_line, timer = null, force = false)
	{
		if ( this.enabledClassLevelNewLine() || force )
		{
			if (! timer || ! this.isNumeric(timer))
			{
				timer = (this.new_line_expire * 1000);
			}

			var self = this;
			element_line.fadeIn('slow', function()
			{
				if (timer > 0)
				{
					setTimeout(function() { self.cleanClassLevelNewLine(element_line) }, timer );
				}
			});

			// if (this.getTypeReturn() != "FIRS_READ" && this.getTypeReturn() != "CLEAN_FILE")
			if (this.getTypeReturn() != "FIRS_READ")
			{
				element_line.addClass(this.getNameClassLevelNewLine(0));
			}
		}
		return element_line;
	},


	controlBuffer: function(force = false)
	{
		var buffer = this.getBufferSize();
		if ( buffer > 0 || force)
		{
			var num_lines = this.countLines();
			if ( num_lines > buffer )
			{
				var diff = num_lines - buffer - 1;
				this.getLogAreaLines().find('div.'+ this.class_line_log +':lt(' + diff + ')').remove();
			}
		}
	},
	getBufferSize: function()
	{
		return this.getValByName(this.name_size_buffer);
	},
	

	isFullScreen: function()
	{
		return this.getLogArea().hasClass(this.class_fullscreen);
	},
	fullScreen: function(new_status)
	{
		if ( ( ( new_status ) && ( ! this.isFullScreen() ) ) || ( ( ! new_status) && ( this.isFullScreen() ) ) )
		{
			this.getLogArea().toggleClass(this.class_fullscreen);
			if ( this.isFullScreen() ) 
			{
				$("body").css("overflow", "hidden");
			} 
			else
			{
				$("body").css("overflow", "auto");
			}
		}
	}

}