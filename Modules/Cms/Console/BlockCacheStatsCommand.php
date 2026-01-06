<?php

declare(strict_types=1);

namespace Modules\Cms\Console\Commands;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\Cms\Domain\Services\BlockCacheManager;

/**
 * Block Cache Statistics Command
 * 
 * Show cache statistics for the block system
 */
class BlockCacheStatsCommand extends BaseCommand
{
    public function __construct(
        Application $app,
        private readonly BlockCacheManager $cacheManager
    ) {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'cache:stats-blocks';
    }

    public function description(): string
    {
        return 'Show block cache statistics';
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        try {
            $stats = $this->cacheManager->getStats();

            $this->io->title('Block Cache Statistics');

            // Registry stats
            $this->io->section('Block Class Registry');
            $this->io->table(
                ['Metric', 'Value'],
                [
                    ['Cached Instances', $stats['registry']['cached_instances']],
                    ['Validated Classes', $stats['registry']['validated_classes']],
                    ['Valid Classes', $stats['registry']['valid_classes']],
                    ['Invalid Classes', $stats['registry']['invalid_classes']],
                ]
            );

            // Cache driver info
            $this->io->section('Cache Configuration');
            $this->io->writeln("Driver: <info>{$stats['cache_driver']}</info>");

            // Recommendations
            $this->io->section('Recommendations');
            
            if ($stats['registry']['invalid_classes'] > 0) {
                $this->warn("Found {$stats['registry']['invalid_classes']} invalid block classes. Check your block types!");
            }
            
            if ($stats['registry']['cached_instances'] === 0) {
                $this->io->note('No cached block instances. This is normal if no blocks have been rendered yet.');
            } else {
                $this->success("{$stats['registry']['cached_instances']} block instances are cached in memory.");
            }

            return self::SUCCESS;
            
        } catch (\Throwable $e) {
            $this->error("Failed to get cache stats: {$e->getMessage()}");
            if ($this->io->isVerbose()) {
                $this->io->writeln($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }
}

