<?php

declare(strict_types=1);

namespace Core\Development;

use Core\Support\Facades\Log;

/**
 * Visual Debugging Tools
 *
 * Provides visual debugging interface với:
 * - Request flow visualization
 * - Database query analyzer
 * - Performance profiler với flame graphs
 * - Memory leak detector
 *
 * Features:
 * - Request flow visualization
 * - Database query analyzer
 * - Performance profiler với flame graphs
 * - Memory leak detector
 */
class VisualDebugger
{
    protected array $requestFlow = [];
    protected array $queries = [];
    protected array $performanceData = [];
    protected array $memorySnapshots = [];

    public function __construct(
        protected array $config = [],
    ) {
    }

    /**
     * Start tracking request flow
     */
    public function startRequest(string $requestId, array $requestData): void
    {
        $this->requestFlow[$requestId] = [
            'start_time' => microtime(true),
            'request' => $requestData,
            'steps' => [],
            'queries' => [],
            'memory_peak' => 0,
        ];
    }

    /**
     * Add step to request flow
     */
    public function addStep(string $requestId, string $step, array $data = []): void
    {
        if (!isset($this->requestFlow[$requestId])) {
            return;
        }

        $this->requestFlow[$requestId]['steps'][] = [
            'step' => $step,
            'time' => microtime(true),
            'memory' => memory_get_usage(true),
            'data' => $data,
        ];
    }

    /**
     * Record database query
     */
    public function recordQuery(string $requestId, string $query, float $duration, array $bindings = []): void
    {
        if (!isset($this->requestFlow[$requestId])) {
            return;
        }

        $this->requestFlow[$requestId]['queries'][] = [
            'query' => $query,
            'duration' => $duration,
            'bindings' => $bindings,
            'time' => microtime(true),
        ];

        $this->queries[] = [
            'request_id' => $requestId,
            'query' => $query,
            'duration' => $duration,
            'bindings' => $bindings,
        ];
    }

    /**
     * End request tracking
     */
    public function endRequest(string $requestId): array
    {
        if (!isset($this->requestFlow[$requestId])) {
            return [];
        }

        $flow = $this->requestFlow[$requestId];
        $flow['end_time'] = microtime(true);
        $flow['duration'] = $flow['end_time'] - $flow['start_time'];
        $flow['memory_peak'] = memory_get_peak_usage(true);
        $flow['memory_usage'] = memory_get_usage(true);

        // Generate flame graph data
        $flow['flame_graph'] = $this->generateFlameGraph($flow);

        // Detect memory leaks
        $flow['memory_leak'] = $this->detectMemoryLeak($requestId);

        return $flow;
    }

    /**
     * Generate flame graph data
     */
    protected function generateFlameGraph(array $flow): array
    {
        $flameGraph = [];
        $startTime = $flow['start_time'];

        foreach ($flow['steps'] as $step) {
            $flameGraph[] = [
                'name' => $step['step'],
                'start' => ($step['time'] - $startTime) * 1000, // Convert to ms
                'duration' => isset($prevStep) ? (($step['time'] - $prevStep['time']) * 1000) : 0,
                'memory' => $step['memory'],
            ];
            $prevStep = $step;
        }

        return $flameGraph;
    }

    /**
     * Detect memory leaks
     */
    protected function detectMemoryLeak(string $requestId): ?array
    {
        if (!isset($this->memorySnapshots[$requestId])) {
            return null;
        }

        $snapshots = $this->memorySnapshots[$requestId];
        
        if (count($snapshots) < 2) {
            return null;
        }

        $first = $snapshots[0];
        $last = end($snapshots);
        
        $growth = $last['memory'] - $first['memory'];
        $growthPercent = ($growth / max($first['memory'], 1)) * 100;

        if ($growthPercent > 50) { // 50% growth threshold
            return [
                'detected' => true,
                'growth' => $growth,
                'growth_percent' => $growthPercent,
                'snapshots' => $snapshots,
            ];
        }

        return ['detected' => false];
    }

