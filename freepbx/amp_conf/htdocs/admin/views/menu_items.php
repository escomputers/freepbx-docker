<?php
// If freepbx_menu.conf exists then use it to define/redefine categories
//
if ($amp_conf['USE_FREEPBX_MENU_CONF']) {
	$fd = $amp_conf['ASTETCDIR'].'/freepbx_menu.conf';
	if (file_exists($fd)) {
		$favorites = @parse_ini_file($fd,true);
		if ($favorites !== false) foreach ($favorites as $menuitem => $setting) {
			if (isset($fpbx_menu[$menuitem])) {
				foreach($setting as $key => $value) {
					switch ($key) {
						case 'category':
						case 'name':
							$fpbx_menu[$menuitem][$key] = htmlspecialchars($value);
						break;
						case 'type':
							// this is really deprecated but ???
							if (strtolower($value)=='setup' || strtolower($value)=='tool') {
								$fpbx_menu[$menuitem][$key] = strtolower($value);
							}
						break;
						case 'sort':
							if (is_numeric($value) && $value > -10 && $value < 10) {
								$fpbx_menu[$menuitem][$key] = $value;
							}
						break;
						case 'remove':
							// parse_ini_file sets all forms of yes/true to 1 and no/false to nothing
							if ($value == '1') {
								unset($fpbx_menu[$menuitem]);
							}
							break;
						case 'display_on_auth':
								$fpbx_menu[$menuitem][$key] = strtolower($value);
						break;
					}
				}
			}
		} else {
			freepbx_log('FPBX_LOG_ERROR', _("Syntax error in your freepbx_menu.conf file"));
		}
	}
}

//For Localization pickup
if(false) {
  _("Admin");
  _("Applications");
  _("Connectivity");
  _("Reports");
  _("Settings");
  _("User Panel");
  _("Other");
}
$connected = FreePBX::create()->astman->connected();
if (isset($fpbx_menu) && is_array($fpbx_menu)) {	// && freepbx_menu.conf not defined
	$out = null;
	if (empty($favorites)) foreach ($fpbx_menu as $mod => $deets) {
		$menu[strtolower($deets['category'])][] = $deets;
	} else {
		foreach ($fpbx_menu as $mod => $deets) {
			$menu[$deets['category']][] = $deets;
		}
	}

	$count = 0;
	$menu = is_array($menu) ? $menu : array();
	$mods = array();
	foreach($menu as $t => $cat) { //categories
		if (count($cat) == 1) {
			if ((isset($cat[0]['display_on_auth']) && $cat[0]['display_on_auth'] == '1') && 
			    (isset($display) && ($display == 'noauth'))) {
				continue;
			}
			if (isset($cat[0]['hidden']) && $cat[0]['hidden'] == 'true') {
				continue;
			}
			$href = isset($cat[0]['href']) ? $cat[0]['href'] : 'config.php?display=' . $cat[0]['display'];
			$target = isset($cat[0]['target']) ? ' target="' . $cat[0]['target'] . '"'  : '';
			$class = $cat[0]['display'] == $display ? 'class="ui-state-highlight"' : '';
			$mods[$t] = '<li><a href="' . $href . '" ' . $target . $class . '>' . modgettext::_(ucwords($cat[0]['name']),$cat[0]['module']['rawname']) . '</a></li>';
			continue;
		}

		//Reverse lookup here, first look in amp, then the module, then amp again.
		//This allows us to check special modules that are not defined in Framework
		$catname = _(ucwords($t));
		$catname = ($catname != ucwords($t)) ? $catname : modgettext::_(ucwords($t),$cat[0]['module']['rawname']);
		$mods[$t] = '<li class="dropdown">
			<a href="#" class="dropdown-toggle" data-toggle="dropdown">' . $catname . '</a>
			<ul class="dropdown-menu" role="menu">';

		$cat = is_array($cat) ? $cat : array();
		$items = array();
		foreach ($cat as $c => $mod) { //modules
			if (isset($mod['hidden']) && $mod['hidden'] == 'true') {
				continue;
			}
			//remove administrators, makes no sense in these modes
			if($mod['display'] == "ampusers" &&  in_array(FreePBX::Config()->get('AUTHTYPE'), array("none"))) {
				continue;
			}
			$classes = array();

			//build default module url
			$href = isset($mod['href'])
					? $mod['href']
					: "config.php?display=" . $mod['display'];

			$target = isset($mod['target'])
					? ' target="' . $mod['target'] . '" '  : '';

			$active = !empty($mod['display']) && !empty($_REQUEST['display']) && ($mod['display'] == $_REQUEST['display']) ? 'active' : '';
			// try the module's translation domain first
			if (isset($mod['disabled']) && $mod['disabled']) {
				$items[$mod['name']] = '<li><a'
						. ' class="disabled ' . implode(' ', $classes) . ' '.$active.'">'
						. modgettext::_($mod['name'], $mod['module']['rawname'])
						. '</a></li>';
			} else {
				$items[$mod['name']] = '<li><a href="' . $href . '"'
						. $target
						. ' class="' . implode(' ', $classes) . ' '.$active.'">'
						. modgettext::_($mod['name'], $mod['module']['rawname'])
						. '</a></li>';
			}
			$_item_sort[$mod['name']] = $mod['sort'];
		}
		uksort($items,'_item_sort');
		$mods[$t] .= implode($items) . '</ul>';
		unset($items);
		unset($_item_sort);
	}
	uksort($mods,'_menu_sort');
	$out .= implode($mods);

	echo $out;
}

// key sort but keep Favorites on the far left, Other on the far right
//
function _menu_sort($a, $b) {
	$a = strtolower($a);
	$b = strtolower($b);
	if ($a == 'favorites')
		return false;
	else if ($b == 'favorites')
		return true;
	else if ($a == 'other')
		return true;
	else if ($b == 'other')
		return false;
	else
		return $a > $b;
}

function _item_sort($a, $b) {
	global $_item_sort;

	if (!empty($_item_sort[$a]) && !empty($_item_sort[$a]) && $_item_sort[$a] != $_item_sort[$b])
		return $_item_sort[$a] > $_item_sort[$b];
	else
		return $a > $b;
}
