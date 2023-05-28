<?php
namespace FreePBX\Console\Application;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputDefinition;

use Povils\Figlet\Figlet;
/**
* ListCommand displays the list of all available commands for the application.
*
* @author Fabien Potencier <fabien@symfony.com>
*/
class FWListCommand extends Command {

	private $banner = array(
		"font" => "doom",
		"color" => "green",
		"background" => "black",
		"text" => "FreePBX"
	);

	protected function configure() {
		$this
		->setName('list')
		->setDefinition($this->createDefinition())
		->setDescription('Lists commands')
		->setHelp(<<<EOF
The <info>%command.name%</info> command lists all commands:

	<info>php %command.full_name%</info>

You can also display the commands for a specific namespace:

	<info>php %command.full_name% test</info>

You can also output the information in other formats by using the <comment>--format</comment> option:

	<info>php %command.full_name% --format=xml</info>

It's also possible to get raw list of commands (useful for embedding command runner):

	<info>php %command.full_name% --raw</info>
EOF
			)
		;
		$this->banner['text'] = \FreePBX::Config()->get('DASHBOARD_FREEPBX_BRAND');
	}

	public function getNativeDefinition() {
		return $this->createDefinition();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
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

		if ($input->getOption('xml')) {
			$input->setOption('format', 'xml');
		}

		$helper = new DescriptorHelper();
		$helper->describe($output, $this->getApplication(), array(
			'format' => $input->getOption('format'),
			'raw_text' => $input->getOption('raw'),
			'namespace' => $input->getArgument('namespace'),
		));
	}

	private function createDefinition()
	{
		return new InputDefinition(array(
			new InputArgument('namespace', InputArgument::OPTIONAL, 'The namespace name'),
			new InputOption('xml', null, InputOption::VALUE_NONE, 'To output list as XML'),
			new InputOption('raw', null, InputOption::VALUE_NONE, 'To output raw command list'),
			new InputOption('format', null, InputOption::VALUE_REQUIRED, 'To output list in other formats', 'txt'),
		));
	}
}
