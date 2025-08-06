<?php

namespace Modules\Centrifugo\Infrastructure\Repositories;

use Firebase\JWT\JWT;
use InvalidArgumentException;
use Modules\Centrifugo\Domain\Contracts\TokenGeneratorInterface;

class JwtTokenGenerator implements TokenGeneratorInterface
{
    private string $secret;
    private int $lifetime;

    public function __construct()
    {
        $this->secret = config('centrifugo.secret');
        $this->lifetime = config('centrifugo.lifetime', 3600);

        if (empty($this->secret)) {
            throw new InvalidArgumentException('Centrifugo JWT secret key is not configured in config/centrifugo.php or .env file.');
        }
    }

    public function generate(string $userId, array $claims = []): string
    {
        $payload = array_merge($claims, [
            'sub' => $userId,
            'exp' => time() + $this->lifetime,
        ]);

        return JWT::encode($payload, $this->secret, 'HS256');
    }
}
