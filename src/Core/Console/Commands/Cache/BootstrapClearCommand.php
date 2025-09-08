<?php

namespace Core\Console\Commands\Cache;

use Core\Console\Contracts\BaseCommand;
use Core\Filesystem\Filesystem;

class BootstrapClearCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'bootstrap:clear';
    }

    public function description(): string
    {
        return 'Remove the cached framework bootstrap file.';
    }

    public function handle(): int
    {
        /** @var Filesystem $files */
        $files = $this->app->make(Filesystem::class);

        $cacheFile = $this->app->bootstrapPath('cache/services.php');

        if ($files->exists($cacheFile)) {
            $files->delete($cacheFile);
            $this->info('✔ Framework bootstrap cache cleared!');
        } else {
            $this->comment('› Framework bootstrap cache not found. Nothing to clear.');
        }

        return self::SUCCESS;
    }
}
