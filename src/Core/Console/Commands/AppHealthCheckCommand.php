<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;
use Core\WebSocket\CentrifugoAPIService;
use PDO;
use Throwable;

class AppHealthCheckCommand extends BaseCommand
{
    /**
     * Constructor cho phép inject các dependency.
     * DI Container sẽ tự động cung cấp các instance của PDO và CentrifugoAPIService.
     */
    public function __construct(
        private ?PDO $pdo,
        private ?CentrifugoAPIService $centrifugo
    ) {
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
        if (!$this->pdo) {
            $this->line('[<fg=yellow>SKIP</>] Database service (PDO) is not configured or could not be resolved.');
            return;
        }

        try {
            $this->pdo->query('SELECT 1');
            $this->line('[<fg=green>OK</>] Database connection is healthy.');
        } catch (Throwable $e) {
            $this->line('[<fg=red>FAIL</>] Database connection failed: ' . $e->getMessage());
        }
    }

    private function checkCentrifugoConnection(): void
    {
        if (!$this->centrifugo) {
            $this->line('[<fg=yellow>SKIP</>] Centrifugo service is not configured.');
            return;
        }

        $isHealthy = $this->centrifugo->healthCheck();
        $status = $isHealthy ? '[<fg=green>OK</>]' : '[<fg=red>FAIL</>]';
        $this->line("{$status} Centrifugo API connection is " . ($isHealthy ? 'healthy.' : 'unhealthy.'));
    }
}