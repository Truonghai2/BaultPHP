<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\ResponseFactory;
use Core\Routing\Attributes\Route;
use Core\WebSocket\CentrifugoAPIService;
use Http\JsonResponse;
use Modules\User\Infrastructure\Models\User;
use Psr\Http\Message\ServerRequestInterface;

#[Route('/api/admin/notifications')]
class NotificationController
{
    private CentrifugoAPIService $centrifugo;

    public function __construct(CentrifugoAPIService $centrifugo)
    {
        // CentrifugoAPIService giờ đây được tự động inject bởi DI Container.
        $this->centrifugo = $centrifugo;
    }

    /**
     * Gửi một thông báo thử nghiệm đến một người dùng cụ thể bằng ID của họ.
     */
    #[Route('/user/{userId}', method: 'POST')]
    public function sendToUser(int $userId, ServerRequestInterface $request, ResponseFactory $responseFactory): JsonResponse
    {
        $user = User::find($userId);
        if (!$user) {
            return $responseFactory->json(['message' => 'User not found.'], 404);
        }

        // Dữ liệu bạn muốn gửi đi
        $payload = [
            'event' => 'user_specific_alert',
            'data' => ['greeting' => "Hello, {$user->name}! This message is just for you."],
        ];

        // Centrifugo sử dụng một channel đặc biệt cho từng user, thường có dạng #<user_id>.
        // Client sẽ cần subscribe vào channel này để nhận tin nhắn.
        $userChannel = "#{$user->id}";

        $success = $this->centrifugo->publish($userChannel, $payload);

        if ($success) {
            return $responseFactory->json(['message' => "Notification sent to user {$userId}."]);
        }

        return $responseFactory->json(['message' => 'Failed to send notification.'], 500);
    }

    /**
     * Gửi một thông báo đến tất cả các client đang lắng nghe trên một channel công khai.
     */
    #[Route('/broadcast', method: 'POST')]
    public function broadcastToAll(ServerRequestInterface $request, ResponseFactory $responseFactory): JsonResponse
    {
        $payload = [
            'event' => 'global_announcement',
            'data' => ['text' => $request->get('message', 'This is a broadcast message for everyone!')],
        ];

        // Publish đến một channel công khai, ví dụ 'news'
        $success = $this->centrifugo->publish('news', $payload);

        if ($success) {
            return $responseFactory->json(['message' => 'Broadcast notification sent.']);
        }

        return $responseFactory->json(['message' => 'Failed to send broadcast notification.'], 500);
    }
}
