<?php

namespace App\Http\Controllers;

use App\Http\ResponseFactory;
use App\Tasks\GenerateReportTask;
use Core\Server\SwooleServer;

class ReportController
{
    public function generate(SwooleServer $server, ResponseFactory $responseFactory): \App\Http\JsonResponse
    {
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
