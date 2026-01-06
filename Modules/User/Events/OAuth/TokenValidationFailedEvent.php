<?php

namespace Modules\User\Events\OAuth;

class TokenValidationFailedEvent
{
    public function __construct(
        public readonly string $reason,
        public readonly string $ipAddress,
        public readonly ?string $tokenIdentifier = null,
    ) {
    }
}

