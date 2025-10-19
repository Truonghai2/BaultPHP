<?php

namespace Core\Auth;

use Core\Contracts\Auth\Authenticatable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

/**
 * Dịch vụ chuyên tạo JWT token.
 */
class TokenIssuerService
{
    private Configuration $config;

    public function __construct(string $appKey)
    {
        $this->config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($appKey),
        );
    }

    /**
     * Tạo một token cho một user với các scope và thời gian sống nhất định.
     */
    public function issue(Authenticatable $user, array $scopes = [], int $lifetime = 3600): string
    {
        $now = new \DateTimeImmutable();
        $token = $this->config->builder()
            ->issuedBy(config('app.url'))
            ->permittedFor(config('app.url'))
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->issuedAt($now)
            ->expiresAt($now->modify("+{$lifetime} seconds"))
            ->withClaim('uid', $user->getAuthIdentifier())
            ->withClaim('scopes', $scopes)
            ->getToken($this->config->signer(), $this->config->signingKey());

        return $token->toString();
    }
}
