<?php

namespace Core\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

class RouteClearCommand extends BaseCommand
{
    public function __construct(private Application $app)
    {
        parent::__construct();
    }

    public function signature(): string
    {
        return 'route:clear';
    }

    public function description(): string
    {
        return 'Remove the route cache file.';
    }

    public function fire(): void
    {
        $this->io->title('Clearing Route Cache');

        $cachePath = $this->app->getCachedRoutesPath();

        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->io->success('Route cache cleared successfully!');
        } else {
            $this->io->info('Route cache not found. Nothing to clear.');
        }
    }
}