<?php

namespace App\Providers;

use Core\Support\ServiceProvider;
use DebugBar\Bridge\Cache\CacheCollector;
use DebugBar\Bridge\Cache\TraceableCachePool;
use DebugBar\Bridge\MonologCollector;
use DebugBar\DataCollector\PDO\PDOCollector;
use DebugBar\DataCollector\PDO\TraceablePDO;
use DebugBar\DebugBar;
use DebugBar\StandardDebugBar;
use PDO;

class DebugbarServiceProvider extends ServiceProvider
{
    /**
     * Chỉ đăng ký service provider này nếu APP_ENV là 'development'.
     */
    public function shouldRegister(): bool
    {
        return config('app.env') === 'local';
    }

    public function register(): void
    {
        $this->app->bind(DebugBar::class, function () {
            return new StandardDebugBar();
        });

        $this->app->extend(PDO::class, function (PDO $originalPdo, $app) {
            /** @var StandardDebugBar $debugbar */
            $debugbar = $app->make(DebugBar::class);

            if (!$debugbar->hasCollector('pdo')) {
                $pdoCollector = new PDOCollector();
                $debugbar->addCollector($pdoCollector);
            } else {
                /** @var PDOCollector $pdoCollector */
                $pdoCollector = $debugbar->getCollector('pdo');
            }

            $traceablePdo = new TraceablePDO($originalPdo);

            $traceablePdo->addCollector($pdoCollector);

            $pdoCollector->addConnection($traceablePdo, 'default');

            // *** BaultFrame System-Wide SQL Logging ***
            $queryLogger = $app->make(\App\Logging\QueryLoggerCollector::class);
            $traceablePdo->addCollector($queryLogger);

            return $traceablePdo;
        });

        $this->app->singleton(\DebugBar\Bridge\MonologCollector::class, function ($app) {
            /** @var StandardDebugBar $debugbar */
            $debugbar = $app->make(DebugBar::class);

            if (!$debugbar->hasCollector('monolog')) {
                $debugbar->addCollector(new MonologCollector());
            }

            return $debugbar->getCollector('monolog');
        });

        $this->app->extend(\Psr\Cache\CacheItemPoolInterface::class, function (\Psr\Cache\CacheItemPoolInterface $originalPool, $app) {
            /** @var StandardDebugBar $debugbar */
            $debugbar = $app->make(DebugBar::class);

            if (!$debugbar->hasCollector('cache')) {
                $cacheCollector = new CacheCollector();
                $debugbar->addCollector($cacheCollector);
            } else {
                /** @var CacheCollector $cacheCollector */
                $cacheCollector = $debugbar->getCollector('cache');
            }

            $traceablePool = new TraceableCachePool($originalPool);

            $cacheCollector->addPool($traceablePool);

            return $traceablePool;
        });
    }

    public function boot(): void
    {
        // Đảm bảo các collector cơ bản được thêm vào khi DebugBar được tạo.
        // Điều này rất quan trọng trong môi trường Swoole.
        $this->app->resolving(DebugBar::class, function (StandardDebugBar $debugbar) {
            if (!$debugbar->hasCollector('time')) {
                $debugbar->addCollector(new \DebugBar\DataCollector\TimeDataCollector());
            }
            if (!$debugbar->hasCollector('memory')) {
                $debugbar->addCollector(new \DebugBar\DataCollector\MemoryCollector());
            }
            if (!$debugbar->hasCollector('messages')) {
                $debugbar->addCollector(new \DebugBar\DataCollector\MessagesCollector());
            }
        });
    }
}
