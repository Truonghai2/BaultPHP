<?php

declare(strict_types=1);

namespace Modules\Cms\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\Cms\Domain\Services\BlockSyncService;

/**
 * Sync Blocks Command
 * 
 * Automatically discovers and syncs block types to database
 * Usage: php cli cms:sync-blocks [--force]
 */
class SyncBlocksCommand extends BaseCommand
{
    public function __construct(
        Application $app,
        private readonly BlockSyncService $syncService
    ) {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'cms:sync-blocks {--force : Force sync even if recently synced}';
    }

    public function description(): string
    {
        return 'Sync block types and regions to database';
    }

    public function handle(): int
    {
        $this->io->title('Block Sync');
        $this->info('Syncing blocks to database...');
        $this->io->newLine();

        $force = $this->option('force');

        if ($force) {
            $this->warn('Force sync enabled - bypassing cache');
            $this->io->newLine();
        }

        try {
            $stats = $this->syncService->syncBlocks($force);

            // Display results
            $this->io->table(
                ['Metric', 'Count'],
                [
                    ['Block Types Created', $stats['types_created']],
                    ['Block Types Updated', $stats['types_updated']],
                    ['Block Types Deactivated', $stats['types_deleted']],
                    ['Regions Created', $stats['regions_created']],
                    ['Regions Updated', $stats['regions_updated']],
                    ['Total Time', $stats['total_time'] . 's'],
                ]
            );

            $this->io->newLine();

            if ($stats['types_created'] === 0 && $stats['types_updated'] === 0 && $stats['regions_created'] === 0) {
                $this->io->success('Everything is already up to date!');
            } else {
                $this->io->success('Blocks synced successfully!');
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Failed to sync blocks: ' . $e->getMessage());
            
            if ($this->input->getOption('verbose')) {
                $this->io->newLine();
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }
}
