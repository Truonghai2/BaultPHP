<?php

namespace Core\Console\Commands\Mail;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Mail\Mailer;
use Core\Mail\Mailable;

/**
 * Mail Test Command.
 * 
 * Send test email to verify mail configuration.
 * 
 * Usage: php artisan mail:test user@example.com
 */
class MailTestCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'mail:test 
                {to : Recipient email address}
                {--subject=Test Email : Email subject}
                {--queue : Queue the email instead of sending}';
    }

    public function description(): string
    {
        return 'Send a test email to verify mail configuration';
    }

    public function handle(): int
    {
        $to = $this->argument('to');
        $subject = $this->option('subject');
        $queue = $this->option('queue');

        $this->info("Sending test email to: {$to}");

        $mailer = $this->app->make(Mailer::class);

        // Create test mailable
        $mailable = new class($to, $subject) extends Mailable {
            public function __construct(
                private string $recipient,
                private string $emailSubject
            ) {
                parent::__construct();
            }

            public function build(): \Symfony\Component\Mime\Email
            {
                return $this
                    ->to($this->recipient)
                    ->subject($this->emailSubject)
                    ->html('<h1>Test Email</h1><p>This is a test email from BaultFrame.</p>')
                    ->build();
            }
        };

        try {
            if ($queue) {
                $mailer->queue($mailable);
                $this->info("✅ Email queued successfully!");
            } else {
                $mailer->send($mailable);
                $this->info("✅ Email sent successfully!");
            }

            return 0;
        } catch (\Throwable $e) {
            $this->error("❌ Failed to send email: " . $e->getMessage());
            return 1;
        }
    }
}
