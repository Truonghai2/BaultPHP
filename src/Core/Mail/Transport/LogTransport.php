<?php

namespace Core\Mail\Transport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\RawMessage;

/**
 * Log Transport.
 * 
 * Logs emails instead of sending them (for development).
 */
class LogTransport implements TransportInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $this->logger->info("📧 Email logged (not sent)", [
            'to' => $this->getRecipients($envelope ?? Envelope::create($message)),
            'subject' => $this->getSubject($message),
            'body' => $this->getBody($message),
        ]);

        return new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    public function __toString(): string
    {
        return 'log://default';
    }

    /**
     * Get recipients from envelope.
     */
    protected function getRecipients(Envelope $envelope): array
    {
        return array_map(
            fn($recipient) => $recipient->getAddress(),
            $envelope->getRecipients()
        );
    }

    /**
     * Get subject from message.
     */
    protected function getSubject(RawMessage $message): ?string
    {
        if (method_exists($message, 'getSubject')) {
            return $message->getSubject();
        }

        return null;
    }

    /**
     * Get body from message.
     */
    protected function getBody(RawMessage $message): ?string
    {
        if (method_exists($message, 'getHtmlBody')) {
            return substr($message->getHtmlBody() ?? '', 0, 200) . '...';
        }

        if (method_exists($message, 'getTextBody')) {
            return substr($message->getTextBody() ?? '', 0, 200) . '...';
        }

        return null;
    }
}
