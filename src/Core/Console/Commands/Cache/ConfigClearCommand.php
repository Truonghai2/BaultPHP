<?php

namespace Core\Console\Commands\Cache;

use Core\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigClearCommand extends Command
{
    protected static $defaultName = 'config:clear';

    protected Application $app;

    public function __construct(Application $app)
    {
        parent::__construct();
        $this->app = $app;
    }

    protected function configure()
    {
        $this->setName(static::$defaultName);
        $this->setDescription('Clear the configuration cache.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $this->app->bootstrapPath('cache/config.php');

        if (file_exists($configPath)) {
            @unlink($configPath);
            $output->writeln('<info>Configuration cache cleared!</info>');
        } else {
            $output->writeln('<info>Configuration cache not found.</info>');
        }

        return Command::SUCCESS;
    }
}
