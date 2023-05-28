<?php
function get_new_row($item, $itemParent = null)
{
	$new_row = '
	<div class="element-container %%__HAS-ERROR__%%">
		<div class="row">
			<div class="form-group">
				<div class="col-lg-5 col-md-5 col-sm-12 col-xs-12">
					%%__TAB_SUBITEM_START__%%
					<i class="fa fa-chevron-right" aria-hidden="true"></i>
					<i class="fa fa-chevron-right" aria-hidden="true"></i>
					%%__TAB_SUBITEM_END__%%
					<label class="control-label" for="%%__FEATURE__%%">%%__TITLE__%%</label>
					%%__IF_HELP_START__%%
						<i class="fa fa-question-circle fpbx-help-icon" data-for="%%__FEATURE__%%"></i>
					%%__IF_HELP_END__%%
				</div>
				<div class="col-lg-4 col-md-3 col-sm-7 col-xs-12">
					<input type="text" name="fc[%%__MODULE__%%][%%__FEATURE__%%][code]" value="%%__CODE__%%" id="custom_%%__ID__%%" data-default="%%__DEFAULT__%%" placeholder="%%__DEFAULT__%%" data-custom="%%__CUSTOM__%%" class="form-control extdisplay" %%__IS_CUSTOM_INPUT__%% required pattern="[0-9A-D\*#]*">
				</div>
				<div class="col-lg-3 col-md-4 col-sm-5 col-xs-12 col-actions">
					<span class="radioset">
						<input type="checkbox" data-for="custom_%%__ID__%%" name="fc[%%__MODULE__%%][%%__FEATURE__%%][customize]" class="custom" id="usedefault_%%__ID__%%" %%__IS_CUSTOM_CHECK__%%>
						<label for="usedefault_%%__ID__%%">%%__LABEL_CUSTOMIZE__%%</label>
					</span>
					<span class="radioset">
						<input type="checkbox" class="enabled" name="fc[%%__MODULE__%%][%%__FEATURE__%%][enable]" id="ena_%%__ID__%%" %%__IS_ENABLED__%% %%__IS_ENABLED_OFF__%%>
						<label for="ena_%%__ID__%%">%%__LABEL_ENABLED__%%</label>
					</span>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="%%__FEATURE__%%-help" class="help-block fpbx-help-block">%%__HELP__%%</span>
			</div>
		</div>
	</div>
	';
	$isSubItem = (!is_null($itemParent));
	$remplaces 	= array(
		'HAS-ERROR' 		=> !empty($conflict['exten_conflict_arr'][$item['code']]) ? 'has-error' : '',
		'FEATURE' 			=> $item['feature'],
		'TITLE' 			=> $item['title'],
		'MODULE'			=> $item['module'],
		'CODE'				=> $item['code'],
		'ID'				=> $item['id'],
		'DEFAULT'			=> $item['default'],
		'CUSTOM'			=> $item['custom'],
		'IS_CUSTOM_INPUT' 	=> (! $item['iscustom']) ? 'readonly' : '',
		'LABEL_CUSTOMIZE' 	=> _("Customize"),
		'IS_CUSTOM_CHECK' 	=> ($item['iscustom']) ? 'checked' : '',
		'LABEL_ENABLED' 	=> _("Enabled"),
		'IS_ENABLED'		=> ($item['isenabled']) ? 'checked' : '',
		// 'IS_ENABLED_OFF'	=> ($isSubItem && !$itemParent['isenabled']) ? 'disabled' : '',
		'IS_ENABLED_OFF'	=> '',
		'HELP' 				=>  $item['help'],
		'IF_HELP_START'		=> empty($item['help']) ? '<!-- ' : '',
		'IF_HELP_END'		=> empty($item['help']) ? ' -->' : '',
		'TAB_SUBITEM_START'	=> ($isSubItem) ? '' : '<!-- ',
		'TAB_SUBITEM_END'	=> ($isSubItem) ? '' : ' -->',
	);
	foreach ($remplaces as $key => $value)
	{
		$new_row = str_replace("%%__".$key."__%%", $value, $new_row);
	}
	return $new_row;
}

