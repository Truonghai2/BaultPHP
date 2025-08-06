<?php

namespace Core\Listeners;

use Core\Application;
use Core\Console\Commands\ModuleClearCommand;
use Core\Console\Commands\RouteClearCommand;
use Core\Events\ModuleChanged;
use Psr\Log\LoggerInterface;

class ClearRelatedCacheOnModuleChange
{
    protected Application $app;
    protected ?LoggerInterface $logger;

    public function __construct(Application $app)
    {
        $this->app = $app;
        // Safely resolve the logger from the container
        $this->logger = $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null;
    }

    /**
     * Handle the event.
     *
     * @param ModuleChanged $event
     * @return void
     */
    public function handle(ModuleChanged $event): void
    {
        $this->log("Module '{$event->moduleName}' was {$event->action}. Invalidating related caches...");

        // 1. Execute module:clear command
        $this->runCommand(ModuleClearCommand::class);

        // 2. Execute route:clear command
        $this->runCommand(RouteClearCommand::class);

        // 3. Invalidate other caches that depend on module structure, like the CMS block cache
        $cmsCachePath = $this->app->basePath('storage/cache/cms_blocks.php');
        if (file_exists($cmsCachePath)) {
            if (@unlink($cmsCachePath)) {
                $this->log('✔ CMS Block cache cleared successfully!');
            } else {
                $this->log('Error: Could not clear CMS Block cache.', 'error');
            }
        }

        $this->log('Cache invalidation process finished.');
    }

    /**
     * Instantiates and runs a console command.
     * @param class-string<\Core\Console\Contracts\CommandInterface> $commandClass
     */
    private function runCommand(string $commandClass): void
    {
        /** @var \Core\Console\Contracts\BaseCommand $command */
        $command = new $commandClass();
        $command->setCoreApplication($this->app);
        $command->handle();
    }

    private function log(string $message, string $level = 'info'): void
    {
        if ($this->logger) {
            $this->logger->{$level}($message);
        }
    }
}
