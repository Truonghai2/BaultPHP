<?php

namespace Core\Console\Commands\Cache;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\FileSystem\Filesystem;

class ViewClearCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    /**
     * The signature of the command.
     */
    public function signature(): string
    {
        return 'view:clear';
    }

    /**
     * The description of the command.
     */
    public function description(): string
    {
        return 'Clear all compiled view files.';
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var Filesystem $files */
        $files = $this->app->make(Filesystem::class);

        $path = config('view.compiled');

        if (!$path || !$files->isDirectory($path)) {
            $this->info('Compiled views directory not found or not configured.');
            return self::SUCCESS;
        }

        foreach (glob($path . '/*') as $view) {
            $files->delete($view);
        }

        $this->info('Compiled views cleared!');
        return self::SUCCESS;
    }
}
