<?php

namespace App\Providers;

use Core\Support\ServiceProvider;
use InvalidArgumentException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

class LoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind LoggerInterface vào container dưới dạng singleton.
        // Điều này đảm bảo toàn bộ ứng dụng chỉ sử dụng một logger instance duy nhất.
        $this->app->singleton(LoggerInterface::class, function ($app) {
            $config = $app->make('config')->get('logging');
            $defaultChannel = $config['default'] ?? 'file';

            if (empty($config['channels'][$defaultChannel])) {
                throw new InvalidArgumentException("Kênh log mặc định [{$defaultChannel}] chưa được định nghĩa.");
            }

            $channelConfig = $config['channels'][$defaultChannel];
            $logger = new Logger($defaultChannel);

            // Processor này cho phép sử dụng cú pháp {placeholder} trong message log.
            $logger->pushProcessor(new PsrLogMessageProcessor());

            // Hiện tại chỉ hỗ trợ driver 'single' (ghi ra một file duy nhất).
            if ($channelConfig['driver'] === 'single') {
                $path = $channelConfig['path']; // Giả định helper storage_path() đã được thực thi
                $level = $channelConfig['level'] ?? 'debug';
                $logger->pushHandler(new StreamHandler($path, $level));
            } else {
                throw new InvalidArgumentException("Driver log [{$channelConfig['driver']}] chưa được hỗ trợ.");
            }

            return $logger;
        });

        // Tạo một alias 'log' để Facade có thể sử dụng.
        $this->app->alias(LoggerInterface::class, 'log');
    }
}
