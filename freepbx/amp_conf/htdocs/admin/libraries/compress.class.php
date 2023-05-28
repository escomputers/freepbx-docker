<?php
class compress {
	//We keep this outside of everything to prevent the numerous issues that cropped up during the 2.9->2.10->2.11 cycles
	public static function web_files() {
		//TODO: do stuff here for brand css changes since we are all LESS now
		/*
		global $amp_conf;
		$mainstyle_css      = $amp_conf['BRAND_CSS_ALT_MAINSTYLE']
							? $amp_conf['BRAND_CSS_ALT_MAINSTYLE']
							: 'assets/css/mainstyle.css';
		$wwwroot 			= $amp_conf['AMPWEBROOT'] . "/admin";
		$mainstyle_css_gen 	= $wwwroot . '/' . $amp_conf['mainstyle_css_generated'];
		$mainstyle_css		= $wwwroot . '/' . $mainstyle_css;
		$new_css 			= file_get_contents($mainstyle_css)
							. file_get_contents($wwwroot . '/' . $amp_conf['JQUERY_CSS']);
		$new_css 			= CssMin::minify($new_css);
		$new_md5 			= md5($new_css);
		$gen_md5 			= file_exists($mainstyle_css_gen) ? md5(file_get_contents($mainstyle_css_gen)) : '';


		//regenerate if hashes dont match
		if ($new_md5 != $gen_md5) {
			$ms_path = dirname($mainstyle_css);

			// it's important for filename tp unique
			//because that will force browsers to reload vs. caching it
			$mainstyle_css_generated = $ms_path.'/mstyle_autogen_' . time() . '.css';
			//remove any stale generated css files
			exec(fpbx_which('rm') . ' -f ' . $ms_path . '/mstyle_autogen_*');

			$ret = file_put_contents($mainstyle_css_generated, $new_css);

			// Now assuming we write something reasonable, we need to save the generated file name and mtimes so
			// next time through this ordeal, we see everything is setup and skip all of this.
			//
			// we skip this all this if we get back false or 0 (nothing written) in which case we will use the original
			// We need to set the value in addition to defining the setting
			//since if already defined the value won't be reset.
			if ($ret) {
				$freepbx_conf =& freepbx_conf::create();
				$val_update['mainstyle_css_generated'] = str_replace($wwwroot . '/', '', $mainstyle_css_generated);

				// Update the values (in case these are new) and commit
				$freepbx_conf->set_conf_values($val_update, true, true);


				// If it is a regular file (could have been first time and previous was blank then delete old
				if (is_file($mainstyle_css_gen) && !unlink($mainstyle_css_gen)) {
					freepbx_log(FPBX_LOG_WARNING,
								sprintf(_('failed to delete %s from assets/css directory after '
										. 'creating updated CSS file.'),
										$mainstyle_css_generated_full_path));
				}
			}
		}
		*/
	}
}
