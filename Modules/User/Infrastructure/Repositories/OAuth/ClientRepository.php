<?php

namespace Modules\User\Infrastructure\Repositories\OAuth;

use Core\Application;
use Core\Cache\CacheManager;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Modules\User\Domain\Entities\OAuth\ClientEntity;
use Modules\User\Infrastructure\Models\OAuth\Client as ClientModel;

/**
 * Optimized ClientRepository with multi-level caching.
 * 
 * PERFORMANCE OPTIMIZATIONS:
 * - L1: Request-level cache (fastest, no I/O)
 * - L2: APCu cache (shared memory, 5min TTL)
 * - L3: Redis cache (persistent, 10min TTL)
 * - L4: Database query (fallback)
 */
class ClientRepository implements ClientRepositoryInterface
{
    /**
     * Request-level cache for client entities
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
     * Cache TTLs
     */
    private const L2_TTL = 300; // 5 minutes (APCu)
    private const L3_TTL = 600; // 10 minutes (Redis)
    
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
    public function getClientEntity($clientIdentifier)
    {
        // L1: Check request cache first
        $l1Key = "oauth:client:{$clientIdentifier}";
        if (isset(self::$requestCache[$l1Key])) {
            return self::$requestCache[$l1Key];
        }

        // L2: Check APCu cache (if available)
        if (self::isApcuAvailable()) {
            $l2Data = apcu_get("oauth:client:{$clientIdentifier}");
            if ($l2Data instanceof ClientEntity) {
                self::$requestCache[$l1Key] = $l2Data;
                return $l2Data;
            }
        }

        // L3: Check persistent cache
        if ($this->cacheManager) {
            $l3Data = $this->cacheManager->store()->get("oauth:client:{$clientIdentifier}");
            if ($l3Data instanceof ClientEntity) {
                self::$requestCache[$l1Key] = $l3Data;
                // Populate L2 cache if available
                if (self::isApcuAvailable()) {
                    apcu_set("oauth:client:{$clientIdentifier}", $l3Data, self::L2_TTL);
                }
                return $l3Data;
            }
        }

        // L4: Database query
        /** @var ClientModel|null $client */
        $client = ClientModel::where('id', '=', $clientIdentifier)->first();

        if (!$client || $client->revoked) {
            return null;
        }

        $clientEntity = new ClientEntity($client->id);
        $clientEntity->setName($client->name);
        $clientEntity->setRedirectUri(explode(',', $client->redirect));
        $clientEntity->isConfidential(!is_null($client->secret));

        // Cache at all levels
        self::$requestCache[$l1Key] = $clientEntity;
        
        // Cache using helper function (handles both APCu and persistent cache)
        oauth_cache_client($clientIdentifier, $clientEntity, self::L2_TTL, self::L3_TTL);

        return $clientEntity;
    }

    /**
     * {@inheritdoc}
     * 
     * PERFORMANCE: Reuse cached client entity, only validate secret
     */
    public function validateClient($clientIdentifier, $clientSecret, $grantType)
    {
        // Get client entity (will use cache)
        $clientEntity = $this->getClientEntity($clientIdentifier);
        
        if (!$clientEntity) {
            return false;
        }

        // For secret validation, we need to query the model
        // (secret is not stored in entity for security)
        /** @var ClientModel|null $client */
        $client = ClientModel::where('id', '=', $clientIdentifier)->first();

        if (!$client || $client->revoked) {
            return false;
        }

        if ($client->secret !== null) {
            if (!$clientSecret) {
                return false;
            }
            
            if (str_starts_with($client->secret, '$2y$')) {
                return password_verify($clientSecret, $client->secret);
            } else {
                return hash_equals((string) $client->secret, $clientSecret);
            }
        }
        elseif ($clientSecret !== null) {
            return false;
        }

        return true;
    }
    
    /**
     * Clear cache for a specific client.
     * Should be called when client data is updated.
     */
    public function clearCache(string $clientIdentifier): void
    {
        $l1Key = "oauth:client:{$clientIdentifier}";
        unset(self::$requestCache[$l1Key]);
        
        // Clear cache using helper function
        oauth_clear_client_cache($clientIdentifier);
    }
    
    /**
     * Clear all caches (useful for testing or maintenance).
     */
    public static function clearAllCaches(): void
    {
        self::$requestCache = [];
    }
}
