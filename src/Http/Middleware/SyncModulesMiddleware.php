<?php

namespace App\Http\Middleware;

use Closure;
use Core\Cache\CacheManager;
use Core\Module\ModuleSynchronizer;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class SyncModulesMiddleware
{
    // Cache key to store the last sync timestamp
    private const CACHE_KEY = 'modules_last_sync_timestamp';
    // Cooldown period in seconds between syncs
    private const SYNC_COOLDOWN = 300; // 5 minutes

    public function __construct(
        private ?CacheManager $cache,
        private ModuleSynchronizer $synchronizer,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        // If cache is not available or we are in debug mode, always attempt to sync.
        if ($this->cache && !env('APP_DEBUG', false)) {
            $lastSync = (int) $this->cache->get(self::CACHE_KEY);
            if (time() - $lastSync < self::SYNC_COOLDOWN) {
                // Not time to sync yet, skip.
                return $next($request);
            }
        }

        try {
            $result = $this->synchronizer->sync();
            // Update the timestamp after a successful sync
            $this->cache?->set(self::CACHE_KEY, time());

            // Attach the sync result to the request for the controller to use
            $request->attributes->set('sync_result', $result);
        } catch (Throwable $e) {
            // Log the error but don't block the user's request
            Log::error('Automatic module synchronization failed', ['exception' => $e]);
        }

        return $next($request);
    }
}
