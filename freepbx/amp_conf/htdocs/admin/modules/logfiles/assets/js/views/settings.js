/**
 * UI Config Settings
 * 
 * @author Javier Pastor (VSC55)
 * @license GPLv3
 */

var global_module_logfiles_i18n = i18nGet('settings');

$(document).ready(function()
{

	$(".settings_input").each(settings_get_value);
	$(document).on("click", ".setting_realod", function(e)
	{
		e.preventDefault();
		t = e.target || e.srcElement;
		$(t).closest('.form-group').find('input').each(settings_get_value);
	});
	$(document).on("click", "input:radio.settings_input", function(e)
	{
		// Not run "preventDefault" because it stops the selection of the radio
		// e.preventDefault();
		t = e.target || e.srcElement;
		settings_save(null, t);
	});
	$(document).on("click", ".setting_save", function(e)
	{
		e.preventDefault();
		t = e.target || e.srcElement;
		$(t).closest('.form-group').find('input').each(settings_save);
	});

	$('#logfiles_add_new_line').on("click", logfiles_add_new_line);
	$(document).on("click", ".logfiles_add_new", function(e)
	{
		e.preventDefault();
		t = e.target || e.srcElement;
		logfiles_add_new(t);
	});
	$(document).on("click", ".logfiles_add_cancel", function(e)
	{
		e.preventDefault();
		t = e.target || e.srcElement;
		var process = new logfiles_process(t);
		process.removeRow(false);
	});
	$(document).on("click", ".logfiles_save", function(e)
	{
		e.preventDefault();
		t = e.target || e.srcElement;
		logfiles_save(t);
	});
	$(document).on("click", ".logfiles_destory", function(e)
	{
		e.preventDefault();
		t = e.target || e.srcElement;
		logfiles_destory(t);
	});

});


function show_btn_apply_conf()
{
	$('#button_reload').show();
}


/*
 * INIT > TAB - LOG_FILES
 */

function is_Numeric(num)
{
	return !isNaN(parseFloat(num)) && isFinite(num);
}

function logfiles_cell_id(value, row, index, field)
{
	var html = $('<input/>', { 
					'type': 'hidden',
					'name': field,
					'value': row.name
				})
				.get(0).outerHTML;

	html += value;
	return html;
}

function logfiles_cell_acction(value, row, index)
{
	var data_return = $('<div/>', {
		'role': 'group',
		'class': 'btn-group btn-group-justified blocks'
	});

	if (row['readonly'] == "0")
	{
		data_return
		.append(
			$('<div/>', {
				'role': 'group',
				'class': 'btn-group btn-group-success'
			})
			.append(
				$('<button/>', {
					'class': 'btn btn-success btn-sm logfiles_save',
					'title': i18n_mod("SAVE")
				})
				.append( $('<i/>', { 'class': 'fa fa-floppy-o'}) )
			)
		);
		if (row['permanent'] == "0")
		{
			data_return
			.append(
				$('<div/>', {
					'role': 'group',
					'class': 'btn-group btn-group-danger'
				})
				.append(
					$('<button/>', {
						'class': 'btn btn-danger btn-sm logfiles_destory',
						'title': i18n_mod("REMOVE")
					})
					.append( $('<i/>', { 'class': 'fa fa-trash'}) )
				)
			);
		}
	}
	return data_return.get(0).outerHTML;
}

function logfiles_cell_dropdown(value, row, index, field)
{
	var select = $('<select/>', { 'class': 'form-control', 'name': field });
	if (field == 'verbose') 
	{
		if ( is_Numeric(value) )
		{
			value = parseFloat(value);
			if (value >= 10 ) { value = 10; }
		}
		else
		{
			if (value == 'off') 	{ value = 0; }
			else if(value == 'on') 	{ value = 3; }
		}

		select.append(new Option( i18n_mod("OFF"), 'off', (value == 0 ? true : false) ));
		select.append(new Option( i18n_mod("ON") , 'on',  (value == 3 ? true : false) ));
		var i;
		for (i = 4; i <= 10; i++)
		{
			select.append(new Option(i, i, (value == i ? true : false) ));
		}
		select.append(new Option('*' , '*', (value == '*' ? true : false) ));
	}
	else if (field == 'disabled')
	{
		select.append(new Option( i18n_mod("DISABLED"), '1', (value == '1' ? true : false) ));
		select.append(new Option( i18n_mod("ENABLED") , '0', (value != '1' ? true : false) ));
	}
	else
	{
		select.append(new Option( i18n_mod("ON") , 'on',  (value == 'on' ? true : false) ));
		select.append(new Option( i18n_mod("OFF"), 'off', (value != 'on' ? true : false) ));
	}

	if ( ( row !== null ) && ( row['readonly'] == "1") )
	{
		select.attr('disabled', true);
	}

	return select.get(0).outerHTML;
}

