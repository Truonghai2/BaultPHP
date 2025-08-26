<?php

namespace App\Providers;

use Core\Server\SwooleServer;
use Core\Support\ServiceProvider;
use Laminas\Diactoros\ServerRequestFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\RPC\Codec\JsonCodec;
use Spiral\Goridge\RPC\CodecInterface;
use Spiral\Goridge\StreamRelay;
use Swoole\Http\Request as SwooleRequest;

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

        $this->app->bind(ServerRequestInterface::class, function ($app) {
            if ($app->has(SwooleRequest::class)) {
                $swooleRequest = $app->get(SwooleRequest::class);
                $bridge = new \Core\Server\SwoolePsr7Bridge();
                return $bridge->toPsr7Request($swooleRequest);
            }

            return ServerRequestFactory::fromGlobals();
        });

        $this->app->bind(RequestInterface::class, function ($app) {
            return $app->make(ServerRequestInterface::class);
        });

        $this->app->singleton(SwooleServer::class, function ($app) {
            return new SwooleServer($app);
        });
    }
}
