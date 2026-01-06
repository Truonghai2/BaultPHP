<?php

namespace Core\Auth;

use Core\Application;
use Core\Contracts\Auth\Authenticatable;
use Core\Contracts\Auth\Guard;
use Core\Contracts\Auth\UserProvider;
use Psr\Http\Message\ServerRequestInterface;

class ApiKeyGuard implements Guard
{
    protected ?Authenticatable $user = null;
    protected bool $userResolved = false;
    protected ?ServerRequestInterface $request;

    public function __construct(
        protected string $name,
        protected Application $app,
        protected UserProvider $provider,
        protected string $inputKey = 'api_key',
        protected string $storageKey = 'key',
    ) {
        $this->request = $app->has(ServerRequestInterface::class) ? $app->make(ServerRequestInterface::class) : null;
    }

    public function user(): ?Authenticatable
    {
        if ($this->userResolved) {
            return $this->user;
        }

        $apiKey = $this->getApiKeyFromRequest();

        if (empty($apiKey)) {
            $this->userResolved = true;
            return null;
        }

        $hashedKey = hash('sha256', $apiKey);

        /** @var ApiKey|null $tokenRecord */
        $tokenRecord = ApiKey::where($this->storageKey, $hashedKey)->first();

        if ($tokenRecord) {
            // Kiểm tra key đã hết hạn chưa
            if ($tokenRecord->expires_at && $tokenRecord->expires_at->isPast()) {
            } else {
                $tokenRecord->last_used_at = now();
                $tokenRecord->save();

                $user = $this->provider->retrieveById($tokenRecord->user_id);
                if ($user) {
                    $this->setUser($user);
                }
            }
        }

        $this->userResolved = true;
        return $this->user;
    }

    protected function getApiKeyFromRequest(): ?string
    {
        $authHeader = $this->request?->getHeaderLine('Authorization');
        if ($authHeader && str_starts_with(strtolower($authHeader), 'bearer ')) {
            return substr($authHeader, 7);
        }

        $customHeader = $this->request?->getHeaderLine('X-API-KEY');
        if (!empty($customHeader)) {
            return $customHeader;
        }

        $queryParams = $this->request?->getQueryParams();
        return $queryParams[$this->inputKey] ?? null;
    }

    public function check(): bool
    {
        return !is_null($this->user());
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id()
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
        $this->userResolved = true;
    }

    public function login(Authenticatable $user, bool $remember = false): void
    {
    }
    public function logout(): void
    {
    }
    public function attempt(array $credentials = [], bool $remember = false): ?Authenticatable
    {
        return null;
    }
}
