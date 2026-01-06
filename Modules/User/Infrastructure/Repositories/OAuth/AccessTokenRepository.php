<?php

namespace Modules\User\Infrastructure\Repositories\OAuth;

use Core\Application;
use Core\Cache\CacheManager;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Modules\User\Domain\Entities\OAuth\AccessTokenEntity;
use Modules\User\Infrastructure\Models\OAuth\AccessToken as AccessTokenModel;

/**
 * Optimized AccessTokenRepository with caching for token validation.
 *
 * PERFORMANCE OPTIMIZATIONS:
 * - Cache token revocation status
 * - Request-level cache for validation results
 */
class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    /**
     * Request-level cache for token revocation status
     */
    private static array $revocationCache = [];

    /**
     * Cache manager for persistent caching
     */
    private ?CacheManager $cacheManager = null;

    /**
     * Application instance
     */
    private ?Application $app = null;

    /**
     * Cache TTL for token validation (short TTL for security)
     */
    private const VALIDATION_CACHE_TTL = 60; // 1 minute

    public function __construct(?Application $app = null)
    {
        $this->app = $app;

        // Get cache manager if available
        if ($app && $app->has(CacheManager::class)) {
            $this->cacheManager = $app->make(CacheManager::class);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null)
    {
        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);
        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }
        $accessToken->setUserIdentifier($userIdentifier);

        return $accessToken;
    }

    /**
     * {@inheritdoc}
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity)
    {
        AccessTokenModel::create([
            'id' => $accessTokenEntity->getIdentifier(),
            'user_id' => $accessTokenEntity->getUserIdentifier(),
            'client_id' => $accessTokenEntity->getClient()->getIdentifier(),
            'scopes' => json_encode(array_map(fn ($scope) => $scope->getIdentifier(), $accessTokenEntity->getScopes())),
            'revoked' => false,
            'expires_at' => $accessTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * PERFORMANCE: Clear cache when revoking
     */
    public function revokeAccessToken($tokenId)
    {
        AccessTokenModel::where('id', '=', $tokenId)->update(['revoked' => true]);

        // Clear caches
        $this->clearTokenCache($tokenId);
    }

    /**
     * {@inheritdoc}
     *
     * PERFORMANCE: Cache validation results
     */
    public function isAccessTokenRevoked($tokenId)
    {
        // L1: Check request cache
        if (isset(self::$revocationCache[$tokenId])) {
            return self::$revocationCache[$tokenId];
        }

        // L2: Check persistent cache (short TTL for security)
        if ($this->cacheManager) {
            $cacheKey = "oauth:token:revoked:{$tokenId}";
            $cached = $this->cacheManager->store()->get($cacheKey);
            if ($cached !== null) {
                self::$revocationCache[$tokenId] = (bool) $cached;
                return self::$revocationCache[$tokenId];
            }
        }

        // L3: Database query
        $token = AccessTokenModel::find($tokenId);
        $revoked = !$token || $token->revoked;

        // Cache the result
        self::$revocationCache[$tokenId] = $revoked;

        if ($this->cacheManager) {
            $this->cacheManager->store()->set(
                "oauth:token:revoked:{$tokenId}",
                $revoked ? 1 : 0,
                self::VALIDATION_CACHE_TTL,
            );
        }

        return $revoked;
    }

    /**
     * Clear cache for a specific token.
     */
    private function clearTokenCache(string $tokenId): void
    {
        unset(self::$revocationCache[$tokenId]);

        if ($this->cacheManager) {
            $this->cacheManager->store()->delete("oauth:token:revoked:{$tokenId}");
        }
    }

    /**
     * Clear all caches (useful for testing or maintenance).
     */
    public static function clearAllCaches(): void
    {
        self::$revocationCache = [];
    }
}
