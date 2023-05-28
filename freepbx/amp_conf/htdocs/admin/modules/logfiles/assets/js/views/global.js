/**
 * UI Config Global
 * 
 * @author Javier Pastor (VSC55)
 * @license GPLv3
 */

var global_module_logfiles_i18n = {};

function i18nGet(file_name)
{
    var data_return = {};

    if (file_name)
    {
        var post_data = {
            module: 'logfiles',
            command: 'i18n',
            filejs: file_name
        };
        $.ajax({
            async: false,
            type: 'post',
            url: window.FreePBX.ajaxurl,
            data: post_data,
            success: function (data)
            {
                if (data)
                {
                    if (! data.status )
                    {
                        console.log("i18nGet failed:", data.message);
                    }
                    else
                    {
                        data_return = data.i18n;
                    }
                }
                else 
                {
                    console.log("i18nGet failed: No data received!")
                }
            },
            error: function (request, status, error)
            {
                console.log("i18nGet ajax error:", jQuery.parseJSON(request.responseText).Message);
            }
        });
    }

    return data_return;
}

function i18n_mod(find)
{
	var return_data = "Not found in i18n!";
	if ( global_module_logfiles_i18n.hasOwnProperty(find) )
	{
		return_data = global_module_logfiles_i18n[find];
	}
	return return_data;
}