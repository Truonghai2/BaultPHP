<?php

namespace Core\Mail;

use Core\Contracts\Mail\Mailable;
use Core\Contracts\Mail\Mailer as MailerContract;
use Core\Contracts\Queue\ShouldQueue;
use Core\Support\Facades\Queue;

/**
 * Lớp trung gian để tạo ra một API gửi mail mượt mà (fluent).
 * Ví dụ: Mail::to($user)->send(new WelcomeEmail());
 */
class PendingMail
{
    protected array $to = [];

    public function __construct(protected MailerContract $mailer)
    {
    }

    public function to(object|array|string $users): static
    {
        if (is_string($users)) {
            $this->to[] = ['address' => $users];
        } elseif (is_array($users)) {
            foreach ($users as $user) {
                if (is_string($user)) {
                    $this->to[] = ['address' => $user];
                } elseif (is_object($user) && property_exists($user, 'email')) {
                    $this->to[] = ['address' => $user->email, 'name' => $user->name ?? null];
                }
            }
        } elseif (is_object($users) && property_exists($users, 'email')) {
            $this->to[] = ['address' => $users->email, 'name' => $users->name ?? null];
        }
        return $this;
    }

    public function send(Mailable $mailable): void
    {
        $mailable->to($this->to);

        if ($mailable instanceof ShouldQueue) {
            // Nếu Mailable nên được đưa vào hàng đợi, hãy dispatch một job mới.
            Queue::dispatch(new SendQueuedMailable($mailable));
        } else {
            // Nếu không, gửi ngay lập tức.
            $this->mailer->send($mailable);
        }
    }
}
