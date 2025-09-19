<?php

namespace Core\Console\Commands\Cache;

use Core\Console\Contracts\BaseCommand;
use Core\FileSystem\Filesystem;

class EventClearCommand extends BaseCommand
{
    /**
     * The signature of the command.
     */
    public function signature(): string
    {
        return 'event:clear';
    }

    /**
     * The description of the command.
     */
    public function description(): string
    {
        return 'Remove the event cache file.';
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var Filesystem $files */
        $files = $this->app->make(Filesystem::class);

        $cacheFile = $this->app->getCachedEventsPath();

        if ($files->exists($cacheFile)) {
            $files->delete($cacheFile);
            $this->info('Event cache cleared!');
        }

        return self::SUCCESS;
    }
}
