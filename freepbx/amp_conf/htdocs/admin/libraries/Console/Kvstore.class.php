<?php
namespace FreePBX\Console\Command;
//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\Question;

class Kvstore extends Command {
  protected function configure(){
    $this->setName('kvstore')
    ->setDescription('Manage kvstore')
    ->setDefinition(array(
      new InputOption('iknowwhatiamdoing', '', InputOption::VALUE_NONE, _('With great power comes great responsibility. This command can potentially break things. Unless you pass this parameter it is readonly')),
      new InputArgument('module',InputArgument::REQUIRED, 'What module are we making calls against?'),
      new InputOption('action','',InputOption::VALUE_REQUIRED, 'What action to call',''),
      new InputOption('key','',InputOption::VALUE_REQUIRED, 'The key',''),
      new InputOption('value','',InputOption::VALUE_REQUIRED, 'A Value if setting one',''),
      new InputOption('group','',InputOption::VALUE_REQUIRED, 'Optional Group','noid'))
      );
  }
  protected function execute(InputInterface $input, OutputInterface $output){
    $module = ucfirst($input->getArgument('module'));
    $action = $input->getOption('action');
    $key = $input->getOption('key');
    $value = $input->getOption('value');
    $group = $input->getOption('group');
    $c = \FreePBX::$module();
    if(!is_object($c)){
      $output->writeln(sprintf(_("Could not load a class for %s"),$module));
      return;
    }
    if(!is_subclass_of($c,'\\FreePBX\\DB_Helper')){
      $output->writeln(sprintf(_("%s does not implement kvstore"),$module));
      return;
    }
    switch ($action) {
      case 'getall':
        $output->writeln(json_encode($c->getAll($group),JSON_PRETTY_PRINT));
      break;
      case 'deleteall':
        if(!$input->getOption('iknowwhatiamdoing')){
          $output->writeln("This command blocked in readonly mode");
          return;
        }
        $c->deleteAll();
        break;
        case 'getallkeys':
          $output->writeln(json_encode($c->getAllKeys($group),JSON_PRETTY_PRINT));
        break;
        case 'getallids':
          $output->writeln(json_encode($c->getAllids(),JSON_PRETTY_PRINT));
        break;
        case 'deletebyid':
          if(!$input->getOption('iknowwhatiamdoing')){
            $output->writeln("This command blocked in readonly mode");
            return;
          }
          $c->delById($key);
        break;
        case 'set':
          $tmpval = @json_decode($value, true);
          if (json_last_error() == JSON_ERROR_NONE) {
            $c->setConfig($key, $tmpval);
          } else {
            $c->setConfig($key, $value);
          }
          $output->writeln("Key stored successfully");
        break;
        case 'get':
          $result = $c->getConfig($key);
          $output->writeLn($result ? json_encode($result) : "Key not found!");
        break;
        default:
          $output->writeln("Invalid or no command provided");
        break;
    }

  }
}