function logfiles_add_new_line(e) 
{
	e.preventDefault();
	$('#logfile_entries > tbody:last').each(function() 
	{
		var input = $('<input/>', { 'type': 'text', 'name': 'name', 'value': '', 'class': 'form-control'});
		$(this).find('tr:last').after(
			$('<tr/>', {})
			.append(
				$('<td/>', {'class': 'form-group'}).append( input ),
				$('<td/>', {}).html( logfiles_cell_dropdown(0,	   null, null, 'disabled') ),
				$('<td/>', {}).html( logfiles_cell_dropdown('on',  null, null, 'debug') ),
				$('<td/>', {}).html( logfiles_cell_dropdown('off', null, null, 'dtmf') ),
				$('<td/>', {}).html( logfiles_cell_dropdown('on',  null, null, 'error') ),
				$('<td/>', {}).html( logfiles_cell_dropdown('off', null, null, 'fax') ),
				$('<td/>', {}).html( logfiles_cell_dropdown('on',  null, null, 'notice') ),
				$('<td/>', {}).html( logfiles_cell_dropdown('on',  null, null, 'verbose') ),
				$('<td/>', {}).html( logfiles_cell_dropdown('on',  null, null, 'warning') ),
				$('<td/>', {}).html( logfiles_cell_dropdown('off', null, null, 'security') ),
				$('<td/>', {})
				.append(
					$('<div/>', { 'role': 'group', 'class': 'btn-group btn-group-justified blocks'})
					.append(
						$('<div/>', { 'role': 'group', 'class': 'btn-group btn-group-success'})
						.append(
							$('<button/>', {'class': 'btn btn-success btn-sm logfiles_add_new', 'title': i18n_mod("CREATE") })
							.append(
								$('<i/>', { 'class': 'fa fa-check'}),
							)
						),
						$('<div/>', { 'role': 'group', 'class': 'btn-group btn-group-danger'})
						.append(
							$('<button/>', {'class': 'btn btn-danger btn-sm logfiles_add_cancel', 'title': i18n_mod("CANCEL") })
							.append(
								$('<i/>', { 'class': 'fa fa-times'}),
							)		
						),
					)
				)
			)	
		);
		input.focus();
	});
}

function logfiles_add_new(e) 
{
	var process = new logfiles_process(e);
	
	if ( ! process.checkId(false) )
	{
		process.errorFilename( i18n_mod("ERROR_FILENAME_MISSING") );
	}
	else
	{
		var post_data = {
			module: 'logfiles',
			command: 'logfiles_is_exist_file_name',
			namefile: process.getId()
		};
		$.post(window.FreePBX.ajaxurl, post_data, function(data) 
		{
			process.begin();
			process.setStatusAjax("AJAX_SEND_QUERY");
		})
		.done(function(data) 
		{
			if (data.status)
			{
				if (data.exist)
				{
					process.setStatusAjax("ERROR_FILENAME_EXIST");
					process.errorFilename( i18n_mod("ERROR_FILENAME_ALREADY_EXISTS") );
				}
				else { process.setStatusAjax("DONE"); }
			}
			else
			{
				process.errorFilename( data.message ? data.message : i18n_mod("ERROR_UNKNOW") );
				process.setStatusAjax("STATUS_FAILED");
			}
		})
		.fail(function(err) { process.setStatusAjax("AJAX_ERROR"); })
		.always(function() 
		{
			if (process.getStatusAjax() == "DONE")
			{
				logfiles_save(e, true);
			}
			else { process.finish(); }
		});
	}
}

