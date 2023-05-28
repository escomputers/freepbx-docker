<?php
$c = FreePBX::Config();

$ena = _("Enabled");
$dis = _("Disabled");
$emo = _("Email Only");

$um = new \FreePBX\Builtin\UpdateManager();
$settings = $um->getCurrentUpdateSettings(false);

extract($settings);

$sysUpdate = new \FreePBX\Builtin\SystemUpdates();
$sysUpdateDisabled = '';
if($sysUpdate->canDoSystemUpdates()) {
	$asue = ($auto_system_updates=="enabled")?"checked":"";
	$asud = ($auto_system_updates=="disabled")?"checked":"";
	$asuemail = ($auto_system_updates=="emailonly")?"checked":"";
} else {
	$asue = "";
	$asud = "checked";
	$asuemail = "";
	$sysUpdateDisabled = 'disabled';
}

// Auto module updates radio buttons
$amue = ($auto_module_updates=="enabled")?"checked":"";
$amud = ($auto_module_updates=="disabled")?"checked":"";
$amuemail = ($auto_module_updates=="emailonly")?"checked":"";

// Auto module security updates radio buttons
$amsue = ($auto_module_security_updates=="enabled")?"checked":"";
$amsud = ($auto_module_security_updates=="disabled")?"checked":"";
$amsuemail = ($auto_module_security_updates=="emailonly")?"checked":"";

$use = ($unsigned_module_emails=="enabled")?"checked":"";
$usd = ($unsigned_module_emails=="disabled")?"checked":"";

$welcome = sprintf(_('Welcome to %s!'),$c->get("BRAND_TITLE"));
$is = _("Initial Setup");
$cc = _('Please provide the core settings that will be used to administer and update your system');
$er = _('Please correct the following errors:');
$un = array(_('Username'), _('Admin user name'));
$pw = array(_('Password'), _('Admin password'), _('Confirm Password'));
$em = array(_('Notifications Email address'), _('Email Address'));
$si = array(_('System Identifier'), _('System Identifier'));

if (!isset($username)) {
	$username = "";
}

if (!isset($email)) {
	$email = "";
}

if (!isset($system_ident)) {
	$system_ident = "";
}
?>

<form method='post' id='loginform'>
<input type='hidden' name='action' value='setup_admin'>

<div class='container-fluid'>
  <div class='panel panel-default'>
    <div class='panel-heading'><?php echo $welcome; ?></div>
    <div class='panel-body'>
      <div class='row'>
        <div class='col-sm-12'>
          <h2 class="text-center"><?php echo $is; ?></h2>
          <p><?php echo $cc; ?></p>
        </div>
      </div>
