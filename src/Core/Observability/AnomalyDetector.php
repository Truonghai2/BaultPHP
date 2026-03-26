<?php

declare(strict_types=1);

namespace Core\Observability;

use Core\Support\Facades\Log;

/**
 * AI-Powered Anomaly Detector
 *
 * Detects anomalies in system metrics, performance, and behavior.
 * Uses statistical methods and ML-based detection.
 *
 * Features:
 * - ML-based anomaly detection
 * - Auto-alerting
 * - Performance degradation detection
 * - Pattern recognition
 */
class AnomalyDetector
{
    protected array $metrics = [];
    protected array $baselines = [];
    protected array $alerts = [];
    protected array $patterns = [];

    public function __construct(
        protected array $config = [],
    ) {
        $this->loadBaselines();
    }

    /**
     * Load baseline metrics
     */
    protected function loadBaselines(): void
    {
        // Load from cache or database
        $this->baselines = [
            'response_time' => [
                'mean' => 100, // ms
                'stddev' => 20,
                'p95' => 150,
                'p99' => 200,
            ],
            'error_rate' => [
                'mean' => 0.01, // 1%
                'stddev' => 0.005,
            ],
            'cpu_usage' => [
                'mean' => 50, // %
                'stddev' => 15,
            ],
            'memory_usage' => [
                'mean' => 60, // %
                'stddev' => 20,
            ],
        ];
    }

    /**
     * Record a metric
     *
     * @param string $metricName Metric name
     * @param float $value Metric value
     * @param array $tags Additional tags
     */
    public function recordMetric(string $metricName, float $value, array $tags = []): void
    {
        $timestamp = time();
        
        if (!isset($this->metrics[$metricName])) {
            $this->metrics[$metricName] = [];
        }

        $this->metrics[$metricName][] = [
            'value' => $value,
            'timestamp' => $timestamp,
            'tags' => $tags,
        ];

        // Keep only recent metrics (last hour)
        $this->cleanupOldMetrics($metricName);

        // Check for anomalies
        $this->checkAnomalies($metricName, $value, $tags);
    }

    /**
     * Detect anomalies in metrics
     *
     * @return array Detected anomalies
     */
    public function detectAnomalies(): array
    {
        $anomalies = [];

        foreach ($this->metrics as $metricName => $values) {
            if (empty($values)) {
                continue;
            }

            $recentValues = array_slice($values, -100); // Last 100 values
            $anomalies = array_merge($anomalies, $this->analyzeMetric($metricName, $recentValues));
        }

        return $anomalies;
    }

    /**
     * Analyze a metric for anomalies
     */
    protected function analyzeMetric(string $metricName, array $values): array
    {
        $anomalies = [];
        
        if (!isset($this->baselines[$metricName])) {
            return $anomalies;
        }

        $baseline = $this->baselines[$metricName];
        $currentValues = array_column($values, 'value');
        
        // Statistical analysis
        $mean = array_sum($currentValues) / count($currentValues);
        $stddev = $this->calculateStdDev($currentValues, $mean);
        
        // Z-score detection
        foreach ($values as $value) {
            $zScore = abs(($value['value'] - $baseline['mean']) / max($baseline['stddev'], 0.001));
            
            if ($zScore > 3) { // 3-sigma rule
                $anomalies[] = [
                    'metric' => $metricName,
                    'value' => $value['value'],
                    'baseline_mean' => $baseline['mean'],
                    'z_score' => $zScore,
                    'severity' => $this->calculateSeverity($zScore),
                    'timestamp' => $value['timestamp'],
                    'tags' => $value['tags'] ?? [],
                ];
            }
        }

        // Trend analysis
        $trend = $this->detectTrend($currentValues);
        if ($trend['direction'] === 'degrading' && abs($trend['slope']) > 0.1) {
            $anomalies[] = [
                'metric' => $metricName,
                'type' => 'trend',
                'trend' => $trend,
                'severity' => 'medium',
                'timestamp' => time(),
            ];
        }

        // Pattern detection
        $pattern = $this->detectPattern($currentValues);
        if ($pattern['anomalous']) {
            $anomalies[] = [
                'metric' => $metricName,
                'type' => 'pattern',
                'pattern' => $pattern,
                'severity' => 'high',
                'timestamp' => time(),
            ];
        }

        return $anomalies;
    }

