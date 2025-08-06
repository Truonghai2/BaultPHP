<?php

namespace Core\Auth;

use Core\Application;
use Core\Contracts\Auth\Authenticatable;
use Core\Contracts\Auth\Guard;
use Core\Contracts\Auth\UserProvider;
use Core\Session\SessionManager;

class SessionGuard implements Guard
{
    protected Application $app;
    protected SessionManager $session;
    protected UserProvider $provider;
    protected ?Authenticatable $user = null;
    protected bool $userResolved = false;

    public function __construct(Application $app, SessionManager $session, UserProvider $provider)
    {
        $this->app = $app;
        $this->session = $session;
        $this->provider = $provider;
    }

    public function check(): bool
    {
        return !is_null($this->user());
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?Authenticatable
    {
        if ($this->userResolved) {
            return $this->user;
        }

        $id = $this->session->get($this->getName());
        if (!is_null($id)) {
            $this->user = $this->provider->retrieveById($id);
        }

        $this->userResolved = true;
        return $this->user;
    }

    public function id()
    {
        return $this->session->get($this->getName());
    }

    public function login(Authenticatable $user): void
    {
        $this->session->set($this->getName(), $user->getAuthIdentifier());
        $this->session->regenerate(); // Chống session fixation attacks
        $this->setUser($user);
    }

    public function logout(): void
    {
        $this->session->forget($this->getName());
        $this->session->regenerate();
        $this->user = null;
        $this->userResolved = false;
    }

    public function attempt(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($this->hasValidCredentials($user, $credentials)) {
            $this->login($user);
            return true;
        }

        return false;
    }

    protected function hasValidCredentials(?Authenticatable $user, array $credentials): bool
    {
        return !is_null($user) && password_verify($credentials['password'], $user->getAuthPassword());
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
        $this->userResolved = true;
    }

    protected function getName(): string
    {
        return 'login_web_' . sha1(static::class);
    }
}
