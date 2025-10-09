<?php

namespace App\Providers;

use Core\Auth\AuthManager;
use Core\Debug\AuthCollector;
use Core\Debug\CacheCollector;
use Core\Debug\EventCollector;
use Core\Debug\GuzzleCollector;
use Core\Debug\GuzzleMiddleware;
use Core\Debug\SessionCollector;
use Core\Debug\TraceableConnection;
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
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class DebugbarServiceProvider extends ServiceProvider
{
    /**
     * only register this service provider if APP_DEBUG is true.
     */
    public function shouldRegister(): bool
    {
        return false;
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

        /** @var DebugBar $debugbar */
        $debugbar = $this->app->make(DebugBar::class);

        $debugbar->addCollector($this->app->make(PhpInfoCollector::class));
        $debugbar->addCollector($this->app->make(MessagesCollector::class));
        $debugbar->addCollector($this->app->make(RequestDataCollector::class));
        $debugbar->addCollector($this->app->make(TimeDataCollector::class));
        $debugbar->addCollector($this->app->make(MemoryCollector::class));
        $debugbar->addCollector($this->app->make(PDOCollector::class));
        $debugbar->addCollector($this->app->make(MonologCollector::class));
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

        if ($this->app->bound(LoggerInterface::class)) {
            /** @var MonologCollector $monologCollector */
            $monologCollector = $debugbar->getCollector('messages');

            if ($monologCollector instanceof MonologCollector) {
                $monologCollector->setLogger($this->app->make(LoggerInterface::class));
            }
        }

        $this->setupRenderer($debugbar);
    }

    /**
     * Cấu hình renderer và đăng ký listener để chèn debugbar vào response.
     */
    protected function setupRenderer(DebugBar $debugbar): void
    {
        $renderer = $debugbar->getJavascriptRenderer();
        $renderer->setAjaxHandlerClass(false);

        // Đảm bảo dữ liệu được thu thập ngay cả khi không có request AJAX
        if (!$this->app->runningInConsole()) {
            $this->app->booted(fn () => $debugbar->collect());
        }
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
        $this->app->singleton(MessagesCollector::class, fn () => new MonologCollector());
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
     *
     */
    protected function decorateServices(): void
    {
        $this->app->extend(GuzzleClient::class, function (GuzzleClient $client, $app) {
            $config = $client->getConfig();
            /** @var HandlerStack $handler */
            $handler = $config['handler'] ?? HandlerStack::create();

            $traceMiddleware = $app->make(GuzzleMiddleware::class);

            $handler->push($traceMiddleware, 'debugbar');

            return new GuzzleClient(['handler' => $handler] + $config);
        });

        $this->app->extend(Connection::class, function (Connection $connection, $app) {
            if ($app->bound('debugbar')) {
                /** @var DebugBar $debugbar */
                $debugbar = $app->make('debugbar');
                /** @var PDOCollector $pdoCollector */
                $pdoCollector = $debugbar->getCollector('pdo');
                return new TraceableConnection($connection, $pdoCollector);
            }
            return $connection;
        });
    }
}
