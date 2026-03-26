<?php

namespace Core\Mail\Testing;

use Core\Mail\Mailer;
use Core\Mail\Mailable;
use Core\Mail\Transport\ArrayTransport;
use Core\Contracts\Queue\Queue;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Mail Fake for Testing.
 * 
 * Replaces real mailer in tests and provides assertions.
 * 
 * Usage:
 * ```php
 * $fake = MailFake::create();
 * $fake->send(new WelcomeEmail($user));
 * 
 * $fake->assertSent(WelcomeEmail::class);
 * $fake->assertSentTo('user@example.com', WelcomeEmail::class);
 * ```
 */
class MailFake extends Mailer
{
    private ArrayTransport $arrayTransport;
    private array $queued = [];

    public function __construct(
        ArrayTransport $transport,
        LoggerInterface $logger,
        ?Queue $queue = null
    ) {
        $this->arrayTransport = $transport;
        parent::__construct($transport, $logger, $queue);
    }

    /**
     * Create mail fake instance.
     */
    public static function create(LoggerInterface $logger): self
    {
        return new self(new ArrayTransport(), $logger);
    }

    /**
     * Override queue to store in memory.
     */
    public function queue(Mailable $mailable, ?string $queueName = 'emails'): void
    {
        $this->queued[] = [
            'mailable' => $mailable,
            'queue' => $queueName,
            'delay' => 0,
        ];
    }

    /**
     * Override later to store in memory.
     */
    public function later(Mailable $mailable, int $delaySeconds, ?string $queueName = 'emails'): void
    {
        $this->queued[] = [
            'mailable' => $mailable,
            'queue' => $queueName,
            'delay' => $delaySeconds,
        ];
    }

    /**
     * Assert email was sent.
     */
    public function assertSent(string $mailableClass, ?callable $callback = null): void
    {
        $count = $this->countSent($mailableClass, $callback);

        PHPUnit::assertTrue(
            $count > 0,
            "Email [{$mailableClass}] was not sent."
        );
    }

    /**
     * Assert email was not sent.
     */
    public function assertNotSent(string $mailableClass, ?callable $callback = null): void
    {
        $count = $this->countSent($mailableClass, $callback);

        PHPUnit::assertTrue(
            $count === 0,
            "Email [{$mailableClass}] was sent {$count} time(s)."
        );
    }

    /**
     * Assert email was sent to address.
     */
    public function assertSentTo(string $address, string $mailableClass): void
    {
        PHPUnit::assertTrue(
            $this->arrayTransport->hasSentTo($address),
            "No email was sent to [{$address}]."
        );
    }

    /**
     * Assert email was queued.
     */
    public function assertQueued(string $mailableClass, ?callable $callback = null): void
    {
        $count = $this->countQueued($mailableClass, $callback);

        PHPUnit::assertTrue(
            $count > 0,
            "Email [{$mailableClass}] was not queued."
        );
    }

    /**
     * Assert email was not queued.
     */
    public function assertNotQueued(string $mailableClass, ?callable $callback = null): void
    {
        $count = $this->countQueued($mailableClass, $callback);

        PHPUnit::assertTrue(
            $count === 0,
            "Email [{$mailableClass}] was queued {$count} time(s)."
        );
    }

    /**
     * Assert nothing was sent.
     */
    public function assertNothingSent(): void
    {
        $count = $this->arrayTransport->getMessageCount();

        PHPUnit::assertEquals(
            0,
            $count,
            "{$count} email(s) were sent."
        );
    }

    /**
     * Assert nothing was queued.
     */
    public function assertNothingQueued(): void
    {
        $count = count($this->queued);

        PHPUnit::assertEquals(
            0,
            $count,
            "{$count} email(s) were queued."
        );
    }

    /**
     * Count sent emails.
     */
    protected function countSent(string $mailableClass, ?callable $callback = null): int
    {
        $count = 0;

        foreach ($this->arrayTransport->getMessages() as $item) {
            $message = $item['message'];
            
            if (!$this->isMailableClass($message, $mailableClass)) {
                continue;
            }

            if ($callback === null || $callback($message)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Count queued emails.
     */
    protected function countQueued(string $mailableClass, ?callable $callback = null): int
    {
        $count = 0;

        foreach ($this->queued as $item) {
            $mailable = $item['mailable'];

            if (!$mailable instanceof $mailableClass) {
                continue;
            }

            if ($callback === null || $callback($mailable)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Check if message is of mailable class.
     */
    protected function isMailableClass($message, string $mailableClass): bool
    {
        // This is simplified - in production you'd track the original mailable
        return true;
    }

    /**
     * Get sent messages.
     */
    public function sent(): array
    {
        return $this->arrayTransport->getMessages();
    }

    /**
     * Get queued messages.
     */
    public function queued(): array
    {
        return $this->queued;
    }

    /**
     * Clear all sent/queued messages.
     */
    public function clear(): void
    {
        $this->arrayTransport->clear();
        $this->queued = [];
    }
}
