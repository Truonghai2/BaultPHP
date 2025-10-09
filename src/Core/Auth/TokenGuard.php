<?php

namespace Core\Auth;

use Core\Application;
use Core\Auth\Events\Authenticated;
use Core\Auth\Events\Login;
use Core\Auth\Events\Logout;
use Core\Cache\CacheManager;
use Core\Contracts\Auth\Authenticatable;
use Core\Contracts\Auth\Guard;
use Laminas\Diactoros\ServerRequest;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Modules\User\Infrastructure\Models\User;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A guard that validates OAuth2 access tokens (JWTs) for stateless authentication.
 * This guard centralizes the logic for validating tokens from both HTTP requests
 * and direct token strings (e.g., for WebSockets).
 */
class TokenGuard implements Guard
{
    protected string $name;
    protected ?Authenticatable $user = null;
    protected bool $userResolved = false;
    protected ?EventDispatcherInterface $dispatcher;
    protected array $validatedRequestAttributes = [];

    public function __construct(
        string $name,
        protected Application $app,
        protected ResourceServer $resourceServer,
        protected CacheManager $cache,
    ) {
        $this->dispatcher = $this->app->has(EventDispatcherInterface::class) ? $this->app->make(EventDispatcherInterface::class) : null;
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

        try {
            $this->validate();
        } catch (OAuthServerException) {
            return null;
        }

        return $this->user;
    }

    public function id()
    {
        if (isset($this->validatedRequestAttributes['oauth_user_id'])) {
            return $this->validatedRequestAttributes['oauth_user_id'];
        }

        return $this->user()?->getAuthIdentifier() ?? null;
    }

    /**
     * Validate the incoming request and authenticate the user.
     * Throws OAuthServerException on failure.
     *
     * @return Authenticatable
     * @throws OAuthServerException
     */
    public function validate(): Authenticatable
    {
        if ($this->userResolved) {
            return $this->user;
        }

        $request = $this->getRequest();

        if (!$request || !$request->hasHeader('authorization')) {
            throw OAuthServerException::accessDenied('Missing "Authorization" header');
        }

        $user = $this->validateRequestAndGetUser($request);
        $this->setUser($user);

        return $this->user;
    }

    /**
     * Authenticate a user from a raw JWT string.
     *
     * @param string $token
     * @return Authenticatable|null
     * @throws OAuthServerException
     */
    public function userFromToken(string $token): ?Authenticatable
    {
        $request = (new ServerRequest())->withHeader('Authorization', 'Bearer ' . $token);
        return $this->validateRequestAndGetUser($request);
    }

    protected function validateRequestAndGetUser(ServerRequestInterface $request): ?Authenticatable
    {
        $validatedRequest = $this->resourceServer->validateAuthenticatedRequest($request);

        $this->validatedRequestAttributes = [
            'oauth_user_id' => $validatedRequest->getAttribute('oauth_user_id'),
            'oauth_scopes' => $validatedRequest->getAttribute('oauth_scopes', []),
        ];

        $userId = $this->validatedRequestAttributes['oauth_user_id'];

        if (!$userId) {
            return null;
        }

        $cacheKey = "user:{$userId}";

        $user = $this->cache->get($cacheKey);

        if ($user) {
            return $user;
        }

        $user = $this->cache->lock("lock:{$cacheKey}", 10)->block(5, function () use ($cacheKey, $userId) {
            $cachedUser = $this->cache->get($cacheKey);
            if ($cachedUser) {
                return $cachedUser;
            }

            $userFromDb = User::find($userId);
            $this->cache->forever($cacheKey, $userFromDb);
            return $userFromDb;
        });

        if (!$user) {
            throw new OAuthServerException('Access token is not associated with a valid user.', 10, 'invalid_grant', 401);
        }

        $this->dispatcher?->dispatch(new Authenticated('token', $user));

        return $user;
    }

    public function getScopes(): array
    {
        return $this->validatedRequestAttributes['oauth_scopes'] ?? [];
    }

    protected function getRequest(): ?ServerRequestInterface
    {
        return $this->app->has(ServerRequestInterface::class) ? $this->app->make(ServerRequestInterface::class) : null;
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
        $this->userResolved = true;
    }

    /**
     * Log a user into the application.
     *
     * For a token-based guard, this doesn't create a session. It simply sets
     * the user for the current request instance.
     */
    public function login(Authenticatable $user): void
    {
        // Dispatch event để đảm bảo tính nhất quán với SessionGuard
        $this->dispatcher?->dispatch(new Login('token', $user, false));
        $this->setUser($user);
    }

    public function logout(): void
    {
        $user = $this->user;

        // Dispatch event trước khi xóa user
        $this->dispatcher?->dispatch(new Logout('token', $user));

        $this->user = null;
        $this->userResolved = false;
        $this->validatedRequestAttributes = [];
    }

    public function attempt(array $credentials = []): bool
    {
        return false;
    }
}
