<?php

namespace Modules\User\Application\Commands;

/**
 * A Data Transfer Object (DTO) representing the command to register a new user.
 */
class RegisterUserCommand
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
    ) {
    }
}
