<?php

namespace Core\Support\Facades;

use Core\Mail\Mailer;
use Core\Mail\Mailable;

/**
 * Mail Facade.
 * 
 * @method static void send(Mailable $mailable)
 * @method static void queue(Mailable $mailable, ?string $queueName = 'emails')
 * @method static void later(Mailable $mailable, int $delaySeconds, ?string $queueName = 'emails')
 * @method static void sendToMany(Mailable $mailable, array $recipients)
 * @method static void queueToMany(Mailable $mailable, array $recipients, ?string $queueName = 'emails')
 * 
 * @see Mailer
 */
class Mail extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Mailer::class;
    }
}
