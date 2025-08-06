<?php

namespace Core\Support\Facades;

use Core\Contracts\Mail\Mailable;
use Core\Mail\PendingMail;

/**
 * @method static PendingMail to(object|array|string $users)
 * @method static void send(Mailable $mailable)
 *
 * @see \Core\Contracts\Mail\Mailer
 */
class Mail extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Core\Contracts\Mail\Mailer::class;
    }
}
