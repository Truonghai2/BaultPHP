<?php

namespace App\Tasks;

use Core\Contracts\Task\Task;
use Psr\Log\LoggerInterface;

/**
 * An example task that simulates a long-running report generation.
 */
class GenerateReportTask implements Task
{
    public function __construct(private int $userId, private string $reportType)
    {
    }

    /**
     * This method is executed in the Task Worker.
     */
    public function handle()
    {
        /** @var LoggerInterface $logger */
        $logger = app('log.task_writer');

        $logger->info("Generating '{$this->reportType}' report for user #{$this->userId}...");

        // Simulate a long-running, blocking operation like generating a large file.
        sleep(5);

        $reportPath = "/storage/reports/report_{$this->reportType}_{$this->userId}_" . time() . '.pdf';

        $logger->info("Report generation complete for user #{$this->userId}: {$reportPath}");

        return $reportPath;
    }
}
