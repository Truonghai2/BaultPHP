<?php

namespace Modules\User\Events\OAuth;

class TokenIssuedEvent
{
    public function __construct(
        public readonly string $tokenId,
        public readonly ?string $userId,
        public readonly string $clientId,
        public readonly array $scopes,
        public readonly string $grantType,
        public readonly string $ipAddress,
    ) {
    }
}

