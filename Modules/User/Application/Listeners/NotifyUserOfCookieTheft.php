<?php

namespace Modules\User\Application\Listeners;

use Core\Auth\Events\CookieTheftDetected;
use Core\Contracts\Auth\UserProvider;
use Modules\User\Application\Services\NotificationService;
use Modules\User\Infrastructure\Models\User;

// use Core\Contracts\Mail\Mailer; // Giả sử bạn có một Mailer contract

class NotifyUserOfCookieTheft
{
    public function __construct(
        protected UserProvider $userProvider,
        protected NotificationService $notificationService,
        // protected Mailer $mailer
    ) {
    }

    public function handle(CookieTheftDetected $event): void
    {
        /** @var User|null $user */
        $user = $this->userProvider->retrieveById($event->userId);

        if ($user) {
            // Tại đây, bạn có thể triển khai logic để gửi email, SMS, hoặc thông báo trong ứng dụng.
            // Ví dụ: $this->mailer->to($user->email)->send(new PotentialSecurityRiskMail($user));

            // Hiện tại, chúng ta sẽ ghi log để xác nhận nó hoạt động.
            logger()->warning('Potential cookie theft detected for user. All "remember me" tokens have been invalidated.', [
                'user_id' => $user->getAuthIdentifier(),
                'email' => $user->email,
            ]);

            $this->notificationService->sendToUser(
                $user,
                'Cảnh báo bảo mật',
                'Chúng tôi phát hiện hoạt động đăng nhập đáng ngờ. Tất cả các phiên đăng nhập "ghi nhớ tôi" của bạn đã bị hủy.',
            );
        }
    }
}
