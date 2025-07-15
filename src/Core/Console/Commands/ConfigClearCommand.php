<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class ConfigClearCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'config:clear';
    }

    public function description(): string
    {
        return 'Remove the service provider cache file.';
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->comment('Clearing Service Provider Cache...');

        $cachePath = $this->app->getCachedProvidersPath();
        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->info('✔ Service provider cache cleared successfully!');
        } else {
            $this->comment('› Service provider cache not found. Nothing to clear.');
        }
        return 0;
    }
}