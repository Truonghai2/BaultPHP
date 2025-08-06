<?php

declare(strict_types=1);

namespace Modules\User\Application;

class UserDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
    ) {
    }
}
