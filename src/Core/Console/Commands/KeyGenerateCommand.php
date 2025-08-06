<?php

namespace Core\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class KeyGenerateCommand extends Command
{
    protected static $defaultName = 'key:generate';

    protected function configure()
    {
        $this->setDescription('Generate a new application key.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $key = 'base64:'.base64_encode(random_bytes(32));

        $path = base_path('.env');

        if (file_exists($path)) {
            $content = file_get_contents($path);

            if (strpos($content, 'APP_KEY=') !== false) {
                $content = preg_replace(
                    '/APP_KEY=.*/',
                    'APP_KEY='.$key,
                    $content
                );
            } else {
                $content .= "\nAPP_KEY=".$key;
            }

            file_put_contents($path, $content);
        }

        $output->writeln("Application key set successfully.");

        return Command::SUCCESS;
    }
}