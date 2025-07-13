<?php

namespace Core\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

class ConfigClearCommand extends BaseCommand
{
    protected static $defaultName = 'config:clear';
    protected static $defaultDescription = 'Remove the service provider cache file.';

    private Application $app;

    public function __construct(Application $app)
    {
        parent::__construct();
        $this->app = $app;
    }

    public function signature(): string
    {
        return 'config:clear';
    }

    public function description(): string
    {
        return 'Remove the service provider cache file.';
    }

    /**
     * The core logic of the command.
     * This method clears the service provider cache.
     */
    public function handle(array $args = []): void
    {
        $this->io->title('Clearing Service Provider Cache');
        $this->io->info('Removing cached service providers...');
        $this->fire();
    }

    /**
     * The entry point for the command.
     * This method executes the command logic.
     */
    public function fire(): void
    {
        $cachePath = $this->app->getCachedProvidersPath();
        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->io->success('Service provider cache cleared successfully!');
        } else {
            $this->io->info('No service provider cache found. Nothing to clear.');
        }
    }
}