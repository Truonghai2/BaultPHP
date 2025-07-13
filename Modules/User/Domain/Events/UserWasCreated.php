<?php

namespace Modules\User\Domain\Events;

use Modules\User\Domain\Entities\User as UserEntity;

class UserWasCreated
{
    public function __construct(
        public readonly UserEntity $user
    ) {}
}