<?php

namespace Http\Controllers\Admin;

use Core\WebSocket\CentrifugoAPIService;
use Http\Request;
use Core\Routing\Attributes\Route;
use Http\Response;
use Modules\User\Infrastructure\Models\User;

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
    public function sendToUser(int $userId, Request $request): Response
    {
        $user = User::find($userId);
        if (!$user) {
            return (new Response())->json(['message' => 'User not found.'], 404);
        }

        // Dữ liệu bạn muốn gửi đi
        $payload = [
            'event' => 'user_specific_alert',
            'data' => ['greeting' => "Hello, {$user->name}! This message is just for you."]
        ];

        // Centrifugo sử dụng một channel đặc biệt cho từng user, thường có dạng #<user_id>.
        // Client sẽ cần subscribe vào channel này để nhận tin nhắn.
        $userChannel = "#{$user->id}";

        $success = $this->centrifugo->publish($userChannel, $payload);

        if ($success) {
            return (new Response())->json(['message' => "Notification sent to user {$userId}."]);
        }

        return (new Response())->json(['message' => 'Failed to send notification.'], 500);
    }

    /**
     * Gửi một thông báo đến tất cả các client đang lắng nghe trên một channel công khai.
     */
    #[Route('/broadcast', method: 'POST')]
    public function broadcastToAll(Request $request): Response
    {
        $payload = [
            'event' => 'global_announcement',
            'data' => ['text' => $request->get('message', 'This is a broadcast message for everyone!')]
        ];

        // Publish đến một channel công khai, ví dụ 'news'
        $success = $this->centrifugo->publish('news', $payload);

        if ($success) {
            return (new Response())->json(['message' => 'Broadcast notification sent.']);
        }

        return (new Response())->json(['message' => 'Failed to send broadcast notification.'], 500);
    }
}