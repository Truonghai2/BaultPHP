<?php

namespace Core\Console\Commands\Grpc;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\RPC\Grpc\Server\GrpcServer;

/**
 * Start gRPC Server Command.
 * 
 * Usage: php artisan serve:grpc
 */
class ServeGrpcCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'serve:grpc 
                {--host=0.0.0.0 : The host to bind to}
                {--port=50051 : The port to listen on}';
    }

    public function description(): string
    {
        return 'Start the gRPC server';
    }

    public function handle(): int
    {
        if (!extension_loaded('grpc')) {
            $this->error('gRPC extension is not loaded!');
            $this->info('Install: pecl install grpc');
            return 1;
        }

        $host = $this->option('host');
        $port = $this->option('port');

        $this->info("Starting gRPC server on {$host}:{$port}...");
        $this->info('Press Ctrl+C to stop');
        $this->line('');

        try {
            $server = $this->app->make(GrpcServer::class);
            $server->start();

        } catch (\Throwable $e) {
            $this->error('Failed to start gRPC server:');
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
