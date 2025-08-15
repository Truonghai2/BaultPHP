<?php

namespace App\Providers;

use Core\Application;
use Core\Logging\RequestProcessor;
use Core\Support\ServiceProvider;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\WebProcessor;
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
        $this->app->singleton('log', function (Application $app): \Core\Logging\LogManager {
            // Khởi tạo LogManager.
            $manager = new \Core\Logging\LogManager($app);

            // Đẩy các processor toàn cục vào manager.
            // Manager sẽ tự động áp dụng các processor này cho MỌI kênh log được tạo ra.
            $manager->pushProcessor(new ProcessIdProcessor());
            $manager->pushProcessor(new WebProcessor());
            $manager->pushProcessor($this->app->make(RequestProcessor::class));

            return $manager;
        });

        $this->app->alias('log', LoggerInterface::class);
    }

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot(): void
    {
        // Không cần làm gì ở đây, nhưng giữ lại để tuân thủ cấu trúc.
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['log', LoggerInterface::class];
    }
}
