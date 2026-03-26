<?php

namespace Core\Mail\Transport;

use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\NullTransport;
use Psr\Log\LoggerInterface;

/**
 * Mail Transport Factory.
 * 
 * Creates appropriate mail transport based on configuration.
 */
class TransportFactory
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Create transport from config.
     */
    public function create(array $config): TransportInterface
    {
        $driver = $config['driver'] ?? 'smtp';

        return match ($driver) {
            'smtp' => $this->createSmtpTransport($config),
            'sendmail' => $this->createSendmailTransport($config),
            'log' => $this->createLogTransport($config),
            'array' => $this->createArrayTransport($config),
            'null' => new NullTransport(),
            default => throw new \InvalidArgumentException("Unsupported mail driver: {$driver}"),
        };
    }

    /**
     * Create SMTP transport.
     */
    protected function createSmtpTransport(array $config): EsmtpTransport
    {
        $transport = new EsmtpTransport(
            $config['host'] ?? 'localhost',
            $config['port'] ?? 587,
            $config['encryption'] ?? null
        );

        // Set credentials if provided
        if (isset($config['username']) && isset($config['password'])) {
            $transport->setUsername($config['username']);
            $transport->setPassword($config['password']);
        }

        // Set timeout
        if (isset($config['timeout'])) {
            $transport->setTimeout($config['timeout']);
        }

        return $transport;
    }

    /**
     * Create Sendmail transport.
     */
    protected function createSendmailTransport(array $config): SendmailTransport
    {
        $command = $config['path'] ?? '/usr/sbin/sendmail -bs';
        
        return new SendmailTransport($command);
    }

    /**
     * Create log transport (for testing).
     */
    protected function createLogTransport(array $config): LogTransport
    {
        return new LogTransport($this->logger);
    }

    /**
     * Create array transport (for testing).
     */
    protected function createArrayTransport(array $config): ArrayTransport
    {
        return new ArrayTransport();
    }
}