<?php if (isset($errors)) { ?>
      <div class='row'>
        <div class='col-sm-12'>
          <span class="obe_error"><?php echo $er.ul($errors); ?></span>
        </div>
      </div>
<?php } ?>
      <h3 class="text-center"><?php echo _("Administrator User")?></h3>
      <div class='row'>
        <div class='form-group'>
          <div class='col-sm-3'>
            <label for="username" style='margin-top: 1em'><?php echo $un[0];?></label>
          </div>
          <div class='col-sm-8'>
            <input type='text' class='form-control' id='username' name='username' value='<?php echo $username; ?>' placeholder='<?php echo $un[1]; ?>' autofocus>
          </div>
        </div>
      </div>
      <div class='row'>
        <div class='form-group'>
          <div class='col-sm-3'>
            <label for="password1" style='margin-top: 1em'><?php echo $pw[0];?></label>
          </div>
          <div class='col-sm-8'>
            <input type='password' class='form-control password-meter' id='password1' name='password1' placeholder='<?php echo $pw[1]; ?>'>
          </div>
        </div>
      </div>
      <div class='row'>
        <div class='form-group'>
          <div class='col-sm-3'>
            <label for="password2" style='margin-top: 1em'><?php echo $pw[2];?></label>
          </div>
          <div class='col-sm-8'>
            <input type='password' class='form-control' id='password2' name='password2' placeholder='<?php echo $pw[1]; ?>'>
          </div>
        </div>
      </div>
      <h3 class="text-center"><?php echo _("System Notifcations Email")?></h3>
      <div class='row'>
        <div class='form-group'>
          <div class='col-sm-3'>
            <label for="email" style='margin-top: 1em'><?php echo $em[0];?></label>
          </div>
          <div class='col-sm-8'>
            <input type='text' class='form-control' id='email' name='email' placeholder='<?php echo $em[1]; ?>' value='<?php echo $email; ?>'>
          </div>
        </div>
      </div>
      <h3 class="text-center"><?php echo _("System Identification")?></h3>
      <div class='row'>
        <div class='form-group'>
          <div class='col-sm-3'>
            <label for="system_ident" style='margin-top: 1em'><?php echo $si[0];?></label>
          </div>
          <div class='col-sm-8'>
            <input type='text' class='form-control' id='system_ident' name='system_ident' placeholder='<?php echo $si[1]; ?>' value='<?php echo $system_ident; ?>'>
          </div>
        </div>
      </div>
      <h3 class="text-center"><?php echo _("System Updates")?></h3>
      <?php if($sysUpdate->canDoSystemUpdates()) { ?>
        <div class='row'>
          <div class='form-group'>
            <div class='col-sm-3'>
              <label for="auto_system_updates" style='margin-top: 1em'><?php echo _("Automatic System Updates"); ?></label>
            </div>
            <div class='col-sm-8'>
              <span class='radioset'>
                <input type='radio' name='auto_system_updates' id='asyse' <?php echo $asue; ?> value='enabled' <?php echo $sysUpdateDisabled?>>
                <label for='asyse'><?php echo $ena; ?></label>
                <input type='radio' name='auto_system_updates' id='asysemail' <?php echo $asuemail; ?> value='emailonly' <?php echo $sysUpdateDisabled?>>
                <label for='asysemail'><?php echo $emo; ?></label>
                <input type='radio' name='auto_system_updates' id='asysd' <?php echo $asud; ?> value='disabled' <?php echo $sysUpdateDisabled?>>
                <label for='asysd'><?php echo $dis; ?></label>
            </span>
            </div>
          </div>
        </div>
      <?php } ?>
      <div class='row'>
        <div class='form-group'>
          <div class='col-sm-3'>
            <label for="auto_module_updates" style='margin-top: 1em'><?php echo _("Automatic Module Updates"); ?></label>
          </div>
          <div class='col-sm-8'>
            <span class='radioset'>
              <input type='radio' name='auto_module_updates' id='amode' <?php echo $amue; ?> value='enabled'>
              <label for='amode'><?php echo $ena; ?></label>
              <input type='radio' name='auto_module_updates' id='amodemail' <?php echo $amuemail; ?> value='emailonly'>
              <label for='amodemail'><?php echo $emo; ?></label>
              <input type='radio' name='auto_module_updates' id='amodd' <?php echo $amud; ?> value='disabled'>
              <label for='amodd'><?php echo $dis; ?></label>
          </span>
          </div>
        </div>
      </div>
      <div class='row'>
        <div class='form-group'>
          <div class='col-sm-3'>
            <label for="auto_module_security_updates" style='margin-top: 1em'><?php echo _("Automatic Module Security Updates"); ?></label>
          </div>
          <div class='col-sm-8'>
            <span class='radioset'>
              <input type='radio' name='auto_module_security_updates' id='asmode' <?php echo $amsue; ?> value='enabled'>
              <label for='asmode'><?php echo $ena; ?></label>
              <input type='radio' name='auto_module_security_updates' id='asmodemail' <?php echo $amsuemail; ?> value='emailonly'>
              <label for='asmodemail'><?php echo $emo; ?></label>
            </span>
          </div>
        </div>
      </div>
      <div class='row'>
        <div class='form-group'>
          <div class='col-sm-3'>
            <label for="unsigned_module_emails" style='margin-top: 1em'><?php echo _("Send Security Emails For Unsigned Modules"); ?></label>
          </div>
          <div class='col-sm-8'>
            <span class='radioset'>
              <input type='radio' name='unsigned_module_emails' id='use' <?php echo $use; ?> value='enabled'>
              <label for='use'><?php echo $ena; ?></label>
              <input type='radio' name='unsigned_module_emails' id='usd' <?php echo $usd; ?> value='disabled'>
              <label for='usd'><?php echo $dis; ?></label>
            </span>
          </div>
        </div>
      </div>
      <div class='row'>
        <div class='form-group'>
          <div class='col-sm-3'>
            <label for="unsigned_module_emails" style='margin-top: 1em'><?php echo _("Check for Updates every"); ?></label>
          </div>
          <div class='col-xs-7 col-sm-3'>
            <select class='form-control' id='update_every' name='update_every'>
              <?php
              $days = [
                "day" => _("Day"),
                "monday" => _("Monday"),
                "tuesday" => _("Tuesday"),
                "wednesday" => _("Wednesday"),
                "thursday" => _("Thursday"),
                "friday" => _("Friday"),
                "saturday" => _("Saturday"),
                "sunday" => _("Sunday"),
              ];
              foreach ($days as $v => $desc) {
                if ($v === $update_every) {
                  echo "<option value='$v' selected>$desc</option>";
                } else {
                  echo "<option value='$v'>$desc</option>";
                }
              }

              ?>
            </select>
          </div>
          <div class='col-sm-5 col-md-6'>
            <select class='form-control' id='update_period' name='update_period'>
              <?php
              $times = [
                "0to4" => _("Between Midnight and 4am"),
                "4to8" => _("Between 4am and 8am"),
                "8to12" => _("Between 8am and 12pm"),
                "12to16" => _("Between 12pm and 4pm"),
                "16to20" => _("Between 4pm and 8pm"),
                "20to0" => _("Between 8pm and Midnight"),
              ];

              foreach ($times as $v => $desc) {
                if ($v === $update_period) {
                  echo "<option value='$v' selected>$desc</option>";
                } else {
                  echo "<option value='$v'>$desc</option>";
                }
              }
              ?>
            </select>
          </div>
        </div>
      </div>
    </div>
      <div class='row'>
        <div class='col-sm-3 col-sm-offset-9'><div class='pull-right'><button class='btn btn-default' type='submit' id='createacct'><?php echo _('Setup System')?></button></div></div>
      </div>
    </div>
  </div>
