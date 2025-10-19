<?php

namespace App\Providers;

use App\Logging\QueryLoggerCollector;
use Core\Debug\LoggingTraceablePDO;
use Core\Support\ServiceProvider;
use PDO;
use ReflectionProperty;

class QueryLoggingServiceProvider extends ServiceProvider
{
    /**
     * Chỉ đăng ký provider này khi logging query được bật trong config.
     */
    public function shouldRegister(): bool
    {
        return (bool) config('logging.query.enabled', false);
    }

    public function register(): void
    {
        $this->app->singleton(QueryLoggerCollector::class, function ($app) {
            $channel = config('logging.query.channel', 'stack');
            return new QueryLoggerCollector($app->make('log')->channel($channel));
        });

        $this->app->extend(PDO::class, function (PDO $pdo, $app) {
            if ($pdo instanceof \DebugBar\DataCollector\PDO\TraceablePDO) {
                try {
                    $reflection = new ReflectionProperty($pdo, 'pdo');
                    $reflection->setAccessible(true);
                    $originalPdo = $reflection->getValue($pdo);
                } catch (\ReflectionException) {
                    $originalPdo = $pdo;
                }
            } else {
                $originalPdo = $pdo;
            }

            return new LoggingTraceablePDO($originalPdo, $app->make(QueryLoggerCollector::class));
        });
    }
}
