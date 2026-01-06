<?php

namespace Modules\User\Events\OAuth;

class TokenRevokedEvent
{
    public function __construct(
        public readonly string $tokenId,
        public readonly ?string $userId,
        public readonly string $reason,
        public readonly string $ipAddress,
    ) {
    }
}
