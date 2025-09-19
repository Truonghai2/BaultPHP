<?php

namespace Core\Console\Commands\Cache;

use Core\Console\Contracts\BaseCommand;
use Core\FileSystem\Filesystem;

class ProviderClearCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'provider:clear';
    }

    public function description(): string
    {
        return 'Remove the cached module service provider file.';
    }

    public function handle(): int
    {
        /** @var Filesystem $files */
        $files = $this->app->make(Filesystem::class);

        $cacheFile = $this->app->getCachedProvidersPath();

        if ($files->exists($cacheFile)) {
            $files->delete($cacheFile);
            $this->info('✔ Module provider cache cleared!');
        } else {
            $this->comment('› Module provider cache not found. Nothing to clear.');
        }

        return self::SUCCESS;
    }
}
