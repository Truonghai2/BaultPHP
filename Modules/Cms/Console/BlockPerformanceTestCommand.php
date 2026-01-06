<?php

declare(strict_types=1);

namespace Modules\Cms\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Domain\Services\PageBlockRenderer;

/**
 * Block Performance Test Command
 * 
 * Test and profile block rendering performance
 */
class BlockPerformanceTestCommand extends BaseCommand
{
    public function __construct(
        Application $app,
        private readonly PageBlockRenderer $renderer
    ) {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'cms:test-performance {--page= : Page ID or slug to test} {--region=content : Region to test} {--iterations=10 : Number of iterations}';
    }

    public function description(): string
    {
        return 'Test and profile block rendering performance';
    }

    public function handle(): int
    {
        $this->io->title('Block Rendering Performance Test');

        // Get page
        $pageIdentifier = $this->option('page') ?: 1;
        $page = is_numeric($pageIdentifier) 
            ? Page::find($pageIdentifier)
            : Page::where('slug', $pageIdentifier)->first();

        if (!$page) {
            $this->io->error("Page not found: {$pageIdentifier}");
            return self::FAILURE;
        }

        $region = $this->option('region');
        $iterations = (int) $this->option('iterations');

        $this->io->info("Testing page: {$page->name} (ID: {$page->id})");
        $this->io->info("Region: {$region}");
        $this->io->info("Iterations: {$iterations}");
        $this->io->newLine();

        // Get blocks info
        $blocks = $page->blocksInRegion($region);
        $this->io->comment("Blocks in region: {$blocks->count()}");
        
        if ($blocks->isEmpty()) {
            $this->io->warning('No blocks found in this region!');
            return self::SUCCESS;
        }

        $this->io->newLine();

        // Enable query logging
        if (function_exists('db')) {
            db()->enableQueryLog();
        }

        // Run performance test
        $results = $this->runPerformanceTest($page, $region, $iterations);

        // Display results
        $this->displayResults($results);

        return self::SUCCESS;
    }

    /**
     * Run performance test
     */
    private function runPerformanceTest(Page $page, string $region, int $iterations): array
    {
        $times = [];
        $queryCountsWithCache = [];
        $queryCountsWithoutCache = [];

        $this->io->section('Running performance test...');
        $this->io->progressStart($iterations * 2);

        // Test WITH cache
        $this->renderer->withCache();
        for ($i = 0; $i < $iterations; $i++) {
            if (function_exists('db')) {
                db()->flushQueryLog();
            }

            $start = microtime(true);
            $html = $this->renderer->renderPageBlocks($page, $region);
            $time = (microtime(true) - $start) * 1000;

            $times['with_cache'][] = $time;
            
            if (function_exists('db')) {
                $queryCountsWithCache[] = count(db()->getQueryLog());
            }

            $this->io->progressAdvance();
        }

        // Test WITHOUT cache
        $this->renderer->withoutCache();
        for ($i = 0; $i < $iterations; $i++) {
            if (function_exists('db')) {
                db()->flushQueryLog();
            }

            $start = microtime(true);
            $html = $this->renderer->renderPageBlocks($page, $region);
            $time = (microtime(true) - $start) * 1000;

            $times['without_cache'][] = $time;
            
            if (function_exists('db')) {
                $queryCountsWithoutCache[] = count(db()->getQueryLog());
            }

            $this->io->progressAdvance();
        }

        $this->io->progressFinish();
        $this->io->newLine();

        return [
            'with_cache' => [
                'times' => $times['with_cache'],
                'queries' => $queryCountsWithCache,
                'avg_time' => array_sum($times['with_cache']) / count($times['with_cache']),
                'min_time' => min($times['with_cache']),
                'max_time' => max($times['with_cache']),
                'avg_queries' => !empty($queryCountsWithCache) ? array_sum($queryCountsWithCache) / count($queryCountsWithCache) : 0,
            ],
            'without_cache' => [
                'times' => $times['without_cache'],
                'queries' => $queryCountsWithoutCache,
                'avg_time' => array_sum($times['without_cache']) / count($times['without_cache']),
                'min_time' => min($times['without_cache']),
                'max_time' => max($times['without_cache']),
                'avg_queries' => !empty($queryCountsWithoutCache) ? array_sum($queryCountsWithoutCache) / count($queryCountsWithoutCache) : 0,
            ],
        ];
    }

