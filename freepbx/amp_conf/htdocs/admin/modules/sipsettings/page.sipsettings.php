<?php
// vim: set ai ts=4 sw=4 ft=php:

// SipSettings page. Re-written for usage with chan_sip and chan_pjsip
// AGPL v3 Licened

// Note that BEFORE THIS IS CALLED, the Sipsettings configPageinit
// function is called. This is where you do any changes. The page.foo.php
// is only for DISPLAYING things.  MVC is a cool idea, ya know?
//
$ss = FreePBX::create()->Sipsettings;
?>
<form autocomplete="off" action="" method="post" class="fpbx-submit" id="sipconfig" name="sipconfig" >
	<div class="container-fluid">
		<h1><?php echo _("SIP Settings")?></h1>
		<div class="panel panel-info">
                  <div class="panel-heading">
                    <div class="panel-title">
			  <a href="#" data-toggle="collapse" data-target="#moreinfo">
                            <i class="glyphicon glyphicon-info-sign"></i>
                          </a>&nbsp;&nbsp;&nbsp;<?php echo _("SIP driver informations")?>
		    </div>
                  </div>
                  <div class="panel-body collapse" id="moreinfo">
			<p><?php echo _($ss->getActiveModules())?></p>
		  </div>
               </div>
		<div class="row">
			<div class="col-sm-12">
				<div class="fpbx-container">
					<div class="display no-border">
						<div class="nav-container">
							<div class="scroller scroller-left"><i class="glyphicon glyphicon-chevron-left"></i></div>
							<div class="scroller scroller-right"><i class="glyphicon glyphicon-chevron-right"></i></div>
							<div class="wrapper">
								<ul class="nav nav-tabs list" role="tablist">
									<?php foreach($ss->myShowPage() as $key => $page) { ?>
										<li data-name="<?php echo $key?>" class="change-tab <?php echo $key == 'general' ? 'active' : ''?>"><a href="#<?php echo $key?>" aria-controls="<?php echo $key?>" role="tab" data-toggle="tab"><?php echo $page['name']?></a></li>
									<?php } ?>
								</ul>
							</div>
						</div>
						<div class="tab-content display">
							<?php foreach($ss->myShowPage() as $key => $page) { ?>
								<div id="<?php echo $key?>" class="tab-pane <?php echo $key == 'general' ? 'active' : ''?>">
									<?php echo $page['content']?>
								</div>
							<?php } ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<p><br><br></p>
	</div>
</form>

