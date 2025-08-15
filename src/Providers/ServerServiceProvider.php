<?php

namespace App\Providers;

use Core\Console\Commands\Swoole\StartSwooleCommand;
use Core\Server\SwooleServer;
use Core\Support\ServiceProvider;
use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\RPC\Codec\JsonCodec;
use Spiral\Goridge\RPC\CodecInterface;
use Spiral\Goridge\StreamRelay;

class ServerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the RelayInterface to a concrete implementation.
        // The Swoole server communicates with the PHP workers via STDIN/STDOUT.
        // StreamRelay is the standard implementation for this.
        // We register it as a singleton to ensure the same relay is used throughout the request lifecycle.
        $this->app->singleton(RelayInterface::class, function () {
            // php://stdin and php://stdout are special wrappers to access the standard I/O streams.
            return new StreamRelay(STDIN, STDOUT);
        });

        // Bind the CodecInterface to the standard JSON codec implementation.
        // This tells the RPC service how to serialize and deserialize messages.
        // We can use a simple class binding here as JsonCodec has no complex dependencies.
        $this->app->singleton(CodecInterface::class, JsonCodec::class);

        // Đăng ký SwooleServer như một singleton
        $this->app->singleton(SwooleServer::class, function ($app) {
            return new SwooleServer($app);
        });

        // $this->registerCommands();
    }

    // protected function registerCommands(): void
    // {
    //     if ($this->app->runningInConsole()) {
    //         $this->app->singleton(StartSwooleCommand::class);
    //         // Tag lệnh để Console Application có thể tìm thấy
    //         $this->app->tag(StartSwooleCommand::class, 'console.command');
    //     }
    // }
}
