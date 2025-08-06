<?php

declare(strict_types=1);

namespace Modules\User\Application\Commands;

use Core\CQRS\Command;

class DeleteUserCommand implements Command
{
    public function __construct(
        public readonly int $userId,
    ) {
    }
}
