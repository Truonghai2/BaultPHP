<?php

/**
 * BaultPHP Load Tester - k6 Style
 * 
 * Usage: php scripts/load_test.php <url> <vus> <duration>
 * Example: php scripts/load_test.php http://127.0.0.1:9501/ 100 30s
 */

require __DIR__ . '/../vendor/autoload.php';

use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;
use function Swoole\Coroutine\run;

// Configuration
$targetUrl = $argv[1] ?? 'http://127.0.0.1:9501/';
$vus = (int)($argv[2] ?? 100);
$durationStr = $argv[3] ?? '10s';

// Parse duration
$duration = (int)$durationStr;
if (str_ends_with(strtolower($durationStr), 'm')) {
    $duration *= 60;
}

echo "\n🚀 BaultPHP Load Test\n";
echo "==================================================\n";
echo "🎯 Target:   $targetUrl\n";
echo "👥 VUs:      $vus (Concurrency)\n";
echo "⏱️  Duration: {$duration}s\n";
echo "==================================================\n\n";

// Shared State
$stats = [
    'requests' => 0,
    'success' => 0,
    'failed' => 0,
    'total_time' => 0,
    'min_time' => 999999,
    'max_time' => 0,
    'bytes' => 0,
    'errors' => [],
];

$running = true;
$startTime = microtime(true);

run(function () use ($targetUrl, $vus, $duration, &$stats, &$running, &$startTime) {
    $parsedUrl = parse_url($targetUrl);
    $host = $parsedUrl['host'] ?? '127.0.0.1';
    $port = $parsedUrl['port'] ?? 80;
    $path = $parsedUrl['path'] ?? '/';
    if (isset($parsedUrl['query'])) {
        $path .= '?' . $parsedUrl['query'];
    }
    $isSsl = ($parsedUrl['scheme'] ?? 'http') === 'https';

    // Timer to stop test
    Swoole\Timer::after($duration * 1000, function() use (&$running) {
        $running = false;
    });

    // Output stats periodically
    Swoole\Timer::tick(1000, function() use (&$stats, &$startTime, $vus) {
        $now = microtime(true);
        $elapsed = max(0.1, $now - $startTime);
        $rps = $stats['requests'] / $elapsed;
        echo "\r" . sprintf(
            "⏳ Running... %0.1fs | RPS: %0.1f | Req: %d | Fail: %d", 
            $elapsed, 
            $rps, 
            $stats['requests'], 
            $stats['failed']
        );
    });

    $wg = new \Swoole\Coroutine\WaitGroup();

    // Spawn VUs
    for ($i = 0; $i < $vus; $i++) {
        $wg->add();
        Coroutine::create(function () use ($host, $port, $isSsl, $path, &$stats, &$running, $wg) {
            defer(function () use ($wg) { $wg->done(); });

            $client = new Client($host, $port, $isSsl);
            $client->set(['timeout' => 10, 'keep_alive' => true]);
            
            while ($running) {
                $reqStart = microtime(true);
                
                try {
                    // Use GET method explicitly, not HEAD
                    $client->setMethod('GET');
                    $client->get($path);
                    $reqEnd = microtime(true);
                    
                    $duration = $reqEnd - $reqStart;
                    
                    // Update stats (Warning: technically allow race condition for max perf, minimal error margin)
                    $stats['requests']++;
                    
                    if ($client->statusCode >= 200 && $client->statusCode < 400) {
                        $stats['success']++;
                        $stats['bytes'] += strlen($client->body ?? '');
                    } else {
                        $stats['failed']++;
                        // Keep last few errors
                        if (count($stats['errors']) < 10) {
                            $statusCode = $client->statusCode ?? -1;
                            if (!in_array($statusCode, $stats['errors'])) {
                                $stats['errors'][] = $statusCode;
                            }
                        }
                    }

                    $stats['total_time'] += $duration;
                    if ($duration < $stats['min_time']) $stats['min_time'] = $duration;
                    if ($duration > $stats['max_time']) $stats['max_time'] = $duration;
                } catch (\Throwable $e) {
                    $reqEnd = microtime(true);
                    $duration = $reqEnd - $reqStart;
                    $stats['requests']++;
                    $stats['failed']++;
                    if (count($stats['errors']) < 10) {
                        $stats['errors'][] = 'EXCEPTION';
                    }
                    $stats['total_time'] += $duration;
                }
            }
            $client->close();
        });
    }

    $wg->wait();
    Swoole\Timer::clearAll();
});

$endTime = microtime(true);
$totalDuration = $endTime - $startTime;
$avgLatency = ($stats['requests'] > 0) ? ($stats['total_time'] / $stats['requests']) : 0;
$rps = $stats['requests'] / $totalDuration;
$networkThroughput = ($stats['bytes'] / 1024 / 1024) / $totalDuration; // MB/s

echo "\n\n🏁 Test Finished\n";
echo "==================================================\n";
echo "Execution Time:      " . number_format($totalDuration, 2) . "s\n";
echo "Total Requests:      " . number_format($stats['requests']) . "\n";
echo "Requests/Sec (RPS):  " . number_format($rps, 2) . "\n";
echo "Throughput:          " . number_format($networkThroughput, 2) . " MB/s\n";
echo "--------------------------------------------------\n";
echo "Success:             " . number_format($stats['success']) . "\n";
echo "Failed:              " . number_format($stats['failed']) . "\n";
if (!empty($stats['errors'])) {
    echo "Sample Errors:       " . implode(', ', array_unique($stats['errors'])) . "\n";
}
echo "--------------------------------------------------\n";
echo "Latency (Avg):       " . number_format($avgLatency * 1000, 2) . " ms\n";
echo "Latency (Min):       " . number_format($stats['min_time'] * 1000, 2) . " ms\n";
echo "Latency (Max):       " . number_format($stats['max_time'] * 1000, 2) . " ms\n";
echo "==================================================\n";
