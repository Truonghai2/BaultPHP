<?php

namespace Core\Auth;

use Core\Contracts\Auth\Authenticatable;
use Core\Contracts\Auth\Guard;
use Core\Contracts\Auth\UserProvider;
use Http\Request;

class JwtGuard implements Guard
{
    protected ?Authenticatable $user = null;
    protected bool $userResolved = false;

    public function __construct(
        protected Request $request,
        protected UserProvider $provider
    ) {}

    /**
     * Get the currently authenticated user.
     */
    public function user(): ?Authenticatable
    {
        if ($this->userResolved) {
            return $this->user;
        }

        $token = $this->request->bearerToken();

        if (!$token) {
            $this->userResolved = true;
            return null;
        }

        try {
            // Sử dụng APP_KEY để nhất quán với toàn bộ framework
            $payload = JWT::decode($token, env('APP_KEY'));

            if ($payload && isset($payload->sub)) {
                $this->user = $this->provider->retrieveById($payload->sub);
            }
        } catch (\Exception $e) {
            // Token không hợp lệ, hết hạn, hoặc chữ ký sai.
            // Người dùng vẫn là null.
        }

        $this->userResolved = true;
        return $this->user;
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

    public function login(Authenticatable $user): void {}
    public function logout(): void {}
    public function attempt(array $credentials = []): bool { return false; }
    
}
