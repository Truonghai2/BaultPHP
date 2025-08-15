<?php

namespace Http\Controllers;

use App\Tasks\GenerateReportTask;
use Core\Server\SwooleServer;
use Http\ResponseFactory;

class ReportController
{
    /**
     * Dispatch a task to generate a report.
     * This endpoint will return immediately while the task runs in the background.
     */
    public function generate(SwooleServer $server, ResponseFactory $responseFactory): \Http\JsonResponse
    {
        // In a real app, you'd get the user ID from the authenticated user.
        $userId = 123;
        $reportType = 'monthly_sales';

        $task = new GenerateReportTask($userId, $reportType);

        $taskId = $server->dispatchTask($task);

        if ($taskId === false) {
            return $responseFactory->json(['message' => 'Failed to dispatch the report generation task.'], 500);
        }

        return $responseFactory->json([
            'message' => 'Report generation has been started in the background.',
            'task_id' => $taskId,
        ]);
    }
}
