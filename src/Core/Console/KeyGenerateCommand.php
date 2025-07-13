<?php

namespace Core\Console;

use Core\Console\Contracts\CommandInterface;

class KeyGenerateCommand implements CommandInterface
{
    public function signature(): string
    {
        return 'key:generate';
    }

    public function description(): string
    {
        return 'Set the application key.';
    }

    public function handle(array $args = []): void
    {
        $key = 'base64:'.base64_encode(random_bytes(32));

        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            echo ".env file not found. Please create it from .env.example first.\n";
            return;
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

        echo "Application key [$key] set successfully.\n";
    }
}