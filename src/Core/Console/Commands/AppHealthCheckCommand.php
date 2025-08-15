<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;
use Core\WebSocket\CentrifugoAPIService;
use Throwable;

class AppHealthCheckCommand extends BaseCommand
{
    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Signature của lệnh, dùng để gọi từ terminal.
     */
    public function signature(): string
    {
        return 'app:health-check';
    }

    /**
     * Mô tả của lệnh, sẽ hiển thị khi chạy `php cli list`.
     */
    public function description(): string
    {
        return 'Checks the health of application services like database and Centrifugo.';
    }

    /**
     * Logic chính của lệnh sẽ được đặt ở đây.
     * @return int
     */
    public function handle(): int
    {
        $this->comment('Application Health Check');

        $this->checkDatabaseConnection();
        $this->checkCentrifugoConnection();

        $this->info('Health check completed.');
        return 0;
    }

    private function checkDatabaseConnection(): void
    {
        try {
            // Resolve PDO safely inside the method to avoid boot-time errors
            // if the database isn't ready or configured.
            /** @var \PDO $pdo */
            $pdo = $this->app->make(\PDO::class);
            $pdo->query('SELECT 1');
            $this->line('[<fg=green>OK</>] Database connection is healthy.');
        } catch (Throwable $e) {
            // This will catch both resolution errors and connection errors.
            $this->line('[<fg=red>FAIL</>] Database connection failed: ' . $e->getMessage());
        }
    }

    private function checkCentrifugoConnection(): void
    {
        try {
            // Resolve CentrifugoAPIService safely inside the method. This prevents the
            // "API key not configured" error from halting the entire console application
            // when just listing commands.
            /** @var CentrifugoAPIService $centrifugo */
            $centrifugo = $this->app->make(CentrifugoAPIService::class);
            $isHealthy = $centrifugo->healthCheck();
            $status = $isHealthy ? '[<fg=green>OK</>]' : '[<fg=red>FAIL</>]';
            $this->line("{$status} Centrifugo API connection is " . ($isHealthy ? 'healthy.' : 'unhealthy.'));
        } catch (Throwable $e) {
            $this->line('[<fg=yellow>SKIP</>] Centrifugo service check failed: ' . $e->getMessage());
        }
    }
}
