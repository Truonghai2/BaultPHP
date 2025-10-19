<?php

namespace Core\Auth;

use Core\Application;
use Core\Auth\Events\Authenticated;
use Core\Auth\Events\CookieTheftDetected;
use Core\Auth\Events\Login;
use Core\Auth\Events\Logout;
use Core\Contracts\Auth\Authenticatable;
use Core\Contracts\Auth\Guard;
use Core\Contracts\Auth\UserProvider;
use Core\Contracts\Session\SessionInterface as Session;
use Core\Cookie\CookieManager;
use Core\Support\Facades\Hash;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;

class SessionGuard implements Guard
{
    protected string $name;
    protected Application $app;
    /**
     * @var CookieManager
     */
    protected CookieManager $cookieManager;
    protected Session $session;
    protected UserProvider $provider;
    protected ?Authenticatable $user = null;
    protected ?EventDispatcherInterface $dispatcher;
    protected bool $userResolved = false;
    protected ?ServerRequestInterface $request;

    public function __construct(string $name, Application $app, Session $session, UserProvider $provider)
    {
        $this->name = $name;
        $this->app = $app;
        $this->cookieManager = $app->make(CookieManager::class);
        $this->session = $session;
        $this->provider = $provider;
        $this->dispatcher = $app->has(EventDispatcherInterface::class) ? $app->make(EventDispatcherInterface::class) : null;
        $this->request = $app->has(ServerRequestInterface::class) ? $app->make(ServerRequestInterface::class) : null;
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
            $user = $this->provider->retrieveById($id);
            if (!is_null($user)) {
                $this->fireAuthenticatedEvent($user);
                return $this->user;
            }
        }

        $recaller = $this->getRecallerFromCookie();

        if (!is_null($recaller)) {
            $user = $this->userFromRecaller($recaller);

            if (!is_null($user)) {
                $this->updateSession($user->getAuthIdentifier());

                $this->dispatcher?->dispatch(new Login('session', $user, true));

                $this->setUser($user);
            } else {
                $this->forgetRecallerCookie();
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
        $startTime = microtime(true);
        $this->app->make(\Psr\Log\LoggerInterface::class)->info('SessionGuard login process started.', ['user_id' => $user->getAuthIdentifier()]);

        $this->updateSession($user->getAuthIdentifier());

        if ($remember) {
            $this->createRememberMeCookie($user);
        }

        $this->setUser($user);

        $this->dispatcher?->dispatch(new Login('session', $user, $remember));

        $duration = microtime(true) - $startTime;
        $this->app->make(\Psr\Log\LoggerInterface::class)->info('SessionGuard login process finished.', [
            'user_id' => $user->getAuthIdentifier(),
            'duration_ms' => $duration * 1000,
        ]);
    }

    public function logout(): void
    {
        $user = $this->user();

        $recaller = $this->getRecallerFromCookie();

        if ($user) {
            if ($recaller && isset($recaller['selector'])) {
                $this->removeRememberToken($recaller['selector']);
            }

            $this->dispatcher?->dispatch(new Logout('session', $user));
        }

        $this->forgetRecallerCookie();
        $this->session->invalidate();
        $this->user = null;
        $this->userResolved = false;
    }

    public function attempt(array $credentials = [], bool $remember = false): ?Authenticatable
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($this->hasValidCredentials($user, $credentials)) {
            $this->login($user, $remember);
            return $user;
        }

        return null;
    }

    protected function hasValidCredentials(?Authenticatable $user, array $credentials): bool
    {
        if (is_null($user)) {
            return false;
        }

        return Hash::check($credentials['password'], $user->getAuthPassword());
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
        $this->userResolved = true;
    }

    protected function fireAuthenticatedEvent(Authenticatable $user): void
    {
        $this->user = $user;
        $this->userResolved = true;
        $this->dispatcher?->dispatch(new Authenticated('session', $user));
    }

    protected function getName(): string
    {
        return 'login_' . $this->name . '_' . sha1(static::class);
    }

    protected function getRememberMeCookieName(): string
    {
        return 'remember_' . $this->name . '_' . sha1(static::class);
    }

    protected function updateSession(string|int $id): void
    {
        $logger = $this->app->make(\Psr\Log\LoggerInterface::class);
        $startTime = microtime(true);
        $logger->info('Updating session and migrating ID.', ['user_id' => $id]);

        $this->session->set($this->getName(), $id);
        $this->session->regenerate(true);

        $duration = microtime(true) - $startTime;
        $logger->info('Session ID migration finished.', [
            'duration_ms' => $duration * 1000,
        ]);
    }

