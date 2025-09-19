<?php

namespace App\Providers;

use Core\Application;
use Core\Logging\LogManager;
use Core\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class LoggingServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('log', function (Application $app) {
            return new LogManager($app);
        });

        $this->app->alias('log', LogManager::class);
        $this->app->alias('log', LoggerInterface::class);

        $this->app->singleton('log.sync', function (Application $app) {
            $logManager = new LogManager($app);
            $syncChannel = $this->getSyncChannel($app);
            return $logManager->channel($syncChannel);
        });
    }

    /**
     * Lấy tên kênh log đồng bộ.
     *
     * Nó kiểm tra cấu hình kênh mặc định. Nếu mặc định là 'stack' hoặc 'async',
     * nó sẽ tìm kênh không-phải-async đầu tiên trong stack. Mặc định là 'single'.
     *
     * @param Application $app
     * @return string
     */
    private function getSyncChannel(Application $app): string
    {
        $config = $app->make('config');
        $defaultChannel = $config->get('logging.default');

        if ($defaultChannel === 'default_stack') {
            $stackChannels = $config->get('logging.channels.default_stack.channels', []);
            foreach ($stackChannels as $channel) {
                $driver = $config->get("logging.channels.{$channel}.driver");
                if ($driver !== 'async') {
                    return $channel;
                }
            }
        }

        $defaultDriver = $config->get("logging.channels.{$defaultChannel}.driver");
        if ($defaultDriver !== 'async') {
            return $defaultChannel;
        }

        return 'single';
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['log', 'log.sync', LoggerInterface::class, LogManager::class];
    }
}
