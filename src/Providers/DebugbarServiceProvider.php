<?php

namespace App\Providers;

use Core\Auth\AuthManager;
use Core\Contracts\Session\SessionInterface;
use Core\Debug\AuthCollector;
use Core\Debug\CacheCollector;
use Core\Debug\EventCollector;
use Core\Debug\GuzzleCollector;
use Core\Debug\GuzzleMiddleware;
use Core\Debug\SessionCollector;
use Core\ORM\Connection;
use Core\Support\ServiceProvider;
use DebugBar\Bridge\MonologCollector;
use DebugBar\DataCollector\ConfigCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\PDO\PDOCollector;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DataCollector\RequestDataCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar;
use DebugBar\StandardDebugBar;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class DebugbarServiceProvider extends ServiceProvider
{
    /**
     * only register this service provider if APP_DEBUG is true.
     */
    public function shouldRegister(): bool
    {
        return (bool) config('app.debug', false) && !$this->app->runningInConsole();
    }

    public function register(): void
    {
        $this->registerDebugBar();

        $this->registerCollectors();
        $this->decorateServices();
    }

    public function boot(): void
    {
        if (!$this->shouldRegister()) {
            return;
        }

        // Đăng ký middleware để tự động chèn DebugBar vào response HTML.
        /** @var \Core\Contracts\Http\Kernel $kernel */
        $kernel = $this->app->make(\Core\Contracts\Http\Kernel::class);
        $kernel->pushMiddlewareToGroup('web', \App\Http\Middleware\InjectDebugbarMiddleware::class);

        /** @var DebugBar $debugbar */
        $debugbar = $this->app->make(DebugBar::class);
        $this->addCollectorsToDebugbar($debugbar);

        $this->setupRenderer($debugbar);
    }

    /**
     * Cấu hình renderer và đăng ký listener để chèn debugbar vào response.
     */
    protected function setupRenderer(DebugBar $debugbar): void
    {
        $renderer = $debugbar->getJavascriptRenderer();
        $renderer->setAjaxHandlerClass('PhpDebugBar.AjaxHandler');
        $renderer->setAjaxHandlerAutoShow(true);
    }

    /**
     * Đăng ký instance chính của DebugBar.
     */
    protected function registerDebugBar(): void
    {
        $this->app->singleton(DebugBar::class, function () {
            return new StandardDebugBar();
        });
        $this->app->alias(DebugBar::class, 'debugbar');
    }

    /**
     * register collectors .
     */
    protected function registerCollectors(): void
    {
        $this->app->singleton(PhpInfoCollector::class);
        $this->app->singleton(MessagesCollector::class, fn ($app) => new MonologCollector($app->make(LoggerInterface::class)));
        $this->app->singleton(RequestDataCollector::class);
        $this->app->singleton(TimeDataCollector::class);
        $this->app->singleton(MemoryCollector::class);
        $this->app->singleton(PDOCollector::class);
        $this->app->singleton(MonologCollector::class, fn ($app) => $app->make(MessagesCollector::class));
        $this->app->singleton(CacheCollector::class);
        $this->app->singleton(GuzzleCollector::class);
        $this->app->singleton(GuzzleMiddleware::class);
        $this->app->singleton(SessionCollector::class);
        $this->app->singleton(EventCollector::class);
        $this->app->singleton(AuthCollector::class);
    }

    /**
     * Add all registered collectors to the DebugBar instance.
     * This ensures we use the same singleton instances that other services are using.
     */
    protected function addCollectorsToDebugbar(DebugBar $debugbar): void
    {
        $debugbar->addCollector($this->app->make(PhpInfoCollector::class));
        $debugbar->addCollector($this->app->make(MessagesCollector::class));
        $debugbar->addCollector($this->app->make(RequestDataCollector::class));
        $debugbar->addCollector($this->app->make(TimeDataCollector::class));
        $debugbar->addCollector($this->app->make(MemoryCollector::class));
        $debugbar->addCollector($this->app->make(PDOCollector::class));
        $debugbar->addCollector($this->app->make(CacheCollector::class));
        $debugbar->addCollector($this->app->make(GuzzleCollector::class));
        $debugbar->addCollector($this->app->make(EventCollector::class));

        if ($this->app->bound(AuthManager::class)) {
            $debugbar->addCollector($this->app->make(AuthCollector::class));
        }

        if ($this->app->bound(SessionInterface::class)) {
            $debugbar->addCollector($this->app->make(SessionCollector::class));
        }

        $configCollector = new ConfigCollector();
        $configCollector->setData($this->app->make('config')->all());
        $debugbar->addCollector($configCollector);
    }

    /**
     *
     */
    protected function decorateServices(): void
    {
        // Bọc MonologCollector để gửi log real-time
        $this->app->extend(MonologCollector::class, function (MonologCollector $collector, $app) {
            $wsManager = $app->make(\Core\WebSocket\WebSocketManager::class);
            $collector->setLogger(new \Core\Debug\RealtimeMonologProxy($collector->getLogger(), $wsManager));
            return $collector;
        });

        $this->app->extend(GuzzleClient::class, function (GuzzleClient $client, $app) {
            $config = $client->getConfig();
            /** @var HandlerStack $handler */
            $handler = $config['handler'] ?? HandlerStack::create();
            // GuzzleMiddleware đã có sẵn, nó sẽ gọi collector, chúng ta cần sửa collector

            $traceMiddleware = $app->make(GuzzleMiddleware::class);

            $handler->push($traceMiddleware, 'debugbar');

            return new GuzzleClient(['handler' => $handler] + $config);
        });

        // Bọc Connection để gửi query real-time
        $this->app->extend(Connection::class, function (Connection $connection, $app) {
            if ($app->bound('debugbar')) {
                /** @var DebugBar $debugbar */
                $debugbar = $app->make('debugbar');
                /** @var PDOCollector $pdoCollector */
                $pdoCollector = $debugbar->getCollector('pdo');
                $wsManager = $app->make(\Core\WebSocket\WebSocketManager::class);
                return new \Core\Debug\RealtimeTraceableConnection($connection, $pdoCollector, $wsManager);
            }
            return $connection;
        });
    }
}
