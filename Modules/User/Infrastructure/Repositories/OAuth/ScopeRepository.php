<?php

namespace Modules\User\Infrastructure\Repositories\OAuth;

use Core\Application;
use Core\Cache\CacheManager;
use Core\Support\Facades\Gate;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Modules\User\Domain\Entities\OAuth\ScopeEntity;
use Modules\User\Infrastructure\Models\OAuth\Scope as ScopeModel;
use Modules\User\Infrastructure\Models\User;

/**
 * Optimized ScopeRepository with multi-level caching.
 *
 * PERFORMANCE OPTIMIZATIONS:
 * - L1: Request-level cache
 * - L2: APCu cache (1 hour TTL - scopes rarely change)
 * - L3: Redis cache (1 hour TTL)
 * - L4: Database query (fallback)
 */
class ScopeRepository implements ScopeRepositoryInterface
{
    /**
     * Request-level cache for scope entities
     */
    private static array $requestCache = [];

    /**
     * Cache manager for persistent caching
     */
    private ?CacheManager $cacheManager = null;

    /**
     * Application instance
     */
    private ?Application $app = null;

    /**
     * Cache TTLs (scopes rarely change, so longer TTL)
     */
    private const L2_TTL = 3600; // 1 hour (APCu)
    private const L3_TTL = 3600; // 1 hour (Redis)

    /**
     * Check if APCu is available and enabled
     *
     * @return bool
     */
    private static function isApcuAvailable(): bool
    {
        return apcu_available();
    }

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
     *
     * PERFORMANCE: Multi-level caching
     */
    public function getScopeEntityByIdentifier($identifier)
    {
        // L1: Check request cache first
        $l1Key = "oauth:scope:{$identifier}";
        if (isset(self::$requestCache[$l1Key])) {
            return self::$requestCache[$l1Key];
        }

        // L2: Check APCu cache (if available)
        if (self::isApcuAvailable()) {
            $l2Data = apcu_get("oauth:scope:{$identifier}");
            if ($l2Data instanceof ScopeEntity) {
                self::$requestCache[$l1Key] = $l2Data;
                return $l2Data;
            }
        }

        // L3: Check persistent cache
        if ($this->cacheManager) {
            $l3Data = $this->cacheManager->store()->get("oauth:scope:{$identifier}");
            if ($l3Data instanceof ScopeEntity) {
                self::$requestCache[$l1Key] = $l3Data;
                // Populate L2 cache if available
                if (self::isApcuAvailable()) {
                    apcu_set("oauth:scope:{$identifier}", $l3Data, self::L2_TTL);
                }
                return $l3Data;
            }
        }

        // L4: Database query
        /** @var ScopeModel|null $scope */
        $scope = ScopeModel::where('id', '=', $identifier)->first();

        if (!$scope) {
            return null;
        }

        $scopeEntity = new ScopeEntity();
        $scopeEntity->setIdentifier($scope->id);

        if (isset($scope->description)) {
            $scopeEntity->setDescription($scope->description);
        }

        // Cache at all levels
        self::$requestCache[$l1Key] = $scopeEntity;

        // Cache using helper function (handles both APCu and persistent cache)
        oauth_cache_scope($identifier, $scopeEntity, self::L2_TTL, self::L3_TTL);

        return $scopeEntity;
    }

    /**
     * {@inheritdoc}
     */
    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null,
    ) {
        $clientRestrictedScopes = config('oauth2.restricted_scopes', []);
        $clientId = $clientEntity->getIdentifier();

        $allowedScopes = array_filter($scopes, function (ScopeEntityInterface $scope) use ($clientId, $clientRestrictedScopes) {
            $scopeIdentifier = $scope->getIdentifier();

            if (array_key_exists($scopeIdentifier, $clientRestrictedScopes)) {
                $allowedClients = $clientRestrictedScopes[$scopeIdentifier];
                if (!in_array($clientId, $allowedClients, true)) {
                    return false;
                }
            }
            return true;
        });

        if ($userIdentifier !== null) {
            $user = User::find($userIdentifier);
            $userRestrictedScopes = config('oauth2.user_restricted_scopes', []);

            if ($user) {
                $allowedScopes = array_filter($allowedScopes, function (ScopeEntityInterface $scope) use ($user, $userRestrictedScopes) {
                    $scopeIdentifier = $scope->getIdentifier();

                    if (array_key_exists($scopeIdentifier, $userRestrictedScopes)) {
                        $permissionName = $userRestrictedScopes[$scopeIdentifier];
                        if (!Gate::check($user, $permissionName)) {
                            return false;
                        }
                    }
                    return true;
                });
            }
        }

        return array_values($allowedScopes);
    }

    /**
     * Clear cache for a specific scope.
     * Should be called when scope data is updated.
     */
    public function clearCache(string $identifier): void
    {
        $l1Key = "oauth:scope:{$identifier}";
        unset(self::$requestCache[$l1Key]);

        // Clear cache using helper function
        oauth_clear_scope_cache($identifier);
    }

    /**
     * Clear all caches (useful for testing or maintenance).
     */
    public static function clearAllCaches(): void
    {
        self::$requestCache = [];
    }
}
