<?php

namespace App\Http\Middleware;

use Core\Cache\CacheManager;
use Core\Filesystem\Filesystem;
use Core\Http\Redirector;
use Core\ORM\Connection;
use Core\Support\Facades\Cache;
use Modules\User\Domain\Services\AccessControlService;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CheckForPendingModulesMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Filesystem $fs,
        private Connection $connection,
        private Redirector $redirector,
        private CacheManager $cache,
        private AccessControlService $accessControlService,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = auth()->user();

        if (!$user || !$this->accessControlService->isSuperAdmin($user)) {
            return $handler->handle($request);
        }

        $excludedPaths = [
            '/admin/modules/install/confirm',
            '/api/',
        ];

        foreach ($excludedPaths as $path) {
            if (str_starts_with($request->getUri()->getPath(), $path)) {
                return $handler->handle($request);
            }
        }

        $pendingModules = $this->getPendingModules();

        if (!empty($pendingModules)) {
            return $this->redirector
                ->to('/admin/modules/install/confirm')
                ->with('pending_modules', $pendingModules);
        }

        return $handler->handle($request);
    }

    /**
     * Lấy danh sách các module có trên hệ thống file nhưng chưa được đăng ký trong CSDL.
     */
    private function getPendingModules(): array
    {
        $cacheKey = 'modules:installed_names';

        try {
            // Cố gắng lấy từ cache trước
            $installedNames = $this->cache->get($cacheKey);

            if (is_null($installedNames)) {
                $pdo = $this->connection->connection();
                $stmt = $pdo->query('SELECT name FROM modules');
                $installedNames = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
                $this->cache->forever($cacheKey, $installedNames);
            }

            $modulesPath = base_path('Modules');
            if (!$this->fs->isDirectory($modulesPath)) {
                return [];
            }

            $allDirs = $this->fs->directories($modulesPath);
            $allModuleNames = array_map('basename', $allDirs);

            return array_diff($allModuleNames, $installedNames);
        } catch (\Throwable) {
            return [];
        }
    }
}
