<?php

declare(strict_types=1);

namespace Modules\User\Domain\Events;

class UserProfileUpdated
{
    /**
     * @param int $userId ID của người dùng vừa được cập nhật.
     */
    public function __construct(
        public readonly int $userId,
    ) {
    }
}