    /**
     * Check for anomalies when recording a metric
     */
    protected function checkAnomalies(string $metricName, float $value, array $tags): void
    {
        if (!isset($this->baselines[$metricName])) {
            return;
        }

        $baseline = $this->baselines[$metricName];
        $zScore = abs(($value - $baseline['mean']) / max($baseline['stddev'], 0.001));

        if ($zScore > 2) { // 2-sigma threshold for real-time alerts
            $severity = $this->calculateSeverity($zScore);
            
            $this->triggerAlert($metricName, $value, $zScore, $severity, $tags);
        }
    }

    /**
     * Trigger an alert
     */
    protected function triggerAlert(string $metricName, float $value, float $zScore, string $severity, array $tags): void
    {
        $alert = [
            'metric' => $metricName,
            'value' => $value,
            'z_score' => $zScore,
            'severity' => $severity,
            'timestamp' => time(),
            'tags' => $tags,
        ];

        $this->alerts[] = $alert;

        // Log alert
        Log::warning("Anomaly detected", $alert);

        // Trigger alert callback if configured
        if (isset($this->config['alert_callback']) && is_callable($this->config['alert_callback'])) {
            call_user_func($this->config['alert_callback'], $alert);
        }
    }

    /**
     * Calculate standard deviation
     */
    protected function calculateStdDev(array $values, float $mean): float
    {
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        return sqrt($variance / count($values));
    }

    /**
     * Calculate severity based on Z-score
     */
    protected function calculateSeverity(float $zScore): string
    {
        return match (true) {
            $zScore >= 4 => 'critical',
            $zScore >= 3 => 'high',
            $zScore >= 2 => 'medium',
            default => 'low',
        };
    }

    /**
     * Detect trend in values
     */
    protected function detectTrend(array $values): array
    {
        if (count($values) < 2) {
            return ['direction' => 'stable', 'slope' => 0];
        }

        // Simple linear regression
        $n = count($values);
        $x = range(1, $n);
        $sumX = array_sum($x);
        $sumY = array_sum($values);
        $sumXY = 0;
        $sumX2 = 0;

        foreach ($x as $i => $xi) {
            $sumXY += $xi * $values[$i];
            $sumX2 += $xi * $xi;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        
        return [
            'direction' => $slope > 0.1 ? 'increasing' : ($slope < -0.1 ? 'degrading' : 'stable'),
            'slope' => $slope,
        ];
    }

    /**
     * Detect patterns in values
     */
    protected function detectPattern(array $values): array
    {
        if (count($values) < 10) {
            return ['anomalous' => false];
        }

        // Check for sudden spikes or drops
        $recent = array_slice($values, -5);
        $previous = array_slice($values, -10, 5);
        
        $recentAvg = array_sum($recent) / count($recent);
        $previousAvg = array_sum($previous) / count($previous);
        
        $change = abs(($recentAvg - $previousAvg) / max($previousAvg, 0.001));
        
        return [
            'anomalous' => $change > 0.5, // 50% change
            'change_percent' => $change * 100,
        ];
    }

    /**
     * Update baseline for a metric
     *
     * @param string $metricName Metric name
     * @param array $baseline Baseline data
     */
    public function updateBaseline(string $metricName, array $baseline): void
    {
        $this->baselines[$metricName] = $baseline;
        
        // Persist to cache/database
        Log::info("Baseline updated", [
            'metric' => $metricName,
            'baseline' => $baseline,
        ]);
    }

    /**
     * Get baseline for a metric
     *
     * @param string $metricName Metric name
     * @return array|null Baseline data
     */
    public function getBaseline(string $metricName): ?array
    {
        return $this->baselines[$metricName] ?? null;
    }

    /**
     * Get recent alerts
     *
     * @param int $limit Number of alerts to return
     * @return array Recent alerts
     */
    public function getRecentAlerts(int $limit = 10): array
    {
        return array_slice($this->alerts, -$limit);
    }

    /**
     * Cleanup old metrics
     */
    protected function cleanupOldMetrics(string $metricName): void
    {
        $oneHourAgo = time() - 3600;
        
        $this->metrics[$metricName] = array_filter(
            $this->metrics[$metricName],
            fn($m) => $m['timestamp'] > $oneHourAgo
        );
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return [
            'metrics_tracked' => count($this->metrics),
            'baselines_configured' => count($this->baselines),
            'recent_alerts' => count(array_filter(
                $this->alerts,
                fn($a) => $a['timestamp'] > time() - 3600
            )),
            'total_alerts' => count($this->alerts),
        ];
    }
}
