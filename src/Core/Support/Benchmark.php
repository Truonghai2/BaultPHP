<?php

namespace Core\Support;

class Benchmark
{
    protected static array $timers = [];

    public static function start(string $name): void
    {
        static::$timers[$name] = [
            'start_time' => microtime(true),
            'end_time' => 0,
            'start_memory' => memory_get_usage(),
            'end_memory' => 0,
        ];
    }

    public static function stop(string $name): array
    {
        if (!isset(static::$timers[$name])) {
            throw new \Exception("Timer '{$name}' was not started.");
        }

        $timer = &static::$timers[$name];
        $timer['end_time'] = microtime(true);
        $timer['end_memory'] = memory_get_usage();

        return [
            'time' => ($timer['end_time'] - $timer['start_time']) * 1000, // in ms
            'memory' => ($timer['end_memory'] - $timer['start_memory']), // in bytes
            'memory_peak' => memory_get_peak_usage(), // in bytes
        ];
    }

    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = floor(log($bytes, 1024));

        return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
    }
}
