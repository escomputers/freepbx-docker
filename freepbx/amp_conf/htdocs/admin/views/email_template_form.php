<?php
foreach ($templateData as $key => $template) {
    extract($template);
    $identifier = !empty($identifier) ? strtolower($identifier) : "";
    $emailTypeLabel = !empty($type['label']) ? $type['label'] : _('Set email type');
    $emailTypeHelpText = !empty($type['helpText']) ? $type['helpText'] : _('Type of email');
    $emailTypeDefaultValue = !empty($type['defaultValue']) ? $type['defaultValue'] : "";
    $emailTypeHasError = !empty($type['hasError']) && $type['hasError'] ? 'has-error' : "";
    $displayEmailType = !empty($type['display']) && $type['display'] ? true : false;

    $subjectLabel = !empty($subject['label']) ? $subject['label'] : _('Set email Subject');
    $subjectHelpText = !empty($subject['helpText']) ? $subject['helpText'] : _('Email Subject');
    $subjectDefaultValue = !empty($subject['defaultValue']) ? $subject['defaultValue'] : "";
    $subjectHasError = !empty($subject['hasError']) && $subject['hasError'] ? 'has-error' : "";
    $displayEmailSubject = !empty($subject['display']) && $subject['display'] ? true : false;

    $bodyLabel = !empty($body['label']) ? $body['label'] : _('Set email body');
    $bodyHelpText = !empty($body['helpText']) ? $body['helpText'] : _('Email Body');
    $bodyDefaultValue = !empty($body['defaultValue']) ? $body['defaultValue'] : "";
    $bodyHasError = !empty($body['hasError']) && $body['hasError'] ? 'has-error' : "";
    $displayEmailBody = !empty($body['display']) && $body['display'] ? true : false;

?>

    <!-- Start: Email Type  -->
    <div class="element-container <?= $identifier . "EmailTypeWrapper" ?> <?= $emailTypeHasError ?>" style="<?= $displayEmailType ? '' : 'display:none' ?>">
        <div class="row">
            <div class="form-group">
                <div class="col-md-7">
                    <label class="control-label"><?php echo _($emailTypeLabel); ?></label>
                    <i class="fa fa-question-circle fpbx-help-icon" data-for="<?= $identifier . "EmailType" ?>"></i>&nbsp;
                </div>
                <div class="col-md-5 radioset text-right">
                    <input type="radio" id="<?= $identifier . "EmailType" ?>Html" name="<?= $identifier . "EmailType" ?>" onclick="handleEmailType('<?= $identifier ?>');" value="html" <?php echo $emailTypeDefaultValue == 'html' ? 'checked=""' : "" ?>>
                    <label for="<?= $identifier . "EmailType" ?>Html">HTML</label>
                    <input type="radio" id="<?= $identifier . "EmailType" ?>Text" name="<?= $identifier . "EmailType" ?>" onclick="handleEmailType('<?= $identifier ?>');" value="text" <?php echo $emailTypeDefaultValue == 'text' || $emailTypeDefaultValue == '' ? 'checked=""' : "" ?>>
                    <label for="<?= $identifier . "EmailType" ?>Text">Text</label>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12"><span id="<?= $identifier . "EmailType" ?>-help" class="help-block fpbx-help-block"><?php echo _($emailTypeHelpText) ?> </span> </div>
        </div>
    </div>
    <!-- End: Email Type  -->

    <!-- Start: Email Subject  -->
    <div class="element-container <?= $identifier . "EmailSubjectWrapper" ?> <?= $subjectHasError ?>" style="<?= $displayEmailSubject ? '' : 'display:none' ?>">
        <div class=" row">
            <div class="form-group">
                <div class="col-md-3">
                    <label class="control-label"><?php echo _($subjectLabel); ?></label>
                    <i class="fa fa-question-circle fpbx-help-icon" data-for="<?= $identifier . 'EmailSubject'; ?>"></i>&nbsp;
                </div>
                <div class="col-md-9">
                    <input type="text" class="form-control" id="<?= $identifier . 'EmailSubject'; ?>" name="<?= $identifier . 'EmailSubject'; ?>" value=" <?php echo $subjectDefaultValue ?>">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12"><span id="<?= $identifier . 'EmailSubject-help'; ?>" class="help-block fpbx-help-block"><?php echo _($subjectHelpText) ?> </span> </div>
        </div>
    </div>
    <!-- End: Email Subject  -->

    <!-- Start: Email Body  -->
    <div class="element-container <?= $identifier . "EmailBodyWrapper" ?> <?= $bodyHasError ?>" style="<?= $displayEmailBody ? '' : 'display:none' ?>">
        <div class=" row">
            <div class="form-group">
                <div class="col-md-3">
                    <label class="control-label" for="<?= $identifier . 'EmailBody'; ?>"><?php echo _($bodyLabel); ?></label>
                    <i class="fa fa-question-circle fpbx-help-icon" data-for="<?= $identifier . 'EmailBody'; ?>"></i>&nbsp;
                </div>
                <div class="col-md-9">
                    <textarea class="form-control" id="<?= $identifier . 'TextEmailBody' ?>" name="<?= $identifier . 'TextEmailBody' ?>" rows="15" cols="80" spellcheck="false" style="<?php echo $emailTypeDefaultValue == 'html' ? 'display:none;' : "" ?>"><?php echo preg_replace('#<br\s*/?>#i', "", $bodyDefaultValue); ?></textarea>
                    <textarea id="<?= $identifier . 'HtmlEmailBody' ?>" style="<?php echo $emailTypeDefaultValue == 'text' ? 'display:none;' : "" ?>"></textarea>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <span id="<?= $identifier . 'EmailBody-help'; ?>" class="help-block fpbx-help-block">
                    <?= _($bodyHelpText); ?>
                </span>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                let id = `#menuBarDiv_<?= $identifier . 'HtmlEmailBody' ?>`;
                $(`#<?= $identifier . 'HtmlEmailBody' ?>`).Editor({
                    'insert_table': false,
                    'select_all': false,
                    'togglescreen': false,
                    'insert_img': false
                });
                handleEmailType('<?= $identifier ?>')
                setTimeout(() => {
                    let content = `<?= html_entity_decode($bodyDefaultValue, ENT_QUOTES); ?>`;
                    $(id).siblings('.Editor-editor').html(content);
                }, 2000);
            });
        </script>
    </div>
    <!-- End: Email Body  -->

<?php
}
?>
<script>
    function handleEmailType(identifier) {
        let id = `#${identifier}TextEmailBody`;
        let emailType = $(`input[name="${identifier}EmailType"]:checked`).val();
        let htmlEditorID = `#menuBarDiv_${identifier}HtmlEmailBody`;
        if (emailType == 'html') {
            $(id).hide();
            $(htmlEditorID).parent('.Editor-container').show();
        } else {
            $(id).show();
            var htmlContent = $(id).text();
            var regex = /<br *\/?>/gi;
            $(id).html(htmlContent.replace(regex, "\n").replace(/<[^>]*>?/gm, ''));
            $(htmlEditorID).parent('.Editor-container').hide();
        }
    }
</script>