    /**
     * Take memory snapshot
     */
    public function takeMemorySnapshot(string $requestId, string $label = ''): void
    {
        if (!isset($this->memorySnapshots[$requestId])) {
            $this->memorySnapshots[$requestId] = [];
        }

        $this->memorySnapshots[$requestId][] = [
            'label' => $label,
            'memory' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'time' => microtime(true),
        ];
    }

    /**
     * Analyze database queries
     */
    public function analyzeQueries(string $requestId = null): array
    {
        $queries = $requestId 
            ? array_filter($this->queries, fn($q) => $q['request_id'] === $requestId)
            : $this->queries;

        if (empty($queries)) {
            return [];
        }

        $totalDuration = array_sum(array_column($queries, 'duration'));
        $slowQueries = array_filter($queries, fn($q) => $q['duration'] > 100); // > 100ms
        $duplicateQueries = $this->findDuplicateQueries($queries);

        return [
            'total_queries' => count($queries),
            'total_duration' => $totalDuration,
            'average_duration' => $totalDuration / count($queries),
            'slow_queries' => array_values($slowQueries),
            'duplicate_queries' => $duplicateQueries,
            'queries_by_table' => $this->groupQueriesByTable($queries),
        ];
    }

    /**
     * Find duplicate queries
     */
    protected function findDuplicateQueries(array $queries): array
    {
        $queryCounts = [];
        
        foreach ($queries as $query) {
            $normalized = $this->normalizeQuery($query['query']);
            if (!isset($queryCounts[$normalized])) {
                $queryCounts[$normalized] = [];
            }
            $queryCounts[$normalized][] = $query;
        }

        $duplicates = [];
        foreach ($queryCounts as $normalized => $instances) {
            if (count($instances) > 1) {
                $duplicates[$normalized] = [
                    'count' => count($instances),
                    'instances' => $instances,
                ];
            }
        }

        return $duplicates;
    }

    /**
     * Normalize query for comparison
     */
    protected function normalizeQuery(string $query): string
    {
        // Remove values, keep structure
        $normalized = preg_replace('/\b\d+\b/', '?', $query);
        $normalized = preg_replace('/\'[^\']*\'/', '?', $normalized);
        $normalized = preg_replace('/"[^"]*"/', '?', $normalized);
        
        return trim($normalized);
    }

    /**
     * Group queries by table
     */
    protected function groupQueriesByTable(array $queries): array
    {
        $grouped = [];

        foreach ($queries as $query) {
            $table = $this->extractTable($query['query']);
            if ($table) {
                if (!isset($grouped[$table])) {
                    $grouped[$table] = [];
                }
                $grouped[$table][] = $query;
            }
        }

        return $grouped;
    }

    /**
     * Extract table name from query
     */
    protected function extractTable(string $query): ?string
    {
        $query = trim($query);
        
        // Match FROM table
        if (preg_match('/\bFROM\s+`?(\w+)`?/i', $query, $matches)) {
            return $matches[1];
        }
        
        // Match INTO table
        if (preg_match('/\bINTO\s+`?(\w+)`?/i', $query, $matches)) {
            return $matches[1];
        }
        
        // Match UPDATE table
        if (preg_match('/\bUPDATE\s+`?(\w+)`?/i', $query, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get request flow visualization data
     */
    public function getRequestFlow(string $requestId): array
    {
        if (!isset($this->requestFlow[$requestId])) {
            return [];
        }

        return $this->requestFlow[$requestId];
    }

    /**
     * Get performance profile
     */
    public function getPerformanceProfile(string $requestId): array
    {
        $flow = $this->getRequestFlow($requestId);
        
        if (empty($flow)) {
            return [];
        }

        return [
            'duration' => $flow['duration'] ?? 0,
            'memory_peak' => $flow['memory_peak'] ?? 0,
            'memory_usage' => $flow['memory_usage'] ?? 0,
            'queries_count' => count($flow['queries'] ?? []),
            'queries_duration' => array_sum(array_column($flow['queries'] ?? [], 'duration')),
            'steps_count' => count($flow['steps'] ?? []),
            'flame_graph' => $flow['flame_graph'] ?? [],
        ];
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return [
            'requests_tracked' => count($this->requestFlow),
            'queries_recorded' => count($this->queries),
            'memory_snapshots' => array_sum(array_map('count', $this->memorySnapshots)),
        ];
    }
}