    /**
     * Lấy payload (id và token) từ cookie "remember me".
     *
     * @return array{selector: string, verifier: string}|null
     */
    protected function getRecallerFromCookie(): ?array
    {
        $cookie = $this->cookieManager->get($this->getRememberMeCookieName());

        if (!$cookie || !str_contains($cookie, ':')) {
            return null;
        }

        [$selector, $verifier] = explode(':', $cookie, 2);

        return ($selector && $verifier) ? ['selector' => $selector, 'verifier' => $verifier] : null;
    }

    /**
     * Attempt to retrieve a user by the "remember me" cookie's data.
     *
     * @param array $recaller
     * @return \Core\Contracts\Auth\Authenticatable|null
     */
    protected function userFromRecaller(array $recaller): ?Authenticatable
    {
        if (!isset($recaller['selector']) || !isset($recaller['verifier'])) {
            return null;
        }

        $tokenRecord = RememberToken::where('selector', $recaller['selector'])->first();

        if (!$tokenRecord) {
            return null;
        }

        if ($tokenRecord->user_agent !== $this->getUserAgent() || $tokenRecord->ip_address !== $this->getIpAddress()) {
            $this->removeRememberToken($tokenRecord->selector);
            $this->forgetRecallerCookie();
            return null;
        }

        if (hash_equals($tokenRecord->verifier_hash, hash('sha256', $recaller['verifier']))) {
            $user = $this->provider->retrieveById($tokenRecord->user_id);

            if ($user) {
                $this->regenerateRememberToken($tokenRecord, $user);
                return $user;
            }

            $this->removeRememberToken($tokenRecord->selector);
        } else {
            $this->dispatcher?->dispatch(new CookieTheftDetected($tokenRecord->user_id));
            RememberToken::where('user_id', $tokenRecord->user_id)->delete();
            $this->forgetRecallerCookie();
        }

        return null;
    }

    /**
     * Chỉ xóa cookie khỏi trình duyệt.
     */
    protected function forgetRecallerCookie(): void
    {
        $this->cookieManager->queue($this->getRememberMeCookieName(), '', -2628000);
    }

    /**
     * Xóa một remember token khỏi CSDL bằng selector.
     */
    protected function removeRememberToken(string $selector): void
    {
        RememberToken::where('selector', $selector)->delete();
    }

    protected function createRememberMeCookie(Authenticatable $user): void
    {
        $logger = $this->app->make(\Psr\Log\LoggerInterface::class);
        $startTime = microtime(true);

        $selector = bin2hex(random_bytes(16));
        $verifier = bin2hex(random_bytes(32));

        $tokenLifetime = 60 * 24 * 30;

        RememberToken::create([
            'user_id' => $user->getAuthIdentifier(),
            'selector' => $selector,
            'verifier_hash' => hash('sha256', $verifier),
            'user_agent' => $this->getUserAgent(),
            'ip_address' => $this->getIpAddress(),
            'expires_at' => date('Y-m-d H:i:s', time() + $tokenLifetime),
        ]);

        $cookieValue = $selector . ':' . $verifier;
        $lifetime = 60 * 24 * 365;

        $this->cookieManager->queue($this->getRememberMeCookieName(), $cookieValue, $lifetime);

        $duration = microtime(true) - $startTime;
        $logger->info('Remember me cookie created.', [
            'user_id' => $user->getAuthIdentifier(),
            'duration_ms' => $duration * 1000,
        ]);
    }

    /**
     * Tạo lại hoàn toàn remember token (selector và verifier) và xóa token cũ.
     * Đây là cơ chế xoay vòng token (token rotation) đầy đủ.
     *
     * @param RememberToken $tokenRecord Token cũ cần được thay thế.
     * @param Authenticatable $user The user associated with the token.
     */
    protected function regenerateRememberToken(RememberToken $tokenRecord, Authenticatable $user): void
    {
        $this->createRememberMeCookie($user);

        $this->removeRememberToken($tokenRecord->selector);
    }

    /**
     * Get the user agent from the request.
     */
    protected function getUserAgent(): ?string
    {
        return $this->request?->getHeaderLine('User-Agent');
    }

    /**
     * Get the IP address from the request.
     */
    protected function getIpAddress(): ?string
    {
        if (!$this->request) {
            return null;
        }
        $serverParams = $this->request->getServerParams();
        return $serverParams['remote_addr'] ?? null;
    }
}
