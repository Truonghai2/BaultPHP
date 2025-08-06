<?php

namespace Core\Contracts\Mail;

interface Mailable
{
    /**
     * Thiết lập người nhận.
     */
    public function to(object|array|string $address, ?string $name = null): static;

    /**
     * Xây dựng nội dung email (subject, view, data...).
     */
    public function build(): static;

    /**
     * Gửi email bằng một Mailer cụ thể.
     */
    public function send(Mailer $mailer): void;
}
