<?php

namespace App\Providers;

use Core\Contracts\Mail\Mailer as MailerContract;
use Core\Contracts\Support\DeferrableProvider;
use Core\Mail\MailerService;
use Core\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;

class MailServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(MailerContract::class, function ($app) {
            $config = $app->make('config')->get('mail');

            return new MailerService(
                new Mailer($this->createTransport($config)),
                $config['from'],
            );
        });
    }

    protected function createTransport(array $config): TransportInterface
    {
        $mailerName = $config['default'] ?? 'smtp';
        $mailerConfig = $config['mailers'][$mailerName];
        $dsn = $this->getDsn($mailerConfig);

        if ($mailerConfig['transport'] === 'log') {
            $logger = $this->app->make(LoggerInterface::class);
            return Transport::fromDsn($dsn, null, null, $logger);
        }

        return Transport::fromDsn($dsn);
    }

    protected function getDsn(array $config): string
    {
        return match ($config['transport']) {
            'smtp' => sprintf(
                'smtp://%s:%s@%s:%d',
                urlencode($config['username'] ?? ''),
                urlencode($config['password'] ?? ''),
                $config['host'],
                $config['port'],
            ),
            'log' => 'log://null',
            default => throw new \InvalidArgumentException("Unsupported mail transport [{$config['transport']}]"),
        };
    }

    /**
     * Get the services provided by the provider.
     *
     * This provider will only be loaded when one of these services is requested from the container.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [MailerContract::class, MailerService::class];
    }
}
