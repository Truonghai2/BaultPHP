<?php

namespace Modules\User\Application\Commands;

use Core\CQRS\Command;

class UpdateUserProfileCommand implements Command
{
    /**
     * @param int $userId
     * @param string|null $name
     * @param string|null $email
     */
    public function __construct(
        public readonly int $userId,
        public readonly ?string $name,
        public readonly ?string $email,
    ) {
    }
}
