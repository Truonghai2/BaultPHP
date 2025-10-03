<?php

namespace Core\Console\Commands\Cache;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

class ConfigClearCommand extends BaseCommand
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
        return 'config:clear';
    }

    /**
     * The description of the command.
     */
    public function description(): string
    {
        return 'Remove the configuration cache file.';
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cachePath = $this->app->bootstrapPath('cache/config.php');

        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->info('✔ Configuration cache cleared successfully!');
        } else {
            $this->comment('› Configuration cache not found. Nothing to clear.');
        }

        return self::SUCCESS;
    }
}
