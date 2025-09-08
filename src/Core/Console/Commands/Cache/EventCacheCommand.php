<?php

namespace Core\Console\Commands\Cache;

use App\Providers\EventServiceProvider;
use Core\Console\Contracts\BaseCommand;

class EventCacheCommand extends BaseCommand
{
    /**
     * The signature of the command.
     */
    public function signature(): string
    {
        return 'event:cache';
    }

    /**
     * The description of the command.
     */
    public function description(): string
    {
        return 'Create a cache file for faster event registration.';
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->comment('Caching events and listeners...');

        // Resolve the provider from the container to ensure it's properly initialized.
        /** @var EventServiceProvider $provider */
        $provider = $this->app->make(EventServiceProvider::class);

        $allEvents = $provider->getEventsToRegister();

        $cacheFile = $this->app->getCachedEventsPath();
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        $content = '<?php return ' . var_export($allEvents, true) . ';';

        file_put_contents($cacheFile, $content);

        $this->info('✔ Events and listeners cached successfully!');

        return self::SUCCESS;
    }
}
