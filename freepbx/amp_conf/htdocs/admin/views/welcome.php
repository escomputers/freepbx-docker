<?php

// This is the emergency fallback 'welcome' page. It shouldn't be seen.
global $db,$amp_conf;
echo "<h2>".sprintf(_("Welcome to %s"),$amp_conf['DASHBOARD_FREEPBX_BRAND'])."</h2>";
$notify = notifications::create($db);
$items = $notify->list_all(true);
if (count($items)) {
	$notify_names = array(
		NOTIFICATION_TYPE_CRITICAL => _('Critical'),
		NOTIFICATION_TYPE_SECURITY => _('Security'),
		NOTIFICATION_TYPE_UPDATE => _('Update'),
		NOTIFICATION_TYPE_ERROR => _('Error'),
		NOTIFICATION_TYPE_WARNING => _('Warning'),
		NOTIFICATION_TYPE_NOTICE => _('Notice'),
		NOTIFICATION_TYPE_SIGNATURE_UNSIGNED => _('Unsigned')
	);

	echo "<div class=\"warning\">";
	echo '<h3>Notifications:</h3>';
	echo '<ul>';
	foreach ($items as $item) {
		echo '<li><strong>'.$notify_names[ $item['level'] ].':</strong>&nbsp;'.$item['display_text'];
		if (!empty($item['extended_text'])) {
			if (isset($_GET['item']) && $_GET['item'] == $item['module'].'.'.$item['id']) {
				echo '<p>'.nl2br($item['extended_text']).'</p>';
			} else {
				$dis = isset($_GET['display']) ? addslashes($_GET['display']) : '';
				$link = '?display='.$dis.'&amp;item='.$item['module'].'.'.$item['id'];
				echo '&nbsp;&nbsp;<a href="'.$link.'"><i>more..</i></a>';
			}
		}
		echo '</td></li>';
	}
	echo '</ul></div>';
}

print  "<h3>"._("Fallback Welcome Page")."</h3>" ;
print "<p>";
print  _("If you're seeing this page, it means that your Dashboard module is disabled.");
print  _("This won't cause you any problems, but it does make your system a little harder to use.");
print  _("We strongly suggest that you re-enable your Dashboard module through one of the following links:");
print  "</p>";
print "<ul>";
print "  <li> <a href='config.php?display=modules'>"._("Module Admin")."</a>";
print "  <li> <a href='config.php?display=modules&type=tool&extdisplay=online'>".("Online Module Upgrades")."</a>";
print "</ul>";
echo "</p>";
printf( dgettext( "welcome page",
"There is also a community based <a href='%s' target='_new'>FreePBX Web Forum</a> where you can post
questions and search for answers for any problems you may be having."),
"http://community.freepbx.org"  );
echo "</p>\n";

print "<p>" . sprintf(_("We hope you enjoy using %s"),$amp_conf['DASHBOARD_FREEPBX_BRAND']) . "</p>\n";