// True show all subitems; False only show subitems if parent is enabled.
$any_show_all = true;
?>


<form autocomplete="off "class="fpbx-submit" name="frmAdmin" action="config.php?display=featurecodeadmin" method="post">
    <input type="hidden" name="action" value="save">
    <div class="display no-border">
        <div class="container-fluid">

            <!-- Conflict error may display here if there is one-->
            <?php if (! empty($conflict['conflicterror']) && is_array($conflict['conflicterror'])): ?>
                <div>
                    <script>javascript:alert('<?php echo _("You have feature code conflicts with extension numbers in other modules. This will result in unexpected and broken behavior."); ?>')</script>
			        <div class='alert alert-danger'><?php echo _("Feature Code Conflicts with other Extensions"); ?></div>
                    <?php echo implode('<br />', $conflict['conflicterror']); ?>
                </div>
            <?php endif ?>
            <!--End of error zone-->

            <!--Generated-->
            <?php foreach($modules as $rawname => $data): ?>
                <div class="section-title" data-for="<?php echo $rawname?>">
                    <h2><i class="fa fa-minus"></i> <?php echo $data['title']?></h2>
                </div>
                <div class="section" data-id="<?php echo $rawname?>">
                    <div class="element-container hidden-xs">
                        <div class="row">
                            <div class="form-group">
                                <div class="col-lg-5 col-md-5 col-sm-12 col-xs-12">
                                    <h4><?php echo _("Description")?></h4>
                                </div>
                                <div class="col-lg-4 col-md-3 col-sm-7 col-xs-6">
                                    <h4><?php echo _("Code")?></h4>
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-5 col-xs-6 col-actions">
                                    <h4><?php echo _("Actions")?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
					if (! empty($data['items']))
					{
						foreach($data['items'] as $item)
						{
							echo get_new_row($item);
							if (! empty($item['subitems']) && ($item['isenabled'] || $any_show_all))
							{
								foreach($item['subitems'] as $subitem)
								{
									echo get_new_row($subitem, $item);
								}
							}
						}
					}
					?>
                </div>
                <br/>
            <?php endforeach; ?>
            <!--END Generated-->

            <!-- Custom feature codes -->
            <?php if(isset($moduleCustomFeaturecodes) && !empty($moduleCustomFeaturecodes['customCodes'])): ?>
                <div class="section-title" data-for="<?php echo 'Custom feature codes'; ?>">
                    <h2><i class="fa fa-minus"></i> <?php echo _('Custom feature codes'); ?></h2>
                </div>
                <div class="section" data-id="<?php echo 'Custom feature codes'; ?>">
                <div class="section-title" data-for="<?php echo $moduleCustomFeaturecodes['moduleName']; ?>">
                    <h2><i class="fa fa-minus"></i> <?php echo $moduleCustomFeaturecodes['moduleName']; ?> </h2>
                </div>
                <div class="section" data-id="<?php echo $moduleCustomFeaturecodes['moduleName']; ?>">
                    <div class="element-container hidden-xs">
                        <div class="row">
                            <div class="form-group">
                                <div class="col-md-8">
                                    <h4><?php echo _("Description")?></h4>
                                </div>
                                <div class="col-md-4">
                                    <h4><?php echo _("Code")?></h4>
                                </div>
                            </div>
                        </div>
                    </div>	
                    <?php
                        foreach($moduleCustomFeaturecodes['customCodes'] as $code):
                        isset($moduleCustomFeaturecodes['featureCode']) ? $featurecode = $moduleCustomFeaturecodes['featureCode'] : '';
                        ?>
                        <div class="element-container">
                            <div class="row">
                                <div class="form-group">
                                    <div class="col-md-8">
                                        <label class="control-label"> <?php echo $code['reason']; ?> </label>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" disabled value="<?php echo $featurecode .  $code['code']; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                </div>
                <br/>
            <?php endif ?>
            <!-- End custom feature codes -->
        </div>
    </div>
</form>