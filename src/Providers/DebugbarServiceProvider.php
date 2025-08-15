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
        // 1. Đăng ký DebugBar như một singleton cho mỗi request.
        // Sử dụng `bind` thay vì `singleton` để đảm bảo mỗi request trong Swoole
        // có một instance DebugBar riêng, tránh rò rỉ dữ liệu giữa các request.
        $this->app->bind(DebugBar::class, function () {
            return new StandardDebugBar();
        });

        // 2. "Trang trí" (decorate) binding PDO mặc định.
        // Kỹ thuật này cho phép chúng ta thay thế một service đã đăng ký
        // bằng một phiên bản khác (wrapper) mà không làm ảnh hưởng đến code khác.
        $this->app->extend(PDO::class, function (PDO $originalPdo, $app) {
            // Lấy instance DebugBar cho request hiện tại.
            /** @var StandardDebugBar $debugbar */
            $debugbar = $app->make(DebugBar::class);

            // Nếu chưa có PDOCollector, hãy tạo và thêm nó vào.
            if (!$debugbar->hasCollector('pdo')) {
                $pdoCollector = new PDOCollector();
                $debugbar->addCollector($pdoCollector);
            } else {
                /** @var PDOCollector $pdoCollector */
                $pdoCollector = $debugbar->getCollector('pdo');
            }

            // Tạo wrapper TraceablePdo, bao bọc PDO gốc.
            $traceablePdo = new TraceablePDO($originalPdo);

            // Gắn collector vào wrapper để nó bắt đầu theo dõi.
            $traceablePdo->addCollector($pdoCollector);

            // Thêm TraceablePdo vào collector để nó có thể hiển thị thông tin kết nối.
            // Tên 'default' là tùy ý, bạn có thể đặt tên theo connection.
            $pdoCollector->addConnection($traceablePdo, 'default');

            return $traceablePdo;
        });

        // 3. Tích hợp Log Collector (Monolog)
        // Giả sử framework của bạn bind LoggerInterface vào một instance của Monolog\Logger
        $this->app->extend(\Psr\Log\LoggerInterface::class, function (\Monolog\Logger $originalLogger, $app) {
            /** @var StandardDebugBar $debugbar */
            $debugbar = $app->make(DebugBar::class);

            if (!$debugbar->hasCollector('monolog')) {
                // Tạo collector cho Monolog
                $logCollector = new MonologCollector();
                $debugbar->addCollector($logCollector);
            } else {
                /** @var MonologCollector $logCollector */
                $logCollector = $debugbar->getCollector('monolog');
            }

            // MonologCollector cũng là một Handler, nên ta có thể push nó vào logger
            // để nó lắng nghe tất cả các log được ghi.
            $originalLogger->pushHandler($logCollector);

            return $originalLogger;
        });

        // 4. Tích hợp Cache Collector (Symfony Cache / PSR-6)
        // Giả sử framework của bạn bind CacheItemPoolInterface
        $this->app->extend(\Psr\Cache\CacheItemPoolInterface::class, function (\Psr\Cache\CacheItemPoolInterface $originalPool, $app) {
            /** @var StandardDebugBar $debugbar */
            $debugbar = $app->make(DebugBar::class);

            if (!$debugbar->hasCollector('cache')) {
                // Tạo collector cho Cache
                $cacheCollector = new CacheCollector();
                $debugbar->addCollector($cacheCollector);
            } else {
                /** @var CacheCollector $cacheCollector */
                $cacheCollector = $debugbar->getCollector('cache');
            }

            // Bọc (wrap) pool gốc bằng một pool có thể theo dõi (traceable)
            $traceablePool = new TraceableCachePool($originalPool);

            // Thêm pool này vào collector để bắt đầu theo dõi các hoạt động cache
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
