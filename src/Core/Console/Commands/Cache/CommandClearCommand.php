<?php

namespace Core\Console\Commands\Cache;

use Core\Console\Contracts\BaseCommand;
use Core\Filesystem\Filesystem;

class CommandClearCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'command:clear';
    }

    public function description(): string
    {
        return 'Remove the command cache file.';
    }

    public function handle(): int
    {
        /** @var Filesystem $files */
        $files = $this->app->make(Filesystem::class);

        $cacheFile = $this->app->getCachedCommandsPath();

        if ($files->exists($cacheFile)) {
            $files->delete($cacheFile);
            $this->info('✔ Command cache cleared!');
        } else {
            $this->comment('› Command cache not found. Nothing to clear.');
        }

        return self::SUCCESS;
    }
}
