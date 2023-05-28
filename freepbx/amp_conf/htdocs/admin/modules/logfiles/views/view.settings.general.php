<?php
    $options = array(
        array(
            'id'     => 'dateformat',
            'label'  => _("Date Format"),
            'help'   => _('Customize the display of debug message time stamps. '
                        . 'See strftime(3) Linux manual for format specifiers. '
                        . 'Note that there is also a fractional second parameter which may be used in this field.  Use %1q for tenths, %2q for hundredths, etc.')
                        . _('Leave blank for default: ISO 8601 date format yyyy-mm-dd HH:MM:SS (%F %T)'),
            'input'  => array(),
            'type'  => 'text'
        ),
        array(
            'id'     => 'rotatestrategy',
            'label'  => _("Log Rotation"),
            'help'   => _('None: Do not perform any log rotation at all.  You should make very sure to set up some external log rotate mechanism as the asterisk logs can get very large, very quickly.').'<br/>'
                      . _('Sequential: Rename archived logs in order, such that the newest has the highest sequence number').'<br/>'
                      . _('Rotate: Rotate all the old files, such that the oldest has the highest sequence number (expected behavior for Unix administrators).').' <strong><i>(Default)</i></strong><br/>'
                      . _('Timestamp: Rename the logfiles using a timestamp instead of a sequence number when "logger rotate" is executed.').'<br/>',
            'input'  => array(
                array( 'id' => 'rotatestrategynone',        'value' => 'none',       'label' =>  _("None") ),
                array( 'id' => 'rotatestrategysequential',  'value' => 'sequential', 'label' =>  _("Sequential") ),
                array( 'id' => 'rotatestrategyrotate',      'value' => 'rotate',     'label' =>  _("Rotate") ),
                array( 'id' => 'rotatestrategytimestamp',   'value' => 'timestamp',  'label' =>  _("Timestamp") )
            ),
            'type'  => 'radio'
        ),
        array(
            'id'     => 'appendhostname',
            'label'  => _("Append Hostname"),
            'help'   => _("Appends the hostname to the name of the log files").". "._("Setting this to yes will interfere with log rotation and Intrusion Detection.  It is strongly recommended that this setting be set to 'no'."),
            'input'  => array(
                array( 'id' => 'appendhostnameyes', 'value' => 'yes', 'label' =>  _("Yes") ),
                array( 'id' => 'appendhostnameno',  'value' => 'no',  'label' =>  _("No") )
            ),
            'type'  => 'radio'
        ),
        array(
            'id'     => 'queue_log',
            'label'  => _("Log Queues"),
            'help'   => _("Log queue events to a file"),
            'input'  => array(
                array( 'id' => 'queue_logyes', 'value' => 'yes', 'label' =>  _("Yes") ),
                array( 'id' => 'queue_logno',  'value' => 'no',  'label' =>  _("No") )
            ),
            'type'  => 'radio'
        )
    );

    $all_code_html = "";
    foreach ($options as $key => $val)
    {
        if ($val['type'] != "radio") 
        {
            continue;
        }
        
        $code_html = <<<HTML
<div class="element-container">
    <div class="row">
        <div class="col-md-12">
            <div class="row">
                <div class="form-group">
                    <div class="col-md-3">
                        <label class="control-label" for="%%__ID__%%">%%__LABEL__%%</label>
                        <i class="fa fa-question-circle fpbx-help-icon" data-for="%%__ID__%%"></i>
                    </div>
                    <div class="col-md-8 radioset">
                        %%__FOR_INPUT__%%
                    </div>
                    <div class="col-md-1">
                        <a href="#" class="btn btn-block btn-danger setting_realod" style="display: none;" title="%%__BTN_RELOAD_TXT__%%">
                            <i class="fa fa-refresh"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <span id="%%__ID__%%-help" class="help-block fpbx-help-block">%%__HELP__%%</span>
        </div>
    </div>
</div>
HTML;
        
        foreach ($val['input'] as $for_key => $for_val)
        {
            $for_html = "";
            $for_html .= '<input type="radio" name="%%__ID__%%" class="settings_input" id="%%__INPUT_ID__%%" value="%%__INPUT_VALUE__%%">';
            $for_html .= '<label for="%%__INPUT_ID__%%">%%__INPUT_LABEL__%%</label>';

            //add tag for next input
            $for_html .= "%%__FOR_INPUT__%%";
            
            $for_html = str_replace("%%__INPUT_LABEL__%%",  $for_val['label'],   $for_html);
            $for_html = str_replace("%%__INPUT_ID__%%",     $for_val['id'],      $for_html);
            $for_html = str_replace("%%__INPUT_VALUE__%%",  $for_val['value'],   $for_html);
            
            //add input in code_html
            $code_html = str_replace("%%__FOR_INPUT__%%", $for_html,   $code_html);
        }

        $code_html = str_replace("%%__ID__%%",        $val['id'],    $code_html);
        $code_html = str_replace("%%__LABEL__%%",     $val['label'], $code_html);
        $code_html = str_replace("%%__HELP__%%",      $val['help'],  $code_html);


        $code_html = str_replace("%%__BTN_RELOAD_TXT__%%", _('Reload'),  $code_html);

        //clean
        $code_html = str_replace("%%__FOR_INPUT__%%", "", $code_html);

        $all_code_html .= $code_html;
    }

    $dateformat = $options[0];
?>

<div class="element-container">
    <div class="row">
        <div class="col-md-12">
            <div class="row">
                <div class="form-group">
                    <div class="col-md-3">
                        <label class="control-label" for="<?php echo $dateformat['id']; ?>"><?php echo $dateformat['label']; ?></label>
                        <i class="fa fa-question-circle fpbx-help-icon" data-for="<?php echo $dateformat['id']; ?>"></i>
                    </div>
                    <div class="col-md-9">
                        <div class="input-group">
                            <input type="text" class="settings_input form-control" id="<?php echo $dateformat['id']; ?>" name="<?php echo $dateformat['id']; ?>" value="<?php echo _("Loading..."); ?>" disabled>
                            <div class="input-group-btn">
                                <a href="#" class="btn btn-success setting_save" title="<?php echo _("Save"); ?>">
                                    <i class="fa fa-floppy-o"></i>
                                </a>
                                <a href="#" class="btn btn-default setting_realod" title="<?php echo _("Reload"); ?>">
                                    <i class="fa fa-refresh"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <span id="<?php echo $dateformat['id']; ?>-help" class="help-block fpbx-help-block"><?php echo $dateformat['help']; ?></span>
        </div>
    </div>
</div>

<?php echo $all_code_html; ?>