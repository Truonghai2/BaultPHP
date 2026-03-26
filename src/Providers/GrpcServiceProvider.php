<?php

namespace App\Providers;

use Core\ServiceProvider;
use Core\RPC\Grpc\Server\GrpcServer;
use Core\RPC\Grpc\Client\GrpcClient;
use Core\RPC\Grpc\Middleware\{AuthenticationMiddleware, LoggingMiddleware};
use Modules\Todo\RPC\TodoServiceImpl;
use Modules\User\RPC\UserServiceImpl;

/**
 * gRPC Service Provider.
 * 
 * Registers gRPC server, services, and middleware.
 */
class GrpcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register gRPC Server
        $this->app->singleton(GrpcServer::class, function($app) {
            return new GrpcServer(
                host: config('grpc.host', '0.0.0.0'),
                port: config('grpc.port', 50051),
                logger: $app->make('logger'),
                options: config('grpc.options', [])
            );
        });

        // Register gRPC Client
        $this->app->singleton(GrpcClient::class, function($app) {
            return new GrpcClient(
                logger: $app->make('logger'),
                defaultOptions: config('grpc.client', [])
            );
        });

        // Register service implementations
        $this->app->singleton(TodoServiceImpl::class);
        $this->app->singleton(UserServiceImpl::class);
    }

    public function boot(): void
    {
        if (!config('grpc.enabled', false)) {
            return; // gRPC disabled
        }

        $server = $this->app->make(GrpcServer::class);

        // Register middleware
        $server->addMiddleware(
            $this->app->make(LoggingMiddleware::class)
        );

        if (config('grpc.auth.enabled', true)) {
            $server->addMiddleware(
                $this->app->make(AuthenticationMiddleware::class)
            );
        }

        // Register services
        $server->registerService(
            \Todo\TodoServiceClient::class,
            $this->app->make(TodoServiceImpl::class)
        );

        $server->registerService(
            \User\UserServiceClient::class,
            $this->app->make(UserServiceImpl::class)
        );

        // Start gRPC server (in separate process/worker)
        if (php_sapi_name() === 'cli' && isset($_SERVER['GRPC_SERVER'])) {
            $server->start();
        }
    }
}
