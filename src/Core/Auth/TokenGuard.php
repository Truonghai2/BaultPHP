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
 * A guard that validates JWT tokens for stateless authentication.
 * This guard centralizes the logic for validating tokens from HTTP requests.
 */
class TokenGuard implements Guard
{
    protected string $name;
    protected ?Authenticatable $user = null;
    protected bool $userResolved = false;
    protected ?EventDispatcherInterface $dispatcher;
    protected array $validatedRequestAttributes = [];

    /**
     * A static cache for user models within a single request lifecycle.
     * @var array<int|string, Authenticatable>
     */
    protected static array $userCache = [];

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
            'oauth_access_token_id' => $validatedRequest->getAttribute('oauth_access_token_id'),
            'oauth_client_id' => $validatedRequest->getAttribute('oauth_client_id'),
        ];

        $userId = $this->validatedRequestAttributes['oauth_user_id'];

        if (!$userId) {
            return null;
        }

        // Check in-memory cache first (valid for current request lifecycle only)
        if (isset(self::$userCache[$userId])) {
            $user = self::$userCache[$userId];
        } else {
            $cacheKey = "oauth:user:{$userId}";

            // Cache user data for the duration of access token TTL
            // This prevents stale data if user is deleted/updated
            $cacheTtl = $this->getAccessTokenTTL();

            $user = $this->cache->remember($cacheKey, $cacheTtl, function () use ($userId) {
                return User::find($userId);
            });
        }

        if (!$user) {
            // Invalidate cache on user not found
            $this->cache->forget("oauth:user:{$userId}");
            throw new OAuthServerException('Access token is not associated with a valid user.', 10, 'invalid_grant', 401);
        }

        $this->dispatcher?->dispatch(new Authenticated('token', $user));

        // Store in request-scoped cache
        self::$userCache[$userId] = $user;

        return $user;
    }

    /**
     * Get the access token TTL in seconds.
     */
    protected function getAccessTokenTTL(): int
    {
        $ttl = $this->app->make('config')->get('oauth2.access_token_ttl', 'PT1H');

        try {
            $interval = new \DateInterval($ttl);
            $reference = new \DateTime();
            $endTime = $reference->add($interval);
            return $endTime->getTimestamp() - (new \DateTime())->getTimestamp();
        } catch (\Exception $e) {
            // Default to 1 hour if parsing fails
            return 3600;
        }
    }

    public function getScopes(): array
    {
        return $this->validatedRequestAttributes['oauth_scopes'] ?? [];
    }

    public function getTokenId(): ?string
    {
        return $this->validatedRequestAttributes['oauth_access_token_id'] ?? null;
    }

    public function getClientId(): ?string
    {
        return $this->validatedRequestAttributes['oauth_client_id'] ?? null;
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
     * Log a user into the application. This doesn't create a session. It simply sets the user for the current request instance.
     */
    public function login(Authenticatable $user, bool $remember = false): void
    {
        $this->dispatcher?->dispatch(new Login('token', $user, false));
        $this->setUser($user);
    }

    public function logout(): void
    {
        $user = $this->user;

        $this->dispatcher?->dispatch(new Logout('token', $user));

        $this->user = null;
        $this->userResolved = false;
        $this->validatedRequestAttributes = [];
    }

    public function attempt(array $credentials = [], bool $remember = false): ?Authenticatable
    {
        return null;
    }
}
