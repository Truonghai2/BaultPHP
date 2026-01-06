<?php

namespace Core\Console\Commands;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Symfony\Component\Console\Helper\Table;

/**
 * Display file watcher status and metrics
 */
class WatcherStatusCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'watcher:status {--json : Output as JSON}';
    }

    public function description(): string
    {
        return 'Display file watcher status and performance metrics';
    }

    public function handle(): int
    {
        $logPath = storage_path('logs/watcher.log');
        
        if (!file_exists($logPath)) {
            $this->error('File watcher log not found. Is the watcher running?');
            $this->info('Start the watcher with: php cli serve:watch');
            return self::FAILURE;
        }

        $lines = $this->readLastLines($logPath, 100);
        $metrics = $this->extractMetrics($lines);
        $lastChanges = $this->extractLastChanges($lines, 10);
        
        if ($this->option('json')) {
            $this->line(json_encode([
                'metrics' => $metrics,
                'last_changes' => $lastChanges,
            ], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info('═══════════════════════════════════════════════');
        $this->info('     Docker File Watcher v2.0 - Status        ');
        $this->info('═══════════════════════════════════════════════');
        $this->line('');

        if (empty($metrics)) {
            $this->warn('No metrics found in logs. Watcher may have just started.');
            $this->line('');
            $this->info('Check logs: tail -f ' . $logPath);
            return self::SUCCESS;
        }

        $this->comment('Performance Metrics:');
        $table = new Table($this->output);
        $table->setHeaders(['Metric', 'Value']);
        $table->setRows([
            ['Total Scans', number_format($metrics['total_scans'] ?? 0)],
            ['Total Reloads', number_format($metrics['total_reloads'] ?? 0)],
            ['Files Tracked', number_format($metrics['files_tracked'] ?? 0)],
            ['Avg Scan Time', ($metrics['avg_scan_time_ms'] ?? 0) . ' ms'],
            ['Total Changes', number_format($metrics['total_changes_detected'] ?? 0)],
            ['Pending Changes', number_format($metrics['pending_changes'] ?? 0)],
            ['Last Reload', $metrics['last_reload'] ?? 'Never'],
        ]);
        $table->render();
        
        $this->line('');
        
        $this->displayHealthStatus($metrics);
        
        $this->line('');

        if (!empty($lastChanges)) {
            $this->comment('Recent Changes (Last 10):');
            $changesTable = new Table($this->output);
            $changesTable->setHeaders(['Time', 'Type', 'File']);
            
            foreach ($lastChanges as $change) {
                $changesTable->addRow([
                    $change['time'],
                    $this->formatChangeType($change['type']),
                    $this->truncatePath($change['file']),
                ]);
            }
            
            $changesTable->render();
        }

        $this->line('');
        $this->info('For detailed logs: tail -f ' . $logPath);
        
        return self::SUCCESS;
    }

    /**
     * Display health status based on metrics
     */
    private function displayHealthStatus(array $metrics): void
    {
        $this->comment('Health Status:');
        
        $health = [];
        $avgScanTime = $metrics['avg_scan_time_ms'] ?? 0;
        $totalScans = $metrics['total_scans'] ?? 0;
        $totalReloads = $metrics['total_reloads'] ?? 0;
        
        if ($avgScanTime > 100) {
            $health[] = ['⚠️ WARNING', 'Average scan time is high (>' . $avgScanTime . 'ms)'];
        } else {
            $health[] = ['✅ GOOD', 'Scan performance is optimal'];
        }
        
        if ($totalScans > 0) {
            $reloadRate = ($totalReloads / $totalScans) * 100;
            if ($reloadRate > 10) {
                $health[] = ['⚠️ WARNING', 'High reload rate (' . round($reloadRate, 1) . '%)'];
            } else {
                $health[] = ['✅ GOOD', 'Reload rate is normal'];
            }
        }
        
        $table = new Table($this->output);
        $table->setRows($health);
        $table->render();
    }

    /**
     * Extract metrics from log
     */
    private function extractMetrics(array $lines): array
    {
        $metrics = [];
        
        foreach (array_reverse($lines) as $line) {
            if (strpos($line, 'performance metrics') !== false) {
                if (preg_match('/\{.*\}/', $line, $matches)) {
                    $json = json_decode($matches[0], true);
                    if ($json) {
                        $metrics = $json;
                        break;
                    }
                }
            }
        }
        
        return $metrics;
    }

    /**
     * Extract last changes from log
     */
    private function extractLastChanges(array $lines, int $limit = 10): array
    {
        $changes = [];
        
        foreach (array_reverse($lines) as $line) {
            if (strpos($line, 'changes detected') !== false || 
                strpos($line, 'Reloading server') !== false) {
                
                if (preg_match('/\[([\d\-\s:]+)\]/', $line, $timeMatch)) {
                    $time = $timeMatch[1];
                    
                    if (preg_match('/"changes":\s*(\[.*?\])/', $line, $changeMatch)) {
                        $changesData = json_decode($changeMatch[1], true);
                        if ($changesData) {
                            foreach ($changesData as $change) {
                                $changes[] = [
                                    'time' => $time,
                                    'type' => $change['type'] ?? 'unknown',
                                    'file' => $change['file'] ?? 'unknown',
                                ];
                                
                                if (count($changes) >= $limit) {
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $changes;
    }

    /**
     * Read last N lines from a file
     */
    private function readLastLines(string $filePath, int $lines = 100): array
    {
        $handle = fopen($filePath, "r");
        if (!$handle) {
            return [];
        }

        $lineArray = [];
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line !== false) {
                $lineArray[] = $line;
                if (count($lineArray) > $lines) {
                    array_shift($lineArray);
                }
            }
        }

        fclose($handle);
        return $lineArray;
    }

    /**
     * Format change type
     */
    private function formatChangeType(string $type): string
    {
        return match($type) {
            'created' => '<info>created</info>',
            'modified' => '<comment>modified</comment>',
            'deleted' => '<error>deleted</error>',
            default => $type,
        };
    }

    /**
     * Truncate path for display
     */
    private function truncatePath(string $path, int $maxLength = 60): string
    {
        if (strlen($path) <= $maxLength) {
            return $path;
        }
        
        $basePath = base_path();
        if (strpos($path, $basePath) === 0) {
            $path = '...' . substr($path, strlen($basePath));
        }
        
        if (strlen($path) > $maxLength) {
            return '...' . substr($path, -($maxLength - 3));
        }
        
        return $path;
    }
}

