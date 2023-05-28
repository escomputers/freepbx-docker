<?php
/**
 * @package Crony
 * @author Phil Crumm <pkcrumm@gmail.com>
 * @license MIT
 */
namespace FreePBX\Job;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
/**
 * The format that we'll expect all of our Tasks to follow.
 */
interface TaskInterface {
    /**
     * When called, should run the specified task. Returns true on success
     * or false on failure.
     *
     * @return bool
     */
    public static function run(InputInterface $input, OutputInterface $output);
}