<?php

namespace Core\Mail;

use Core\Contracts\Queue\Queue;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Mail Service.
 * 
 * Send emails with:
 * - Multiple transports (SMTP, Sendmail, Log, Array)
 * - Queue support (async sending)
 * - Templates with Blade
 * - Attachments
 * - Testing utilities
 */
class Mailer
{
    private SymfonyMailer $mailer;

    public function __construct(
        private TransportInterface $transport,
        private LoggerInterface $logger,
        private ?Queue $queue = null,
    ) {
        $this->mailer = new SymfonyMailer($this->transport);
    }

    /**
     * Send mailable immediately.
     */
    public function send(Mailable $mailable): void
    {
        try {
            $message = $mailable->build();

            $this->logger->info("Sending email", [
                'to' => $this->getRecipients($message),
                'subject' => $message->getSubject(),
            ]);

            $this->mailer->send($message);

            $this->logger->info("Email sent successfully", [
                'to' => $this->getRecipients($message),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to send email", [
                'error' => $e->getMessage(),
                'mailable' => get_class($mailable),
            ]);

            throw new MailException("Failed to send email: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Queue mailable for async sending.
     */
    public function queue(Mailable $mailable, ?string $queueName = 'emails'): void
    {
        if ($this->queue === null) {
            throw new MailException("Queue service not configured");
        }

        $this->logger->info("Queueing email", [
            'mailable' => get_class($mailable),
            'queue' => $queueName,
        ]);

        $this->queue->push(new SendMailJob($mailable), $queueName);
    }

    /**
     * Send mailable later (delayed).
     */
    public function later(Mailable $mailable, int $delaySeconds, ?string $queueName = 'emails'): void
    {
        if ($this->queue === null) {
            throw new MailException("Queue service not configured");
        }

        $this->logger->info("Scheduling email", [
            'mailable' => get_class($mailable),
            'delay' => $delaySeconds,
            'queue' => $queueName,
        ]);

        $this->queue->later($delaySeconds, new SendMailJob($mailable), $queueName);
    }

    /**
     * Send to multiple recipients.
     */
    public function sendToMany(Mailable $mailable, array $recipients): void
    {
        foreach ($recipients as $recipient) {
            $clone = clone $mailable;
            $clone->to($recipient);
            $this->send($clone);
        }
    }

    /**
     * Queue to multiple recipients.
     */
    public function queueToMany(Mailable $mailable, array $recipients, ?string $queueName = 'emails'): void
    {
        foreach ($recipients as $recipient) {
            $clone = clone $mailable;
            $clone->to($recipient);
            $this->queue($clone, $queueName);
        }
    }

    /**
     * Get transport.
     */
    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * Get underlying Symfony mailer.
     */
    public function getSymfonyMailer(): SymfonyMailer
    {
        return $this->mailer;
    }

    /**
     * Get recipients from message.
     */
    protected function getRecipients($message): array
    {
        $recipients = [];
        
        foreach ($message->getTo() as $address) {
            $recipients[] = $address->getAddress();
        }

        return $recipients;
    }
}