function logfiles_save(e, refres_all = false)
{
	var process = new logfiles_process(e);

	if ( ! process.checkId(false) )
	{
		process.errorFilename( i18n_mod("ERROR_FILENAME_MISSING") );
	}
	else
	{
		var new_data = process.getControlsVal();
		var post_data = {
			module: 'logfiles',
			command: 'logfiles_set',
			namefile: process.getId(),
			data: JSON.stringify(new_data)
		};
		$.post(window.FreePBX.ajaxurl, post_data, function(data) 
		{
			process.begin();
			process.setStatusAjax("AJAX_SEND_QUERY");
		})
		.done(function(data)
		{
			fpbxToast(data.message, '', (data.status ? 'success' : 'error') );
			if (data.status)
			{
				if (refres_all) 
				{
					process.refreshTable();
				}
				process.setStatusAjax("DONE");
			}
			else { process.setStatusAjax("STATUS_FAILED"); }
		})
		.fail(function(err) { process.setStatusAjax("AJAX_ERROR"); })
		.always(function()
		{ 
			process.finish();
			if (process.getStatusAjax() == "DONE")
			{
				show_btn_apply_conf();
			}
		});
	}
}

function logfiles_destory(e)
{
	var process = new logfiles_process(e);

	if ( process.checkId(true) )
	{
		fpbxConfirm(
			sprintf( i18n_mod("CONFIRMING_REMOVE") , process.getId(true) ),
			i18n_mod("YES"), i18n_mod("NO"),
			function()
			{
				var post_data = {
					module: 'logfiles',
					command: 'logfiles_destory',
					namefile: process.getId()
				};
				$.post(window.FreePBX.ajaxurl, post_data, function(data) 
				{
					process.begin();
					process.setStatusAjax("AJAX_SEND_QUERY");
				})
				.done(function(data) 
				{
					fpbxToast(data.message, '', (data.status ? 'success' : 'error') );
					if (data.status)
					{
						process.removeRow();
						process.setStatusAjax("DONE");
					} 
					else { process.setStatusAjax("STATUS_FAILED"); }
				})
				.fail(function(err) { process.setStatusAjax("AJAX_ERROR"); })
				.always(function()
				{
					process.finish();
					if (process.getStatusAjax() == "DONE")
					{
						show_btn_apply_conf();
					}
				});
			}
		);
	}
}

function logfiles_process(e)
{
	this.e 			 = e;
	this.status_ajax = null;
	this.status 	 = null;
	this.row 		 = $(e).closest('tr');
	this.table 		 = $(e).closest('table');
}