    /**
     * Display test results
     */
    private function displayResults(array $results): void
    {
        $this->io->section('Performance Results');

        // Summary table
        $this->io->table(
            ['Metric', 'With Cache', 'Without Cache', 'Improvement'],
            [
                [
                    'Avg Time (ms)',
                    round($results['with_cache']['avg_time'], 2),
                    round($results['without_cache']['avg_time'], 2),
                    $this->calculateImprovement($results['without_cache']['avg_time'], $results['with_cache']['avg_time']),
                ],
                [
                    'Min Time (ms)',
                    round($results['with_cache']['min_time'], 2),
                    round($results['without_cache']['min_time'], 2),
                    '-',
                ],
                [
                    'Max Time (ms)',
                    round($results['with_cache']['max_time'], 2),
                    round($results['without_cache']['max_time'], 2),
                    '-',
                ],
                [
                    'Avg Queries',
                    round($results['with_cache']['avg_queries'], 1),
                    round($results['without_cache']['avg_queries'], 1),
                    $this->calculateImprovement($results['without_cache']['avg_queries'], $results['with_cache']['avg_queries']),
                ],
            ]
        );

        // Cache stats
        $cacheStats = $this->renderer->getCacheStats();
        $this->io->newLine();
        $this->io->comment('Cache Statistics:');
        $this->io->listing([
            "Cache Hits: {$cacheStats['hits']}",
            "Cache Misses: {$cacheStats['misses']}",
            "Errors: {$cacheStats['errors']}",
            "Hit Ratio: " . round($this->renderer->getCacheHitRatio() * 100, 2) . "%",
        ]);

        // Recommendations
        $this->io->newLine();
        $this->provideRecommendations($results);
    }

    /**
     * Calculate improvement percentage
     */
    private function calculateImprovement(float $old, float $new): string
    {
        if ($old == 0) {
            return 'N/A';
        }

        $improvement = (($old - $new) / $old) * 100;
        $sign = $improvement > 0 ? '+' : '';
        
        return $sign . round($improvement, 1) . '%';
    }

    /**
     * Provide performance recommendations
     */
    private function provideRecommendations(array $results): void
    {
        $this->io->section('Recommendations');

        $recommendations = [];

        // Check query count
        if ($results['without_cache']['avg_queries'] > 10) {
            $recommendations[] = '⚠️  High query count detected! Consider:';
            $recommendations[] = '   - Enable eager loading in Page::blocksInRegion()';
            $recommendations[] = '   - Implement preloadData() in block classes';
            $recommendations[] = '   - Use database indexes';
        }

        // Check render time
        if ($results['without_cache']['avg_time'] > 100) {
            $recommendations[] = '⚠️  Slow render time! Consider:';
            $recommendations[] = '   - Enable block output caching';
            $recommendations[] = '   - Optimize block render() methods';
            $recommendations[] = '   - Use lazy loading for heavy content';
        }

        // Check cache effectiveness
        $cacheImprovement = $this->calculateImprovementNumeric(
            $results['without_cache']['avg_time'],
            $results['with_cache']['avg_time']
        );

        if ($cacheImprovement < 20) {
            $recommendations[] = '⚠️  Cache not very effective! Consider:';
            $recommendations[] = '   - Increase cache TTL';
            $recommendations[] = '   - Enable region-level caching';
            $recommendations[] = '   - Implement block-level caching';
        }

        if (empty($recommendations)) {
            $this->io->success('✅ Performance looks good! No major issues detected.');
        } else {
            foreach ($recommendations as $rec) {
                $this->io->text($rec);
            }
        }
    }

    /**
     * Calculate improvement as numeric value
     */
    private function calculateImprovementNumeric(float $old, float $new): float
    {
        if ($old == 0) {
            return 0;
        }

        return (($old - $new) / $old) * 100;
    }
}

