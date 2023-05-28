<?php

/**
 * This is the part of Povils open-source library.
 *
 * @author Povilas Susinskas
 */

namespace Povils\Figlet\Command;

use Povils\Figlet\Figlet;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class FigletCommand
 *
 * @package Povils\Figlet\Command
 */
class FigletCommand extends Command
{
   /**
    * @inheritdoc
    */
   protected function configure()
   {
      $this
         ->setName('figlet')
         ->setDescription('Writes figlet text in terminal')
         ->addArgument('text', InputArgument::REQUIRED, 'Here should be figlet text')
         ->addOption(
            'font',
            'f',
            InputOption::VALUE_OPTIONAL,
            'Figlet font',
            'big'
         )
         ->addOption(
            'color',
            'c',
            InputOption::VALUE_OPTIONAL,
            'Figlet font color'
         )
         ->addOption(
            'bg-color',
            'b',
            InputOption::VALUE_OPTIONAL,
            'Figlet background color'
         )
         ->addOption(
            'stretching',
            's',
            InputOption::VALUE_OPTIONAL,
            'Add spaces between characters'
         )
      ;
   }

   /**
    * @inheritdoc
    */
   protected function execute(InputInterface $input, OutputInterface $output)
   {

      $figlet = new Figlet();
      $figlet
         ->setFont($input->getOption('font'));

      if(null !== $input->getOption('color')){
         $figlet->setFontColor($input->getOption('color'));
      }

      if(null !== $input->getOption('bg-color')){
         $figlet->setBackgroundColor($input->getOption('bg-color'));
      }

      if(null !== $input->getOption('stretching')){
         $figlet->setFontStretching($input->getOption('stretching'));
      }

      $output->write($figlet->render($input->getArgument('text')));

      return 0;
   }
}
