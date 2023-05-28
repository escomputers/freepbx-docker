<?php
/* core_migrate.php
 * migrate logger.conf to core with new conifg to allow includes, keep current configuration in custom files 
 *
 */

global $amp_conf;

$old_logger_file = $amp_conf['ASTETCDIR'] . '/logger.conf';

$custom_logger_general = $amp_conf['ASTETCDIR'] . '/logger_general_custom.conf';
$custom_logger_logfiles = $amp_conf['ASTETCDIR'] . '/logger_logfiles_custom.conf';

$additional_logger_general = $amp_conf['ASTETCDIR'] . '/logger_general_additional.conf';
$additional_logger_logfiles = $amp_conf['ASTETCDIR'] . '/logger_logfiles_additional.conf';

if (is_file($old_logger_file) && !is_link($old_logger_file)) {
  out(sprintf(_("migrating %s settings so core can generate"),basename($old_logger_file)));
  out(sprintf(_("existing settings will be put in %s and %s"),basename($custom_logger_general),basename($custom_logger_logfiles)));

  $init = '';
  $section = 'init';
  $general = '';
  $logfiles = '';
  $error = false;
  $logger = file($old_logger_file);

  if ($logger === false) {
    out(sprintf(_("failed reading %s, file not being removed"),basename($old_logger_file)));
    $error = true;
  } else {
    foreach ($logger as $line) {
      switch ($section) {
      case 'init':
        $cline = trim($line);
        if (substr($cline,0,9) == '[general]') {
          $section = 'general';
        } elseif (substr($cline,0,10) == '[logfiles]') {
          $section = 'logfiles';
        } else {
          $init .= $line;
        }
        break;
      case 'general':
        $cline = substr(trim($line),0,10);
        if ($cline != '[logfiles]') {
          $general .= $line;
        } else {
          $section = 'logfiles';
        }
        break;
      case 'logfiles':
        $logfiles .= $line;
        break;
      }
    }
  }
}

if (file_put_contents($custom_logger_general,$general) === false) {
  $error = true;
}
if (file_put_contents($custom_logger_logfiles,$logfiles) === false) {
  $error = true;
}
touch($additional_logger_general);
touch($additional_logger_logfiles);

if (!$error) {
  if (is_file($old_logger_file) && !is_link($old_logger_file)) {
    if (unlink($old_logger_file) === false) {
      $error = true;
      out(sprintf(_("Could not delete %s"),basename($old_logger_file)));
    } else {
      out(_("logger file removed, core upgrade will provide new file"));
    }
  } else {
    out(_("no logger file to remove"));
  }
}
if ($error) {
  out(_("errors occured listed above"));
} else {
  out(_("apply configuration settings after new core module loaded"));
}
