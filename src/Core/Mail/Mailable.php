<?php

namespace Core\Mail;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Base Mailable Class.
 * 
 * Create email messages with fluent API:
 * 
 * ```php
 * (new WelcomeMail($user))
 *     ->to($user->email)
 *     ->subject('Welcome!')
 *     ->view('emails.welcome');
 * ```
 */
abstract class Mailable
{
    protected Email $message;
    protected ?string $view = null;
    protected array $viewData = [];
    protected array $attachments = [];

    public function __construct()
    {
        $this->message = new Email();
        
        // Set default from
        $defaultFrom = config('mail.from.address');
        $defaultName = config('mail.from.name');
        
        if ($defaultFrom) {
            $this->from($defaultFrom, $defaultName);
        }
    }

    /**
     * Set "from" address.
     */
    public function from(string $address, ?string $name = null): static
    {
        $this->message->from(new Address($address, $name ?? ''));
        return $this;
    }

    /**
     * Set "to" address.
     */
    public function to(string|array $address, ?string $name = null): static
    {
        if (is_array($address)) {
            foreach ($address as $email => $recipientName) {
                if (is_numeric($email)) {
                    $this->message->addTo($recipientName);
                } else {
                    $this->message->addTo(new Address($email, $recipientName));
                }
            }
        } else {
            $this->message->to(new Address($address, $name ?? ''));
        }
        
        return $this;
    }

    /**
     * Set "cc" address.
     */
    public function cc(string $address, ?string $name = null): static
    {
        $this->message->cc(new Address($address, $name ?? ''));
        return $this;
    }

    /**
     * Set "bcc" address.
     */
    public function bcc(string $address, ?string $name = null): static
    {
        $this->message->bcc(new Address($address, $name ?? ''));
        return $this;
    }

    /**
     * Set reply-to address.
     */
    public function replyTo(string $address, ?string $name = null): static
    {
        $this->message->replyTo(new Address($address, $name ?? ''));
        return $this;
    }

    /**
     * Set subject.
     */
    public function subject(string $subject): static
    {
        $this->message->subject($subject);
        return $this;
    }

    /**
     * Set priority (1-5, 1 is highest).
     */
    public function priority(int $priority): static
    {
        $this->message->priority($priority);
        return $this;
    }

    /**
     * Set view template.
     */
    public function view(string $view, array $data = []): static
    {
        $this->view = $view;
        $this->viewData = array_merge($this->viewData, $data);
        return $this;
    }

    /**
     * Set plain text content.
     */
    public function text(string $content): static
    {
        $this->message->text($content);
        return $this;
    }

    /**
     * Set HTML content.
     */
    public function html(string $content): static
    {
        $this->message->html($content);
        return $this;
    }

    /**
     * Add attachment from path.
     */
    public function attach(string $path, ?string $name = null, ?string $mime = null): static
    {
        $this->attachments[] = [
            'type' => 'path',
            'path' => $path,
            'name' => $name,
            'mime' => $mime,
        ];
        
        return $this;
    }

    /**
     * Add attachment from data.
     */
    public function attachData(string $data, string $name, ?string $mime = null): static
    {
        $this->attachments[] = [
            'type' => 'data',
            'data' => $data,
            'name' => $name,
            'mime' => $mime,
        ];
        
        return $this;
    }

    /**
     * Embed image for inline display.
     */
    public function embed(string $path, ?string $name = null): string
    {
        $cid = 'cid:' . uniqid();
        
        $this->attachments[] = [
            'type' => 'embed',
            'path' => $path,
            'cid' => $cid,
            'name' => $name,
        ];
        
        return $cid;
    }

    /**
     * Build the message.
     * 
     * Override this method to customize message building.
     */
    public function build(): Email
    {
        // Render view if set
        if ($this->view !== null) {
            $html = view($this->view, $this->viewData)->render();
            $this->message->html($html);
        }

        // Add attachments
        foreach ($this->attachments as $attachment) {
            match ($attachment['type']) {
                'path' => $this->message->attachFromPath(
                    $attachment['path'],
                    $attachment['name'],
                    $attachment['mime']
                ),
                'data' => $this->message->attach(
                    $attachment['data'],
                    $attachment['name'],
                    $attachment['mime']
                ),
                'embed' => $this->message->embed(
                    DataPart::fromPath($attachment['path']),
                    $attachment['cid']
                ),
            };
        }

        return $this->message;
    }

    /**
     * Get message instance.
     */
    public function getMessage(): Email
    {
        return $this->message;
    }
}
