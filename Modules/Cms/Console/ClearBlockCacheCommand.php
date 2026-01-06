<?php

declare(strict_types=1);

namespace Modules\Cms\Console\Commands;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\Cms\Domain\Services\BlockCacheManager;
use Modules\Cms\Infrastructure\Models\Page;

/**
 * Clear Block Cache Command
 *
 * CLI command to clear block-related caches
 */
class ClearBlockCacheCommand extends BaseCommand
{
    public function __construct(
        Application $app,
        private readonly BlockCacheManager $cacheManager,
    ) {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'cache:clear-blocks 
                {--region= : Clear specific region only}
                {--page= : Clear specific page only (ID)}
                {--global : Clear global regions only}';
    }

    public function description(): string
    {
        return 'Clear all block-related caches';
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $region = $this->option('region');
        $pageId = $this->option('page');
        $global = $this->option('global');

        try {
            if ($region && $global) {
                // Clear global region
                $this->cacheManager->invalidateGlobalRegion($region);
                $this->success("Cleared global region cache: {$region}");

            } elseif ($region && $pageId) {
                // Clear specific region on page
                $this->cacheManager->invalidatePageRegion((int)$pageId, $region);
                $this->success("Cleared region '{$region}' on page {$pageId}");

            } elseif ($pageId) {
                // Clear all regions for page
                $page = Page::find((int)$pageId);
                if (!$page) {
                    $this->error("Page not found: {$pageId}");
                    return self::FAILURE;
                }

                $this->cacheManager->invalidatePage($page);
                $this->success("Cleared all caches for page: {$page->name}");

            } else {
                // Nuclear option - clear all
                $this->warn('Clearing ALL block caches...');
                $this->cacheManager->clearAll();
                $this->success('All block caches cleared!');
            }

            // Show stats
            $stats = $this->cacheManager->getStats();
            $this->io->section('Cache Statistics');
            $this->io->table(
                ['Metric', 'Value'],
                [
                    ['Cached Instances', $stats['registry']['cached_instances']],
                    ['Validated Classes', $stats['registry']['validated_classes']],
                    ['Valid Classes', $stats['registry']['valid_classes']],
                    ['Invalid Classes', $stats['registry']['invalid_classes']],
                    ['Cache Driver', $stats['cache_driver']],
                ],
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to clear cache: {$e->getMessage()}");
            if ($this->io->isVerbose()) {
                $this->io->writeln($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }
}
