<?php

namespace Core\Console\Commands\Cache;

use Core\Console\Contracts\BaseCommand;

class CommandCacheCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'command:cache';
    }

    public function description(): string
    {
        return 'Create a command cache file for faster command registration.';
    }

    public function handle(): int
    {
        $this->comment('Caching application commands...');

        $cachePath = $this->app->getCachedCommandsPath();

        if (file_exists($cachePath)) {
            unlink($cachePath);
        }

        $cacheDir = dirname($cachePath);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $provider = $this->app->make(\App\Providers\ConsoleServiceProvider::class);
        $commands = $provider->discoverCommands();

        file_put_contents($cachePath, '<?php return ' . var_export($commands, true) . ';');

        $this->info('✔ Commands cached successfully!');
        return 0;
    }
}
