<?php

namespace Modules\User\Application\Commands;

/**
 * A Data Transfer Object (DTO) representing the command to log a user in.
 */
class LoginUserCommand
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly bool $remember,
    ) {
    }
}
