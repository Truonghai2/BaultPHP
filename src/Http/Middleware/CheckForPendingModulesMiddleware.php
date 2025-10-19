<?php

namespace App\Http\Middleware;

use Core\Cache\CacheManager;
use Core\FileSystem\Filesystem;
use Core\Http\Redirector;
use Core\ORM\Connection;
use Core\Support\Facades\Cache;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CheckForPendingModulesMiddleware implements MiddlewareInterface
{
    /**
     * Cache key để lưu trạng thái kiểm tra.
     * @var string
     */
    private const PENDING_CHECK_CACHE_KEY = 'modules:pending_check';

    public function __construct(
        private Filesystem $fs,
        private Connection $connection,
        private Redirector $redirector,
        private CacheManager $cache,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = auth()->user();

        if (!$user || !$user->isSuperAdmin()) {
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

        $pendingModules = $this->cache->remember(
            self::PENDING_CHECK_CACHE_KEY,
            600,
            fn () => $this->scanForPendingModules(),
        );

        if (!empty($pendingModules)) {
            if (session()->has('pending_modules')) {
                return $handler->handle($request);
            }
            return $this->redirector
                ->to('/admin/modules/install/confirm')
                ->with('pending_modules', $pendingModules);
        }

        return $handler->handle($request);
    }

    /**
     * Lấy danh sách các module có trên hệ thống file nhưng chưa được đăng ký trong CSDL.
     * Phương thức này thực hiện I/O và chỉ nên được gọi khi cache đã hết hạn.
     */
    private function scanForPendingModules(): array
    {
        try {
            $modulesPath = base_path('Modules');
            if (!$this->fs->isDirectory($modulesPath)) {
                return [];
            }
            $allModuleDirs = $this->fs->directories($modulesPath);
            $allModuleNames = array_map('basename', $allModuleDirs);

            $pdo = $this->connection->connection();
            $stmt = $pdo->query('SELECT name FROM modules');
            $installedNames = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

            return array_diff($allModuleNames, $installedNames);
        } catch (\Throwable) {
            return [];
        }
    }
}
