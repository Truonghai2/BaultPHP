<?php

namespace Modules\Centrifugo\Application\UseCases;

use Core\Contracts\Auth\Authenticatable;
use Modules\Centrifugo\Domain\Contracts\TokenGeneratorInterface;

class GenerateConnectionTokenUseCase
{
    public function __construct(private TokenGeneratorInterface $tokenGenerator)
    {
    }

    /**
     * Executes the use case.
     *
     * @param Authenticatable $user The authenticated user object.
     * @return string The generated connection token.
     */
    public function handle(Authenticatable $user): string
    {
        return $this->tokenGenerator->generate((string) $user->getAuthIdentifier());
    }
}
