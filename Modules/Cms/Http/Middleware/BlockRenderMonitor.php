<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Block Render Performance Monitor
 * 
 * Monitors performance metrics during block rendering in development mode
 * 
 * Features:
 * - Query counting
 * - Render time tracking
 * - Memory usage monitoring
 * - Automatic warnings for slow pages
 * - Debug information injection
 */
class BlockRenderMonitor implements MiddlewareInterface
{
    private const QUERY_WARNING_THRESHOLD = 20;
    private const TIME_WARNING_THRESHOLD_MS = 500;
    private const MEMORY_WARNING_THRESHOLD_MB = 20;

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Only monitor in debug mode
        if (!config('app.debug', false)) {
            return $handler->handle($request);
        }

        // Start monitoring
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $startQueries = $this->getQueryCount();

        // Process request
        $response = $handler->handle($request);

        // Calculate metrics
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endQueries = $this->getQueryCount();

        $metrics = [
            'queries' => $endQueries - $startQueries,
            'time_ms' => ($endTime - $startTime) * 1000,
            'memory_mb' => ($endMemory - $startMemory) / 1024 / 1024,
            'url' => (string) $request->getUri(),
            'method' => $request->getMethod(),
        ];

        // Log warnings
        $this->checkPerformanceWarnings($metrics);

        // Inject debug info if HTML response
        if ($this->isHtmlResponse($response)) {
            $response = $this->injectDebugInfo($response, $metrics);
        }

        return $response;
    }

    /**
     * Get current query count
     */
    private function getQueryCount(): int
    {
        if (!function_exists('db')) {
            return 0;
        }

        try {
            $log = db()->getQueryLog();
            return count($log);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Check performance metrics and log warnings
     */
    private function checkPerformanceWarnings(array $metrics): void
    {
        $warnings = [];

        if ($metrics['queries'] > self::QUERY_WARNING_THRESHOLD) {
            $warnings[] = sprintf('High query count: %d queries', $metrics['queries']);
        }

        if ($metrics['time_ms'] > self::TIME_WARNING_THRESHOLD_MS) {
            $warnings[] = sprintf('Slow render time: %.2f ms', $metrics['time_ms']);
        }

        if ($metrics['memory_mb'] > self::MEMORY_WARNING_THRESHOLD_MB) {
            $warnings[] = sprintf('High memory usage: %.2f MB', $metrics['memory_mb']);
        }

        if (!empty($warnings)) {
            $this->logger->warning('Page performance warning', [
                'warnings' => $warnings,
                'metrics' => $metrics,
            ]);
        } else {
            $this->logger->debug('Page render metrics', $metrics);
        }
    }

    /**
     * Check if response is HTML
     */
    private function isHtmlResponse(ResponseInterface $response): bool
    {
        $contentType = $response->getHeaderLine('Content-Type');
        return str_contains($contentType, 'text/html');
    }

    /**
     * Inject debug information into HTML response
     */
    private function injectDebugInfo(ResponseInterface $response, array $metrics): ResponseInterface
    {
        $body = (string) $response->getBody();

        // Only inject if </body> tag exists
        if (!str_contains($body, '</body>')) {
            return $response;
        }

        $debugHtml = $this->generateDebugHtml($metrics);
        $newBody = str_replace('</body>', $debugHtml . '</body>', $body);

        $response->getBody()->rewind();
        $response->getBody()->write($newBody);

        return $response;
    }

    /**
     * Generate debug HTML
     */
    private function generateDebugHtml(array $metrics): string
    {
        $queriesClass = $metrics['queries'] > self::QUERY_WARNING_THRESHOLD ? 'text-red-600' : 'text-green-600';
        $timeClass = $metrics['time_ms'] > self::TIME_WARNING_THRESHOLD_MS ? 'text-red-600' : 'text-green-600';
        $memoryClass = $metrics['memory_mb'] > self::MEMORY_WARNING_THRESHOLD_MB ? 'text-red-600' : 'text-green-600';

        return sprintf(<<<HTML
<!-- Block Render Performance Monitor -->
<div id="block-perf-monitor" style="position: fixed; bottom: 10px; right: 10px; background: rgba(0,0,0,0.9); color: white; padding: 12px; border-radius: 8px; font-family: monospace; font-size: 12px; z-index: 99999; max-width: 300px;">
    <div style="font-weight: bold; margin-bottom: 8px; border-bottom: 1px solid #444; padding-bottom: 4px;">
        ⚡ Block Renderer
    </div>
    <div style="display: grid; grid-template-columns: auto 1fr; gap: 4px 12px;">
        <span>Queries:</span>
        <span class="%s" style="font-weight: bold;">%d</span>
        
        <span>Time:</span>
        <span class="%s" style="font-weight: bold;">%.2f ms</span>
        
        <span>Memory:</span>
        <span class="%s" style="font-weight: bold;">%.2f MB</span>
        
        <span>Method:</span>
        <span>%s</span>
    </div>
    <button onclick="document.getElementById('block-perf-monitor').remove()" style="margin-top: 8px; background: #444; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; width: 100%%;">
        Close
    </button>
</div>
HTML,
            $queriesClass,
            $metrics['queries'],
            $timeClass,
            $metrics['time_ms'],
            $memoryClass,
            $metrics['memory_mb'],
            $metrics['method']
        );
    }
}

