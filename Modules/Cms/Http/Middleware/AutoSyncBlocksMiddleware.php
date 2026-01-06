<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Middleware;

use Modules\Cms\Domain\Services\BlockSyncService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Auto Sync Blocks Middleware
 * 
 * Automatically syncs blocks in development mode
 * Only runs in local environment and respects cache
 */
class AutoSyncBlocksMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly BlockSyncService $syncService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Only auto-sync in local/development environment
        if (config('app.env') === 'local' && config('cms.auto_sync_blocks', true)) {
            try {
                // Sync blocks (will check cache internally)
                $this->syncService->syncBlocks();
            } catch (\Throwable $e) {
                // Don't break the request if sync fails
                $this->logger->error('Auto-sync blocks failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $handler->handle($request);
    }
}