</div>

</form>

<script type='text/javascript'>
$(document).ready(function(){
	var emailregexp = /[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?/;
	var alertcol = "rgb(255, 192, 192)";
	var origcol = $("#email").css("background-color");
	$("#createacct").click(function(e) {
		var canProceed = true;

		// Check the admin user name. No spaces.
		var un = $("#username").val().replace(/\s/g, "");
		$("#username").val(un);
		if (!un.length) {
			$("#username").css("background-color", alertcol);
			canProceed = false;
		} else {
			$("#username").css("background-color", origcol);
		}

		// Validate the email address
		if (!emailregexp.test($("#email").val())) {
			$("#email").css("background-color", alertcol);
			canProceed = false;
		} else {
			$("#email").css("background-color", origcol);
		}

		// Check the passwords.
		if ($("#password1").val().length < 4) {
			$("#password1").css("background-color", alertcol);
			$("#password2").css("background-color", origcol);
			canProceed = false;
		} else if ($("#password1").val() != $("#password2").val()) {
			$("#password1").css("background-color", origcol);
			$("#password2").css("background-color", alertcol);
			canProceed = false;
		} else {
			$("#password1").css("background-color", origcol);
			$("#password2").css("background-color", origcol);
    }

    //Check system ident
    if($("#system_ident").val().length <= 0) {
      $("#system_ident").css("background-color", alertcol);
			canProceed = false;
		} else {
			$("#system_ident").css("background-color", origcol);
		}

		if (!canProceed) {
			e.preventDefault();
			return false;
		} else {
			return true;
		}
	 });
});
</script>

