<?php
namespace FreePBX\Console\Application;

use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Povils\Figlet\Figlet;
/**
* HelpCommand displays the help for a given command.
*
* @author Fabien Potencier <fabien@symfony.com>
*/
class FWHelpCommand extends HelpCommand {
	private $command;
	private $banner = array(
		"font" => "doom",
		"color" => "green",
		"background" => "black",
		"text" => "FreePBX"
	);

	public function setCommand(Command $command) {
		$this->command = $command;
	}
	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->banner['text'] = \FreePBX::Config()->get('DASHBOARD_FREEPBX_BRAND');
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
			//this is because trim by itself wont work!! :-|
			$lines = preg_split("/\n/m", $banner);
			$banner = '';
			foreach($lines as $l) {
				$l = trim($l);
				if(empty($l)) {
					continue;
				}
				$banner .= $l . "\n";
			}
			//end trim operation
			$output->write($banner);
		} else {
			$output->write(base64_decode($this->banner));
		}

		if (null === $this->command) {
			$this->command = $this->getApplication()->find($input->getArgument('command_name'));
		}
        	if ($input->hasOption('xml') && $input->getOption('xml')) {
			$input->setOption('format', 'xml');
		}
		$helper = new DescriptorHelper();
		$helper->describe($output, $this->command, array(
			'format' => $input->getOption('format'),
			'raw' => $input->getOption('raw'),
		));
		$this->command = null;
	}
}