logfiles_process.prototype = {
	begin: function() 
	{
		if (this.status === true) { return; }
		this.disabledControlsRow(true);
		$(this.e).find('i').addClass("fa-spinner fa-spin");
		this.status = true;
	},
	finish: function() 
	{
		if (this.status === false) { return; }
		this.disabledControlsRow(false);
		$(this.e).find('i').removeClass("fa-spinner fa-spin");
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
	getTable: function()
	{
		return this.table;
	},
	refreshTable: function() 
	{
		this.getTable().bootstrapTable('refresh');
	},
	getRow: function()
	{
		return this.row;
	},
	removeRow: function(refres_all = true)
	{
		if (refres_all)
		{
			this.getTable().bootstrapTable('removeByUniqueId', this.getId() );
		}
		else 
		{
			var row = this.getRow();
			$(row).fadeOut('normal', function()
			{
				$(row).remove();
			});
		}
	},
	disabledControlsRow: function(new_status)
	{
		this.getRow().find('input, select, button').attr('disabled', new_status);
		if (new_status)
		{
			this.getRow().find('a.btn').addClass('disabled');
		}
		else
		{
			this.getRow().find('a.btn').removeClass('disabled');
		}
	},
	getId: function(htmlEncode = false)
	{
		var data_return = $(this.getRow()).find('input[name="name"]').val();
		if (htmlEncode)
		{
			data_return = $('<div/>').text(data_return).html();
		}
		return data_return;
	},
	getControlsVal: function()
	{	
		var data_return = {};
		this.getRow().find('input, select').each(function()
		{
			if ($(this).val())
			{
				data_return[$(this).attr('name')] = $(this).val();
			}
		});
		return data_return;
	},
	checkId: function(showmsg = true)
	{
		if (! this.getId())
		{
			if (showmsg)
			{
				fpbxToast( i18n_mod("NAME_NOT_DEFINED"), '', 'error');
			}
			return false;
		}
		return true;
	},
	errorFilename: function(error_text)
	{
		if (error_text)
		{
			fpbxToast(error_text, '', 'error');
		}
		
		this.getRow().find('input[name="name"]').not("input[type='hidden']").closest('td').each(function() 
		{
			if (! $(this).hasClass("has-error") ) 
			{
				$(this).addClass("has-error has-feedback")
				.append(
					$("<span/>", { 'class': 'glyphicon glyphicon-remove form-control-feedback'})
				);
			}
		});
		
	}
}

/*
 * END > TAB - LOG_FILES
 */


/*
 * INIT > TAB - SETTINGS
 */

function settings_get_value(i, e)
{
	var process = new settings_process(e);
	var post_data = {
		module: 'logfiles',
		command: 'settings_get',
		setting: process.getName()
	};
	$.post(window.FreePBX.ajaxurl, post_data, function(data) 
	{
		if ( process.getType() != "text" )
		{
			process.displayUndo(false);
		}
		process.begin();
		process.setStatusAjax("AJAX_SEND_QUERY");
	})
	.done(function(data)
	{
		if (data.status)
		{
			process.setStatusAjax( process.setValue(data.value) ? "DONE" : "ERROR_SET_NEW_VALUE" );
		}
		else
		{
			process.setStatusAjax("STATUS_FAILED");
			fpbxToast(data.message, '', 'error');
		}
	})
	.fail(function(err) { process.setStatusAjax("AJAX_ERROR"); })
	.always(function()
	{
		process.finish();
		if (process.getStatusAjax() != "DONE")
		{
			process.displayUndo(true);
		}
	});
}

function settings_save(i, e) 
{
	var process = new settings_process(e);
	var post_data = {
		module: 'logfiles',
		command: 'settings_set',
		setting: process.getName(),
		val: process.getValue()
	};
	$.post(window.FreePBX.ajaxurl, post_data, function(data)
	{
		if ( process.getType() != "text" )
		{
			process.displayUndo(false);
		}
		process.begin();
		process.setStatusAjax("AJAX_SEND_QUERY");
	})
	.done(function(data)
	{
		process.setStatusAjax( data.status ? "DONE" : "STATUS_FAILED" );
		fpbxToast(data.message, '', (data.status ? 'success' : 'error') );
	})
	.fail(function(err) { process.setStatusAjax("AJAX_ERROR"); })
	.always(function()
	{
		process.finish();
		if (process.getStatusAjax() != "DONE")
		{
			settings_get_value(null, e);
		}
		else
		{
			show_btn_apply_conf();
		}
	});
}

function settings_process(e) 
{
	this.e 			 = e;
	this.status_ajax = null;
	this.status 	 = null;
	this.form 		 = $(e).closest('.form-group');
}

settings_process.prototype = {
	begin: function() 
	{
		if (this.status === true) { return; }
		this.disabledControls(true);
		// $(this.e).find('i').addClass("fa-spinner fa-spin");
		this.status = true;
	},
	finish: function() 
	{
		if (this.status === false) { return; }
		this.disabledControls(false);
		// $(this.e).find('i').removeClass("fa-spinner fa-spin");
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
	getForm: function()
	{
		return this.form;
	},
	getType: function()
	{
		return this.e.getAttribute('type');
	},
	getName: function()
	{
		return this.e.getAttribute('name');
	},
	getValue: function()
	{
		return $(this.e).val();
	},
	setValue: function(new_value)
	{
		switch (this.getType())
		{
			case 'text':
				$(this.e).val( new_value );
				break;

			case 'radio':
				$("input[value=" + new_value + "]", this.getForm()).prop('checked', true);
				break;
			
			default:
				console.log("Type input not implement!");
				return false;
		}
		return true;
	},
	disabledControls: function(new_status)
	{
		this.getForm().find('input, select, button').attr('disabled', new_status);
		if (new_status)
		{
			this.getForm().find('a.btn').addClass('disabled');
		}
		else
		{
			this.getForm().find('a.btn').removeClass('disabled');
		}
	},
	displayUndo: function(new_status)
	{
		if (new_status)
		{
			this.getForm().find('a.setting_realod').show();
		}
		else
		{
			this.getForm().find('a.setting_realod').hide();
		}
	}
}

/*
 * END > TAB - SETTINGS
 */
