<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;
use Core\Module\ModuleSynchronizer;
use Throwable;

class ModuleSyncCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'module:sync';
    }

    public function description(): string
    {
        return 'Synchronize filesystem modules with the database.';
    }

    public function handle(): int
    {
        $this->comment('Synchronizing Modules...');

        try {
            /** @var ModuleSynchronizer $synchronizer */
            $synchronizer = $this->app->make(ModuleSynchronizer::class);

            $result = $synchronizer->sync();

            if (!empty($result['added'])) {
                $this->line("\n<fg=yellow>Registering new modules:</>");
                foreach ($result['added'] as $moduleName) {
                    $this->line("  <fg=green>+ Registered:</> {$moduleName}");
                }
                $this->info('New modules registered. Enable them with `module:manage --enable=<ModuleName>`.');
            } else {
                $this->line('› No new modules found to register.');
            }

            if (!empty($result['removed'])) {
                $this->line("\n<fg=yellow>Cleaning up stale records:</>");
                foreach ($result['removed'] as $moduleName) {
                    $this->line("  <fg=red>- Removed stale record:</> {$moduleName}");
                }
                $this->info('Stale module records removed.');
            } else {
                $this->line('› No stale module records to remove.');
            }

            $this->info("\nModule synchronization complete.");

            return 0;
        } catch (Throwable $e) {
            $this->error('An error occurred during module synchronization: ' . $e->getMessage());
            return 1;
        }
    }
}
