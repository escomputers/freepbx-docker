<?php
namespace FreePBX\Console\Command;
//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Povils\Figlet\Figlet;

class Motd extends Command {
	private $errors = array();
	private $banner = array(
		"font" => "doom",
		"color" => "green",
		"background" => "black",
		"text" => "FreePBX"
	);
	private $supporturl = 'http://www.freepbx.org/support-and-professional-services';

	protected function configure(){
		$this->setName('motd')
		->setDescription(_('Prints MOTD'))
		->setDefinition(array(
			new InputArgument('args', InputArgument::IS_ARRAY, null, null),));

		$this->banner['text'] = \FreePBX::Config()->get('DASHBOARD_FREEPBX_BRAND');
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$this->updateVars();
		$edgemode = \FreePBX::Config()->get('MODULEADMINEDGE');
		$alerts = count(\FreePBX::Notifications()->list_all());
		if(is_array($this->banner)) {
			//http://www.figlet.org/examples.html
			$font = !empty($this->banner['font']) ? $this->banner['font'] : "doom";
			$color = !empty($this->banner['color']) ? $this->banner['color'] : "green";
			$background = !empty($this->banner['background']) ? $this->banner['background'] : "black";
			$text = !empty($this->banner['text']) ? $this->banner['text'] : "FreePBX";

			$figlet = new Figlet();
			$banner = $figlet
						->setFont($font)
						->setFontColor($color)
						->setBackgroundColor($background)
						->setFontStretching(0)
						->render($text);

			$output->write($banner);
		} else {
			$output->write(base64_decode($this->banner));
		}
		if($alerts != 0) {
			$output->writeln("<fg=red>".sprintf(_("NOTICE! You have %s notifications! Please log into the UI to see them!"), $alerts)."</fg=red>");
		}
		if($edgemode == 1){
			$output->writeln("<fg=red>".sprintf(_("NOTICE! This system has EDGE mode enabled. For more information visit %s"), 'http://wiki.freepbx.org/x/boi3Aw')."</fg=red>");
		}
		$output->writeln("<info>"._("Current Network Configuration")."</info>");
		$iflist = $this->listIFS();
		if($iflist){
			$rows = array();
			foreach($iflist as $if => $info){
				$rows[] = array($if,$info['mac'],$info['ip']);
			}
			$table = new Table($output);
			$table
				->setHeaders(array(_('Interface'), _('MAC Address'), _('IP Addresses')))
				->setRows($rows);
			$table->render();
		}else{
			$output->writeln("-------------------");
			$output->writeln(_("No interfaces found"));
			$output->writeln("-------------------");
		}

		$messages = $this->externalMessages();
		if (isset($messages['pre'])) {
			foreach($messages['pre'] as $o) {
				$output->writeln($o);
			}
		}

		if (!$messages['cancel']) {
			$output->writeln("");
			$output->writeln(_("Please note most tasks should be handled through the GUI."));
			$output->writeln(_("You can access the GUI by typing one of the above IPs in to your web browser."));
			$output->writeln(_("For support please visit: "));
			$output->writeln("    ".$this->supporturl);
			$output->writeln("");
		}

		if (isset($messages['post'])) {
			foreach($messages['post'] as $o) {
				$output->writeln($o);
			}
		}

	}
	private function listIFS(){
		$iflist = array();
		$ifs = scandir('/sys/class/net/');
		foreach($ifs as $if){
			if($if == '.' || $if == '..' || !is_dir("/sys/class/net/$if")) {
				continue;
			}
			$iftype = file_get_contents('/sys/class/net/' . $if . '/type');
			if($iftype != 1){
				continue;
			}
			$MAC = trim(file_get_contents('/sys/class/net/' . $if . '/address'));
			$MAC = strtoupper($MAC);
			$ipv6 = trim(shell_exec("/sbin/ip -o addr show " . $if ." | grep -Po 'inet6 \K[\da-f:]+'"));
			$ipv4 = trim(shell_exec("/sbin/ip -o addr show " . $if ." | grep -Po 'inet \K[\d.]+'"));
			// If this interface has an ipv4 address AND an ipv6 address,
			// display them both
			if ($ipv4 && $ipv6) {
				$ipstr = "$ipv4\n$ipv6";
			} else {
				$ipstr = $ipv4 ? $ipv4 : $ipv6;
			}
			$iflist[$if] = array('mac' => $MAC, 'ip' => $ipstr);
		}
		return $iflist;
	}
	public function updateVars(){
		$hooks = \FreePBX::Hooks()->processHooks();
		foreach($hooks as $hook){
			if(is_array($hook)){
				foreach($hook as $k => $v ){
					if(isset($this->$k)){
						$this->$k = $v;
					}
				}
			}
		}
	}
	public function externalMessages(){
		$ret = array('pre' => array(), 'post' => array(), 'cancel' => false);
		$hooks = \FreePBX::Hooks()->processHooks();
		foreach($hooks as $hook){
			if(is_array($hook)){
				if (isset($hook['pre'])) {
					if (is_array($hook['pre'])) {
						foreach ($hook['pre'] as $o) {
							$ret['pre'][] = $o;
						}
					} else {
						$ret['pre'][] = $hook['pre'];
					}
				}
				if (isset($hook['post'])) {
					if (is_array($hook['post'])) {
						foreach ($hook['post'] as $o) {
							$ret['post'][] = $o;
						}
					} else {
						$ret['post'][] = $hook['post'];
					}
				}

				if (isset($hook['cancel'])) {
					$ret['cancel'] = true;
				}
			}
		}
		return $ret;
	}
}