<?php

namespace Modules\Centrifugo\Domain\Contracts;

interface TokenGeneratorInterface
{
    /**
     * Generate a connection token for a specific user.
     *
     * @param string $userId The ID of the user.
     * @param array $claims Additional claims to include in the token.
     * @return string The generated JWT.
     */
    public function generate(string $userId, array $claims = []): string;
}
