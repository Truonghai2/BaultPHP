<?php

namespace Core\Mail;

use Core\Contracts\Mail\Mailable as MailableContract;
use Core\Contracts\Mail\Mailer as MailerContract;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Lớp cơ sở mà tất cả các class email sẽ kế thừa.
 */
abstract class Mailable implements MailableContract
{
    public array $to = [];
    public string $subject = '';
    public ?string $view = null;
    public array $viewData = [];

    public function to(object|array|string $address, ?string $name = null): static
    {
        if (is_array($address) && !isset($address['address'])) {
            $this->to = array_merge($this->to, $address);
        } else {
            $this->to[] = is_array($address) ? $address : ['address' => $address, 'name' => $name];
        }
        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function view(string $view, array $data = []): static
    {
        $this->view = $view;
        $this->viewData = $data;
        return $this;
    }

    public function build(): static
    {
        return $this;
    }

    public function send(MailerContract $mailer): void
    {
        $mailer->send($this);
    }

    /**
     * Xây dựng đối tượng Symfony Email.
     * @internal
     */
    public function buildSymfonyEmail(array $from): Email
    {
        $this->build();

        $email = (new Email())
            ->from(new Address($from['address'], $from['name'] ?? ''))
            ->subject($this->subject);

        foreach ($this->to as $recipient) {
            $email->addTo(new Address($recipient['address'], $recipient['name'] ?? ''));
        }

        // Sử dụng hệ thống View để render nội dung HTML
        if ($this->view) {
            $html = app('view')->make($this->view, $this->viewData)->render();
            $email->html($html);
        }

        return $email;
    }
}
