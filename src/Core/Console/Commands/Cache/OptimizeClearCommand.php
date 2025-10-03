<?php

namespace Core\Console\Commands\Cache;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

class OptimizeClearCommand extends BaseCommand
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
        return 'optimize:clear';
    }

    /**
     * The description of the command.
     */
    public function description(): string
    {
        return 'Remove the compiled service container cache file.';
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cachePath = $this->app->bootstrapPath('cache/container.php');

        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->info('✔ Compiled service container cache cleared successfully!');
        } else {
            $this->comment('› Compiled service container cache not found. Nothing to clear.');
        }

        return self::SUCCESS;
    }
}
