<?php
global $amp_conf;
global $_item_sort;
?>
<div class="freepbx-navbar">
	<nav class="navbar navbar-default navbar-fixed-top" role="navigation">
		<div class="container-fluid">
			<div class="navbar-header">
				<button type="button" class="navbar-toggle navbar-left collapsed" data-toggle="collapse" data-target="#fpbx-menu-collapse">
					<span class="sr-only"><?php echo _("Toggle navigation")?></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
				<a class="navbar-brand" href="#">
					<img src="<?php echo $amp_conf['BRAND_IMAGE_TANGO_LEFT']; ?>" alt="<?php echo $amp_conf['BRAND_FREEPBX_ALT_LEFT']; ?>" title="<?php echo $amp_conf['BRAND_FREEPBX_ALT_LEFT']; ?>" id="MENU_BRAND_IMAGE_TANGO_LEFT" data-BRAND_IMAGE_FREEPBX_LINK_LEFT="<?php echo $amp_conf['BRAND_IMAGE_FREEPBX_LINK_LEFT']; ?>" />
				</a>
			</div>
			<div class="collapse navbar-collapse" id="fpbx-menu-collapse">
				<ul class="nav navbar-nav navbar-left">
					<?php include_once(__DIR__ . '/menu_items.php'); ?>
				</ul>
			</div>
			<ul class="stuck-right">
				<?php if(FreePBX::create()->astman->connected()) {?>
					<li><a id="button_reload" class="btn btn-danger nav-button reload-btn"><?php echo _('Apply Config'); ?></a></li>
				<?php } else { ?>
					<li><a class="btn btn-danger nav-button reload-btn"><?php echo _('Cannot Connect to Asterisk'); ?></a></li>
				<?php } ?>
				<?php if ( isset($_SESSION['AMP_user']) ) { ?>
					<?php if($amp_conf['SHOWLANGUAGE']) { ?>
						<li class="dropdown admin-btn">
							<button class="btn dropdown-toggle nav-button" data-toggle="dropdown"><i class="fa fa-language"></i></button>
							<ul id="fpbx_lang" class="dropdown-menu" role="menu">
								<?php foreach ($language as $langKey => $lang) {
										$class = '';
										if ($langKey === $_COOKIE['lang']) {
											$class = 'class="disabled"';
										} ?>
									<li <?php echo $class; ?> data-lang="<?php echo $langKey; ?>"><a href="#"><?php echo $lang; ?></a></li>
								<?php } ?>
							</ul>
						</li>
					<?php } ?>
					<button id="search-btn" class="btn nav-button"><i class="fa fa-search"></i></button>
					<?php if($authtype != 'none') {?>
						<li class="dropdown admin-btn">
							<button class="btn dropdown-toggle nav-button" data-toggle="dropdown"><i id="settings-cog" class="fa fa-cog"></i></button>
							<ul class="dropdown-menu" role="menu">
								<li role="presentation" ><a><?php echo _('Hello, ') . (isset($_SESSION['AMP_user']->username) ? $_SESSION['AMP_user']->username : 'ERROR'); ?></a></li>
								<li role="presentation" class="divider"></li>
								<li role="presentation" class="nav-bar-show"><a title="<?php echo _("Nav. Bar is always shown by default or not.") ?>"><?php echo _("Keep Nav Bar Expanded") ?></a></li>
								<li><a id="user_logout" href="#"><?php echo _('Logout'); ?></a></li>
							</ul>
						</li>
					<?php } ?>
				<?php } ?>
			</ul>
		</div>
	</nav>
</div>
<?php if (isset($display) && $display != "noauth") { ?>
	<div class="<?php echo empty($_COOKIE['searchHide']) ? 'in' : ''?>" id='fpbxsearch'>
		<i class="fa fa-search"></i>
		<input type="text" class="form-control typeahead" placeholder="<?php echo _('Search')?>" title="<?php echo _("Quick Search '/'")?>">
	</div>
<?php } ?>
<div id="page_body">
