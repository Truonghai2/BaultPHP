<?php

namespace Core\Auth;

use Core\Application;
use Core\Auth\Events\Authenticated;
use Core\Auth\Events\Login;
use Core\Auth\Events\Logout;
use Core\Contracts\Auth\Authenticatable;
use Core\Contracts\Auth\Guard;
use Core\Contracts\Auth\UserProvider;
use Core\Cookie\CookieManager;
use Core\Support\Facades\Hash;
use Psr\EventDispatcher\EventDispatcherInterface;
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
    protected ?EventDispatcherInterface $dispatcher;
    protected bool $userResolved = false;

    public function __construct(Application $app, Session $session, UserProvider $provider)
    {
        $this->app = $app;
        $this->cookieManager = $app->make(CookieManager::class);
        $this->session = $session;
        $this->provider = $provider;
        $this->dispatcher = $app->has(EventDispatcherInterface::class) ? $app->make(EventDispatcherInterface::class) : null;
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
                $this->userResolved = true;
                return $this->user;
            }
        }

        $recaller = $this->getRecallerFromCookie();

        if (!is_null($recaller)) {
            $user = $this->userFromRecaller($recaller);

            if (!is_null($user)) {
                $this->login($user, true); // Log them in properly, which will also fire the Login event.
                $this->user = $user;
            } else {
                $this->forgetRecaller();
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

        $this->dispatcher?->dispatch(new Login('session', $user, $remember));
    }

    public function logout(): void
    {
        $user = $this->user;

        // Lấy selector từ cookie trước khi xóa nó
        $recaller = $this->getRecallerFromCookie();
        if ($recaller && isset($recaller['selector'])) {
            $this->removeRememberToken($recaller['selector']);
        }

        $this->forgetRecallerCookie();

        $this->user = null;
        $this->userResolved = false;

        $this->session->remove($this->getName());

        $this->dispatcher?->dispatch(new Logout('session', $user));
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
        return !is_null($user) && Hash::check($credentials['password'], $user->getAuthPassword());
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
        $this->userResolved = true;
    }

    protected function fireAuthenticatedEvent(Authenticatable $user): void
    {
        $this->user = $user;
        $this->dispatcher?->dispatch(new Authenticated('session', $user));
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

        if (hash_equals($tokenRecord->verifier_hash, hash('sha256', $recaller['verifier']))) {
            // Token hợp lệ, đăng nhập người dùng và tái tạo token
            $user = $this->provider->retrieveById($tokenRecord->user_id);
            if ($user) {
                $this->updateRememberToken($tokenRecord->selector);
                return $user;
            }
        } else {
            // Phát hiện tấn công! Selector hợp lệ nhưng verifier sai.
            // Xóa tất cả token của người dùng này.
            RememberToken::where('user_id', $tokenRecord->user_id)->delete();
            $this->forgetRecallerCookie();
            // Bạn có thể dispatch một event ở đây để ghi log hoặc thông báo cho người dùng.
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
        $selector = bin2hex(random_bytes(16));
        $verifier = bin2hex(random_bytes(32));

        RememberToken::create([
            'user_id' => $user->getAuthIdentifier(),
            'selector' => $selector,
            'verifier_hash' => hash('sha256', $verifier),
        ]);

        $cookieValue = $selector . ':' . $verifier;
        $lifetime = 60 * 24 * 365;

        $this->cookieManager->queue($this->getRememberMeCookieName(), $cookieValue, $lifetime);
    }

    /**
     * Cập nhật verifier cho một token hiện có và gửi lại cookie mới.
     */
    protected function updateRememberToken(string $selector): void
    {
        $newVerifier = bin2hex(random_bytes(32));

        RememberToken::where('selector', $selector)->update([
            'verifier_hash' => hash('sha256', $newVerifier),
        ]);

        $cookieValue = $selector . ':' . $newVerifier;
        $lifetime = 60 * 24 * 365;

        $this->cookieManager->queue($this->getRememberMeCookieName(), $cookieValue, $lifetime);
    }
}
