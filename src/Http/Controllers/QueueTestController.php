<?php

namespace App\Http\Controllers;

use Core\Http\Controller;
use Core\Http\Response;
use Core\Queue\Jobs\LogMessageJob;
use Core\Queue\Jobs\SendWelcomeEmailJob;
use Core\Queue\QueueManager;
use Core\Routing\Attributes\Route;

class QueueTestController extends Controller
{
    #[Route('/queue-test', method: 'GET')]
    public function dispatchJob(QueueManager $queueManager): Response
    {
        $logJobMessage = 'This is a LogMessageJob dispatched at ' . date('Y-m-d H:i:s');
        $logJob = new LogMessageJob($logJobMessage);
        $success1 = $queueManager->dispatch($logJob);

        $emailJob = new SendWelcomeEmailJob('test@example.com', 'John Doe');
        $success2 = $queueManager->dispatch($emailJob);

        return (new Response())->json([
            'dispatched' => ['log_job' => $success1, 'email_job' => $success2],
            'message' => 'Two different jobs have been dispatched.',
        ]);
    }
}
