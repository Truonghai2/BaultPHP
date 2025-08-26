<?php

namespace Http\Controllers\Admin;

use Core\Routing\Attributes\Route;
use Core\WebSocket\CentrifugoAPIService;
use Http\ResponseFactory;
use Modules\User\Infrastructure\Models\User;
use Psr\Http\Message\ServerRequestInterface as Request;

#[Route('/api/admin/notifications')]
class NotificationController
{
    private CentrifugoAPIService $centrifugo;

    public function __construct(CentrifugoAPIService $centrifugo)
    {
        $this->centrifugo = $centrifugo;
    }

    #[Route('/user/{userId}', method: 'POST')]
    public function sendToUser(int $userId, Request $request, ResponseFactory $responseFactory): \Http\JsonResponse
    {
        $user = User::find($userId);
        if (!$user) {
            return $responseFactory->json(['message' => 'User not found.'], 404);
        }

        $payload = [
            'event' => 'user_specific_alert',
            'data' => ['greeting' => "Hello, {$user->name}! This message is just for you."],
        ];

        $userChannel = "#{$user->id}";

        $success = $this->centrifugo->publish($userChannel, $payload);

        if ($success) {
            return $responseFactory->json(['message' => "Notification sent to user {$userId}."]);
        }

        return $responseFactory->json(['message' => 'Failed to send notification.'], 500);
    }

    #[Route('/broadcast', method: 'POST')]
    public function broadcastToAll(Request $request, ResponseFactory $responseFactory): \Http\JsonResponse
    {
        $payload = [
            'event' => 'global_announcement',
            'data' => ['text' => $request->get('message', 'This is a broadcast message for everyone!')],
        ];

        $success = $this->centrifugo->publish('news', $payload);

        if ($success) {
            return $responseFactory->json(['message' => 'Broadcast notification sent.']);
        }

        return $responseFactory->json(['message' => 'Failed to send broadcast notification.'], 500);
    }
}
