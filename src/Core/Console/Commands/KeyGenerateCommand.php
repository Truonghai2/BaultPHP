<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class KeyGenerateCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'key:generate';
    }

    public function description(): string
    {
        return 'Set the application key.';
    }

    public function handle(): int
    {
        $key = 'base64:'.base64_encode(random_bytes(32));

        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            $this->io->error(".env file not found. Please create it from .env.example first.");
            return 1;
        }

        $content = file_get_contents($envPath);

        if (str_contains($content, 'APP_KEY=')) {
            $content = preg_replace(
                '/^APP_KEY=.*$/m',
                'APP_KEY='.$key,
                $content
            );
        } else {
            $content .= "\nAPP_KEY=".$key."\n";
        }

        file_put_contents($envPath, $content);

        $this->io->success("Application key [$key] set successfully.");
        return 0;
    }
}