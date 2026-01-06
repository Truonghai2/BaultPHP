<?php

namespace App\Http\Middleware;

use Core\Cache\CacheManager;
use Core\FileSystem\Filesystem;
use Core\Http\Redirector;
use Core\Module\Module;
use Core\Security\CsrfManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Middleware tự động phát hiện module mới chưa được cài đặt
 * Tương tự như Moodle Module Installation Detection
 */
class CheckForPendingModulesMiddleware implements MiddlewareInterface
{
    /**
     * Cache key để lưu danh sách module pending
     */
    private const PENDING_CHECK_CACHE_KEY = 'modules:pending_check';

    /**
     * Cache key để lưu thời gian user skip
     */
    private const USER_SKIP_CACHE_KEY = 'modules:user_skip:';

    /**
     * Thời gian skip (30 phút)
     */
    private const SKIP_DURATION = 1800;

    public function __construct(
        private Filesystem $fs,
        private Redirector $redirector,
        private CacheManager $cache,
        private LoggerInterface $logger,
        private CsrfManager $csrfManager,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = auth()->user();

        // Chỉ kiểm tra với Super Admin
        if (!$user || !$user->isSuperAdmin()) {
            return $handler->handle($request);
        }

        // Danh sách path được bỏ qua (không kiểm tra)
        $excludedPaths = [
            '/admin/modules/install/confirm',
            '/admin/modules/install',
            '/admin/modules/skip-install',
            '/api/',
            '/logout',
        ];

        $currentPath = $request->getUri()->getPath();

        foreach ($excludedPaths as $path) {
            if ($currentPath === $path || ($path !== '/' && str_starts_with($currentPath, rtrim($path, '/') . '/'))) {
                return $handler->handle($request);
            }
        }

        $skipKey = self::USER_SKIP_CACHE_KEY . $user->id;
        if ($this->cache->has($skipKey)) {
            return $handler->handle($request);
        }

        $pendingModules = $this->cache->remember(
            self::PENDING_CHECK_CACHE_KEY,
            600,
            fn () => $this->scanForPendingModules(),
        );

        if (!empty($pendingModules)) {
            session()->set('pending_modules', $pendingModules);
            session()->set('pending_modules_count', count($pendingModules));

            $isAjax = strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
            $isGet = $request->getMethod() === 'GET';
            $currentPath = $request->getUri()->getPath();

            $isOnModulePage = str_starts_with($currentPath, '/admin/modules');

            $hasNotified = session()->get('pending_modules_notified', false);

            if ($isGet && !$isAjax && !$hasNotified && !$isOnModulePage) {
                session()->set('pending_modules_notified', true);
                session()->save();
                return $this->redirector->to('/admin/modules/install/confirm');
            }
        } else {
            session()->remove('pending_modules');
            session()->remove('pending_modules_count');
            session()->remove('pending_modules_notified');
        }

        return $handler->handle($request);
    }

    /**
     * Lấy danh sách các module có trên hệ thống file nhưng chưa được đăng ký trong CSDL.
     * Kèm theo thông tin chi tiết từ module.json
     *
     * Sử dụng logic tương tự module:sync để phát hiện module pending
     */
    private function scanForPendingModules(): array
    {
        try {
            $modulesPath = base_path('Modules');
            if (!$this->fs->isDirectory($modulesPath)) {
                return [];
            }

            // Lấy danh sách module từ filesystem
            $allModuleDirs = $this->fs->directories($modulesPath);
            $filesystemModules = [];

            foreach ($allModuleDirs as $dir) {
                $moduleName = basename($dir);
                $moduleInfo = $this->getModuleInfo($dir);
                if ($moduleInfo) {
                    $filesystemModules[$moduleName] = $moduleInfo;
                }
            }

            // Lấy danh sách module đã đăng ký từ database
            try {
                $registeredModules = Module::all()->pluck('name')->toArray();
            } catch (\Throwable $e) {
                $this->logger->warning('Could not query modules table: ' . $e->getMessage());
                $registeredModules = [];
            }

            // Tìm module chưa đăng ký (pending)
            $pendingNames = array_diff(array_keys($filesystemModules), $registeredModules);

            if (empty($pendingNames)) {
                return [];
            }

            // Trả về thông tin chi tiết của các module pending
            return array_values(array_intersect_key($filesystemModules, array_flip($pendingNames)));
        } catch (\Throwable $e) {
            $this->logger->error('Error scanning for pending modules: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return [];
        }
    }

    /**
     * Đọc thông tin module từ module.json
     */
    private function getModuleInfo(string $modulePath): ?array
    {
        try {
            $jsonPath = $modulePath . '/module.json';

            if (!$this->fs->exists($jsonPath)) {
                return null;
            }

            $json = $this->fs->get($jsonPath);
            $info = json_decode($json, true);

            if (!$info || !isset($info['name'])) {
                return null;
            }

            return [
                'name' => $info['name'],
                'display_name' => $info['display_name'] ?? $info['name'],
                'version' => $info['version'] ?? '1.0.0',
                'description' => $info['description'] ?? 'No description available',
                'author' => $info['author'] ?? 'Unknown',
                'requirements' => $info['requirements'] ?? [],
                'path' => $modulePath,
            ];
        } catch (\Throwable $e) {
            $this->logger->warning("Failed to read module info from {$modulePath}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Clear cache khi cần force re-check
     */
    public static function clearCache(): void
    {
        try {
            cache()->forget(self::PENDING_CHECK_CACHE_KEY);
        } catch (\Throwable $e) {
            // Silently fail if cache is not available
        }
    }
}
