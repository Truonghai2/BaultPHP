<?php

namespace Core\Contracts\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The console kernel is the entry point for all console commands.
 */
interface Kernel
{
    /**
     * Handle an incoming console command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    public function handle(InputInterface $input, OutputInterface $output): int;

    /**
     * Terminate the application.
     *
     * This method is called after the command has been handled.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  int  $status
     * @return void
     */
    public function terminate(InputInterface $input, int $status): void;

    public function getApplication(): Application;
}
