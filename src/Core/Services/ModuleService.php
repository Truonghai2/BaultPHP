<?php

namespace Core\Services;

use Core\Cache\CacheManager;
use Core\Exceptions\Module\ModuleDependencyException;
use Core\Exceptions\Module\ModuleNotFoundException;
use Core\Filesystem\Filesystem;
use Modules\Admin\Application\Jobs\InstallModuleDependenciesJob;
use Modules\Admin\Infrastructure\Models\Module;
use Symfony\Component\Process\Process;

/**
 * Handles business logic for managing modules.
 */
class ModuleService
{
    protected string $modulesPath;
    private const CACHE_KEY = 'all_modules_list';
    private const CACHE_TTL = 300;

    public function __construct(
        protected Filesystem $fs,
        protected CacheManager $cache,
    ) {
        $this->modulesPath = base_path('Modules');
    }

    /**
     * Get a list of all modules from the filesystem and database.
     *
     * @return array
     */
    public function getModules(): array
    {
        if ($cachedModules = $this->cache->get(self::CACHE_KEY)) {
            return $cachedModules;
        }

        $directories = $this->fs->directories($this->modulesPath);
        $moduleNamesOnDisk = array_map('basename', $directories);

        try {
            $dbModules = Module::all()->keyBy('name');
        } catch (\Throwable $e) {
            $dbModules = collect();
        }

        $allModules = [];

        foreach ($moduleNamesOnDisk as $name) {
            $dbModule = $dbModules->get($name);

            if ($dbModule) {
                $allModules[] = [
                    'name' => $dbModule->name,
                    'version' => $dbModule->version,
                    'description' => $dbModule->description,
                    'enabled' => $dbModule->enabled,
                    'status' => $dbModule->status,
                ];
            } else {
                $jsonPath = $this->modulesPath . '/' . $name . '/module.json';
                if (!$this->fs->exists($jsonPath) || !($meta = json_decode($this->fs->get($jsonPath), true))) {
                    continue;
                }

                $allModules[] = [
                    'name' => $name,
                    'version' => $meta['version'] ?? '1.0.0',
                    'description' => $meta['description'] ?? 'No description provided.',
                    'enabled' => $meta['enabled'] ?? false,
                    'status' => 'new',
                ];
            }
        }

        $this->cache->set(self::CACHE_KEY, $allModules, self::CACHE_TTL);

        return $allModules;
    }

    /**
     * Toggles the enabled/disabled status of a module.
     *
     * @param string $moduleName
     * @return bool The new status of the module.
     * @throws ModuleNotFoundException
     */
    public function toggleStatus(string $moduleName): bool
    {
        /** @var Module|null $module */
        $module = Module::where('name', $moduleName)->first();

        if (!$module) {
            throw new ModuleNotFoundException("Module '{$moduleName}' không được tìm thấy trong cơ sở dữ liệu.");
        }

        $newStatus = !$module->enabled;
        $module->enabled = $newStatus;
        $module->save();

        $jsonPath = $this->modulesPath . '/' . $moduleName . '/module.json';
        if ($this->fs->exists($jsonPath)) {
            $meta = json_decode($this->fs->get($jsonPath), true);
            if (!$meta) {
                return $newStatus;
            }
            $meta['enabled'] = $newStatus;
            $this->fs->put($jsonPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $this->cache->forget(self::CACHE_KEY);

        return $newStatus;
    }

    /**
     * Deletes a module completely.
     *
     * @param string $moduleName
     * @throws ModuleNotFoundException
     * @return void
     */
    public function deleteModule(string $moduleName): void
    {
        $dir = $this->modulesPath . '/' . $moduleName;

        if (!$this->fs->isDirectory($dir)) {
            throw new ModuleNotFoundException("Module '{$moduleName}' không tồn tại trên hệ thống file.");
        }

        // Xóa khỏi DB bằng ORM
        Module::where('name', $moduleName)->delete();

        $this->fs->deleteDirectory($dir);

        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * Đăng ký một module đã có trên hệ thống file vào cơ sở dữ liệu.
     * Đây là bước cuối cùng để cài đặt một module được phát hiện.
     *
     * @param string $moduleName Tên thư mục của module.
     * @throws \Exception Nếu module đã có trong CSDL hoặc file module.json bị thiếu/lỗi.
     */
    public function registerModule(string $moduleName): void
    {
        $jsonPath = $this->modulesPath . '/' . $moduleName . '/module.json';

        if (!$this->fs->exists($jsonPath)) {
            throw new \Exception("Tệp module.json không tồn tại cho module '{$moduleName}'.");
        }

        $meta = json_decode($this->fs->get($jsonPath), true);
        if (!$meta) {
            throw new \Exception("Tệp module.json của module '{$moduleName}' không hợp lệ.");
        }

        if (Module::where('name', $moduleName)->exists()) {
            return;
        }

        Module::create([
            'name' => $moduleName,
            'version' => $meta['version'] ?? '1.0.0',
            'enabled' => $meta['enabled'] ?? false,
            'status' => 'installing_dependencies',
            'description' => $meta['description'] ?? '',
        ]);

        InstallModuleDependenciesJob::dispatch($moduleName);

        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * Cài đặt các thư viện mà module yêu cầu thông qua Composer.
     *
     * @param string $moduleName Tên module đang được cài đặt.
     * @param array $dependencies Danh sách các yêu cầu từ module.json.
     * @throws ModuleDependencyException Nếu quá trình cài đặt thất bại.
     */
    public function handleDependencies(string $moduleName, array $dependencies): void
    {
        if (empty($dependencies)) {
            return;
        }

        $packages = array_filter($dependencies, function ($key) {
            return !in_array($key, ['php'], true) && !str_starts_with($key, 'ext-');
        }, ARRAY_FILTER_USE_KEY);

        if (empty($packages)) {
            return;
        }

        $command = [$this->findComposer(), 'require'];
        foreach ($packages as $package => $version) {
            $command[] = "{$package}:{$version}";
        }

        $command[] = '--no-interaction';
        $command[] = '--no-progress';
        $command[] = '--no-plugins';
        $command[] = '--no-scripts';
        $command[] = '--with-all-dependencies';

        $process = new Process($command, base_path(), null, null, 300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ModuleDependencyException(
                "Không thể cài đặt các thư viện cho module '{$moduleName}'. Lỗi Composer: " . $process->getErrorOutput(),
            );
        }
    }

    /**
     * Tìm đường dẫn thực thi của Composer.
     */
    private function findComposer(): string
    {
        // Ưu tiên composer.phar trong thư mục gốc nếu có
        return $this->fs->exists(base_path('composer.phar')) ? PHP_BINARY . ' composer.phar' : 'composer';
    }
}
