<?php

declare(strict_types=1);

namespace Modules\User\Domain\Events;

class UserDeleted
{
    public function __construct(
        public readonly int $deletedUserId,
    ) {
    }
}
