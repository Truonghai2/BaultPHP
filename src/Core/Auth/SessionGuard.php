<?php

namespace Core\Auth;

use Core\Application;
use Core\Contracts\Auth\Authenticatable;
use Core\Contracts\Auth\Guard;
use Core\Contracts\Auth\UserProvider;
use Symfony\Component\HttpFoundation\Session\Session;

class SessionGuard implements Guard
{
    protected Application $app;
    protected Session $session;
    protected UserProvider $provider;
    protected ?Authenticatable $user = null;
    protected bool $userResolved = false;

    public function __construct(Application $app, Session $session, UserProvider $provider)
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

    public function login(Authenticatable $user, bool $remember = false): void
    {
        // TODO: Implement "remember me" functionality.
        // This would typically involve setting a long-lived cookie with a remember token.
        // For now, we just log the user in for the current session.
        $this->session->set($this->getName(), $user->getAuthIdentifier());
        $this->session->regenerate(); // Chống session fixation attacks
        $this->setUser($user);
    }

    public function logout(): void
    {
        $this->user = null;
        $this->userResolved = false;

        $this->session->forget($this->getName());
        // Invalidate the session to clear all data and regenerate the session ID.
        $this->session->invalidate();
    }

    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($this->hasValidCredentials($user, $credentials)) {
            $this->login($user, $remember);
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
