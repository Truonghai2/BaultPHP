<?php

namespace App\Providers;

use Core\Application;
use Core\Mail\Mailer;
use Core\Mail\Transport\TransportFactory;
use Psr\Log\LoggerInterface;

/**
 * Mail Service Provider.
 * 
 * Registers mail services.
 */
class MailServiceProvider
{
    public function __construct(
        protected Application $app
    ) {
    }

    public function register(): void
    {
        // Register Transport Factory
        $this->app->singleton(TransportFactory::class, function ($app) {
            return new TransportFactory(
                $app->make(LoggerInterface::class)
            );
        });

        // Register Mailer
        $this->app->singleton(Mailer::class, function ($app) {
            $factory = $app->make(TransportFactory::class);
            
            // Get default mailer config
            $defaultMailer = config('mail.default', 'smtp');
            $mailerConfig = config("mail.mailers.{$defaultMailer}");

            // Create transport
            $transport = $factory->create($mailerConfig);

            // Get queue if available
            $queue = null;
            if (config('mail.queue.enabled', true)) {
                try {
                    $queue = $app->make(\Core\Contracts\Queue\Queue::class);
                } catch (\Throwable $e) {
                    // Queue not available
                }
            }

            return new Mailer(
                $transport,
                $app->make(LoggerInterface::class),
                $queue
            );
        });

        // Alias
        $this->app->alias(Mailer::class, 'mailer');
    }

    public function boot(): void
    {
        // Boot logic if needed
    }
}
