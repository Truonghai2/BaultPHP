<?php

declare(strict_types=1);

namespace Modules\Cms\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\Cms\Domain\Services\BlockCacheManager;
use Modules\Cms\Infrastructure\Models\Page;

/**
 * Warm Up Block Cache Command
 *
 * Pre-generate cache for pages to improve performance
 */
class WarmupBlockCacheCommand extends BaseCommand
{
    public function __construct(
        Application $app,
        private readonly BlockCacheManager $cacheManager,
    ) {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'cache:warmup-blocks 
                {--page= : Warm up specific page (ID)}
                {--all : Warm up all pages}
                {--popular : Warm up popular pages only (home, published)}';
    }

    public function description(): string
    {
        return 'Pre-generate block caches for pages';
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $pageId = $this->option('page');
        $all = $this->option('all');
        $popular = $this->option('popular');

        try {
            $pages = [];

            if ($pageId) {
                // Single page
                $page = Page::find((int)$pageId);
                if (!$page) {
                    $this->error("Page not found: {$pageId}");
                    return self::FAILURE;
                }
                $pages = [$page];

            } elseif ($all) {
                // All pages
                $pages = Page::all()->all();
                $this->info('Warming up ALL pages...');

            } elseif ($popular) {
                // Popular pages (home + published)
                $pages = Page::where('slug', 'home')
                    ->orWhere('status', 'published')
                    ->limit(10)
                    ->get()
                    ->all();
                $this->info('Warming up popular pages...');

            } else {
                $this->error('Please specify --page, --all, or --popular');
                return self::FAILURE;
            }

            if (empty($pages)) {
                $this->warn('No pages found to warm up');
                return self::SUCCESS;
            }

            $this->io->progressStart(count($pages));

            foreach ($pages as $page) {
                try {
                    $pageModel = $page instanceof Page ? $page : Page::find($page['id'] ?? $page['id'] ?? null);
                    if ($pageModel) {
                        $this->cacheManager->warmUpPage($pageModel);
                    }
                    $this->io->progressAdvance();
                } catch (\Throwable $e) {
                    $pageId = $page instanceof Page ? $page->id : ($page['id'] ?? '?');
                    $this->warn("Failed to warm up page {$pageId}: {$e->getMessage()}");
                }
            }

            $this->io->progressFinish();
            $this->success('Warmed up cache for ' . count($pages) . ' page(s)');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to warm up cache: {$e->getMessage()}");
            if ($this->io->isVerbose()) {
                $this->io->writeln($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }
}
