<?php

namespace Core\Mail\Transport;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\RawMessage;

/**
 * Array Transport.
 * 
 * Stores emails in memory for testing.
 */
class ArrayTransport implements TransportInterface
{
    private array $messages = [];

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $this->messages[] = [
            'message' => $message,
            'envelope' => $envelope ?? Envelope::create($message),
            'sent_at' => new \DateTimeImmutable(),
        ];

        return new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    public function __toString(): string
    {
        return 'array://default';
    }

    /**
     * Get all sent messages.
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get count of sent messages.
     */
    public function getMessageCount(): int
    {
        return count($this->messages);
    }

    /**
     * Clear all messages.
     */
    public function clear(): void
    {
        $this->messages = [];
    }

    /**
     * Check if message was sent to address.
     */
    public function hasSentTo(string $address): bool
    {
        foreach ($this->messages as $item) {
            $recipients = $item['envelope']->getRecipients();
            
            foreach ($recipients as $recipient) {
                if ($recipient->getAddress() === $address) {
                    return true;
                }
            }
        }

        return false;
    }
}
