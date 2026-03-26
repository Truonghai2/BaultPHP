<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Core\Application;
use Core\Http\Deduplication\RequestLockManager;
use Core\Http\Deduplication\RequestSignatureGenerator;
use Core\Http\Deduplication\ResponseCache;
use Core\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Deduplicate request: cùng signature chỉ một request xử lý, response cache chia sẻ.
 *
 * - Opt-in: dùng included_paths (chỉ path khớp prefix) hoặc excluded_paths (bỏ qua path).
 * - Mode: cache_only (không chờ, miss thì xử lý) hoặc coalesce (chờ request đầu xong rồi lấy cache).
 */
class RequestDeduplicationMiddleware implements MiddlewareInterface
{
    protected ?RequestSignatureGenerator $signatureGenerator = null;
    protected ?RequestLockManager $lockManager = null;
    protected ?ResponseCache $responseCache = null;

    /** @var array<string, mixed> */
    protected array $config = [];

    public function __construct(
        protected Application $app,
    ) {
        $this->config = config('deduplication', []);
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if (!$this->shouldDeduplicate($request)) {
            return $handler->handle($request);
        }

        $this->initializeDependencies();
        if ($this->responseCache === null || $this->lockManager === null) {
            return $handler->handle($request);
        }

        $signature = $this->signatureGenerator->generate($request, [
            'include_user' => $this->config['include_user'] ?? false,
            'include_headers' => $this->config['include_headers'] ?? [],
        ]);

        $cached = $this->responseCache->get($signature);
        if ($cached !== null) {
            return $cached;
        }

        $acquired = $this->lockManager->acquireLock($signature);
        if ($acquired) {
            try {
                $response = $handler->handle($request);
                $this->responseCache->store($signature, $response);
                return $response;
            } finally {
                $this->lockManager->releaseLock($signature);
            }
        }

        $mode = $this->config['mode'] ?? 'cache_only';
        if ($mode === 'cache_only') {
            return $handler->handle($request);
        }

        $maxWait = (int) ($this->config['max_wait'] ?? 15);
        $waited = $this->lockManager->waitForLock($signature, $maxWait);
        if ($waited) {
            $cached = $this->responseCache->get($signature);
            if ($cached !== null) {
                return $cached;
            }
        }

        return $handler->handle($request);
    }

    protected function shouldDeduplicate(ServerRequestInterface $request): bool
    {
        if (!($this->config['enabled'] ?? false)) {
            return false;
        }
        if ($request->getMethod() !== 'GET') {
            return false;
        }

        $route = $request->getAttribute('route');
        if ($route !== null && isset($route->middleware)) {
            $mw = is_array($route->middleware) ? $route->middleware : [$route->middleware];
            if (in_array('no-deduplication', $mw, true)) {
                return false;
            }
        }

        $path = $request->getUri()->getPath();

        $excluded = $this->config['excluded_paths'] ?? [];
        foreach ($excluded as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        $included = $this->config['included_paths'] ?? [];
        if ($included !== []) {
            foreach ($included as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    protected function initializeDependencies(): void
    {
        if ($this->signatureGenerator !== null) {
            return;
        }
        try {
            if (!$this->app->bound(CacheInterface::class)) {
                return;
            }
            $cache = $this->app->make(CacheInterface::class);
            if ($cache === null) {
                return;
            }

            $this->signatureGenerator = new RequestSignatureGenerator();
            $this->lockManager = new RequestLockManager(
                $cache,
                (int) ($this->config['lock_timeout'] ?? 30),
                (int) ($this->config['lock_wait_interval'] ?? 50),
            );
            $prefix = (string) ($this->config['cache_key_prefix'] ?? 'dedup:');
            $this->responseCache = new ResponseCache(
                $cache,
                (int) ($this->config['cache_ttl'] ?? 60),
                $prefix !== '' ? $prefix . 'resp:' : 'resp:',
            );
        } catch (\Throwable $e) {
            Log::warning('RequestDeduplicationMiddleware: init failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
