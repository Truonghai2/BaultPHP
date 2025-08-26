<?php

namespace Modules\User\Domain\Events;

use Core\Events\DomainEvent;
use Modules\User\Infrastructure\Models\User;

/**
 * Dispatched after a user has been successfully created.
 */
class UserWasCreated implements DomainEvent
{
    public function __construct(public readonly User $user)
    {
    }
}
