<?php

namespace Core\Console\Commands\Cache;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

class RouteClearCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'route:clear';
    }

    public function description(): string
    {
        return 'Remove the route cache file.';
    }

    public function handle(): int
    {
        $this->comment('Clearing Route Cache...');

        $cachePath = $this->app->getCachedRoutesPath();

        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->info('✔ Route cache cleared successfully!');
        } else {
            $this->comment('› Route cache not found. Nothing to clear.');
        }
        return self::SUCCESS;
    }
}
