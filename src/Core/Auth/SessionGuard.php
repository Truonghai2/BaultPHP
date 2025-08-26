<?php

namespace Core\Auth;

use Core\Application;
use Core\Contracts\Auth\Authenticatable;
use Core\Contracts\Auth\Guard;
use Core\Contracts\Auth\UserProvider;
use Core\Cookie\CookieManager;
use Symfony\Component\HttpFoundation\Session\Session;

class SessionGuard implements Guard
{
    protected Application $app;
    /**
     * @var CookieManager
     */
    protected CookieManager $cookieManager;
    protected Session $session;
    protected UserProvider $provider;
    protected ?Authenticatable $user = null;
    protected bool $userResolved = false;

    public function __construct(Application $app, Session $session, UserProvider $provider)
    {
        $this->app = $app;
        $this->cookieManager = $app->make(CookieManager::class);
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

        if (is_null($this->user)) {
            $recaller = $this->getRecallerFromCookie();

            if ($recaller) {
                if (is_null($id) || $recaller['id'] != $id) {
                    $this->user = $this->provider->retrieveById($recaller['id']);
                }

                if ($this->user && $this->validateRecaller($this->user, $recaller['token'])) {
                    $this->updateSession($this->user->getAuthIdentifier());
                } else {
                    $this->user = null;
                }
            }
        }

        $this->userResolved = true;
        return $this->user;
    }

    public function id(): mixed
    {
        return $this->session->get($this->getName());
    }

    public function login(Authenticatable $user, bool $remember = false): void
    {
        $this->updateSession($user->getAuthIdentifier());

        if ($remember) {
            $this->createRememberMeCookie($user);
        }

        $this->setUser($user);
    }

    public function logout(): void
    {
        $this->clearRememberMeCookie();

        $this->user = null;
        $this->userResolved = false;

        $this->session->remove($this->getName());
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

    protected function getRememberMeCookieName(): string
    {
        return 'remember_web_' . sha1(static::class);
    }

    protected function updateSession(string|int $id): void
    {
        $this->session->set($this->getName(), $id);
        $this->session->migrate(true);
    }

    /**
     * Lấy payload (id và token) từ cookie "remember me".
     *
     * @return array|null
     */
    protected function getRecallerFromCookie(): ?array
    {
        $cookie = $this->cookieManager->get($this->getRememberMeCookieName());

        if (!$cookie || !str_contains($cookie, '|')) {
            return null;
        }

        [$id, $token] = explode('|', $cookie, 2);

        return ($id && $token) ? ['id' => $id, 'token' => $token] : null;
    }

    /**
     * Xác thực người dùng dựa trên token từ cookie.
     */
    protected function validateRecaller(Authenticatable $user, string $token): bool
    {
        $userRememberToken = $user->getRememberToken();

        return $userRememberToken && hash_equals($userRememberToken, hash('sha256', $token));
    }

    protected function createRememberMeCookie(Authenticatable $user): void
    {
        $token = bin2hex(random_bytes(32));
        $user->setRememberToken(hash('sha256', $token));
        $user->save();

        $cookieValue = $user->getAuthIdentifier() . '|' . $token;
        $lifetime = 60 * 24 * 365;

        $this->cookieManager->queue($this->getRememberMeCookieName(), $cookieValue, $lifetime);
    }

    protected function clearRememberMeCookie(): void
    {
        $user = $this->user();
        if ($user && $user->getRememberToken()) {
            $user->setRememberToken(null);
            $user->save();
        }

        $this->cookieManager->queue($this->getRememberMeCookieName(), '', -2628000);
    }
}
