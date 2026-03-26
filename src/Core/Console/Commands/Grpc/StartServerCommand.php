<?php

declare(strict_types=1);

namespace Core\Console\Commands\Grpc;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\RPC\GrpcServiceManager;

/**
 * Start gRPC server
 */
class StartServerCommand extends BaseCommand
{
    public function __construct(
        Application $app,
        private readonly GrpcServiceManager $grpcManager,
    ) {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'grpc:serve {--host= : Server host} {--port= : Server port}';
    }

    public function description(): string
    {
        return 'Start the gRPC server';
    }

    public function handle(): int
    {
        if (!$this->grpcManager->isAvailable()) {
            $this->io->error('gRPC extension is not available. Please install grpc PHP extension.');
            $this->io->writeln('Installation: pecl install grpc');
            return 1;
        }

        $this->io->title('Starting gRPC Server');

        $host = $this->option('host') ?? config('grpc.server.host', '0.0.0.0');
        $port = (int)($this->option('port') ?? config('grpc.server.port', 50051));

        $server = $this->grpcManager->getServer();

        // Register services from config
        $services = config('grpc.server.services', []);
        if (!empty($services)) {
            $this->io->info('Registering services...');
            $server->registerServices($services);
        }

        $address = "{$host}:{$port}";
        $this->io->info("Starting server on {$address}...");
        $this->io->writeln("Registered services: " . implode(', ', $server->getServices()));

        try {
            $server->start();
            $this->io->success("gRPC server started on {$address}");

            // Keep server running
            while ($server->isRunning()) {
                sleep(1);
            }

            return 0;
        } catch (\Throwable $e) {
            $this->io->error("Failed to start server: {$e->getMessage()}");
            return 1;
        }
    }
}
