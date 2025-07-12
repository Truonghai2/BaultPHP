<?php

namespace Modules\User\Domain\Events;

use Modules\User\Infrastructure\Models\User;

class UserWasCreated
{
    public function __construct(
        public readonly User $user
    ) {}
}