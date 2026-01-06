<?php

namespace Core\Services;

use Core\Cache\CacheManager;
use Core\Exceptions\Module\ModuleDependencyException;
use Core\Exceptions\Module\ModuleNotFoundException;
use Core\FileSystem\Filesystem;
use Core\Services\ComposerDependencyManager;
use Core\Support\Facades\Log;
use Modules\Admin\Application\Jobs\InstallModuleDependenciesJob;
use Modules\Admin\Infrastructure\Models\Module;

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
        return $this->setModuleStatus($module, $newStatus);
    }

    /**
     * Enable a module.
     *
     * @param string $moduleName
     * @return bool Always returns true on success
     * @throws ModuleNotFoundException
     */
    public function enableModule(string $moduleName): bool
    {
        /** @var Module|null $module */
        $module = Module::where('name', $moduleName)->first();

        if (!$module) {
            throw new ModuleNotFoundException("Module '{$moduleName}' không được tìm thấy trong cơ sở dữ liệu.");
        }

        return $this->setModuleStatus($module, true);
    }

    /**
     * Disable a module.
     *
     * @param string $moduleName
     * @return bool Always returns false on success
     * @throws ModuleNotFoundException
     */
    public function disableModule(string $moduleName): bool
    {
        /** @var Module|null $module */
        $module = Module::where('name', $moduleName)->first();

        if (!$module) {
            throw new ModuleNotFoundException("Module '{$moduleName}' không được tìm thấy trong cơ sở dữ liệu.");
        }

        return $this->setModuleStatus($module, false);
    }

    /**
     * Set module status (enabled/disabled).
     *
     * @param Module $module
     * @param bool $status
     * @return bool The status that was set
     */
    private function setModuleStatus(Module $module, bool $status): bool
    {
        $module->enabled = $status;
        $module->save();

        $jsonPath = $this->modulesPath . '/' . $module->name . '/module.json';
        if ($this->fs->exists($jsonPath)) {
            $meta = json_decode($this->fs->get($jsonPath), true);
            if ($meta) {
                $meta['enabled'] = $status;
                $this->fs->put($jsonPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }

        $this->cache->forget(self::CACHE_KEY);

        return $status;
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

        Module::where('name', $moduleName)->delete();

        $this->fs->deleteDirectory($dir);

        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * Register a module that is already present on the filesystem into the database.
     * This is the final step to install a module that has been detected.
     *
     * @param string $moduleName The name of the module directory.
     * @throws \Exception If the module already exists in the database or the module.json file is missing/invalid.
     */
    public function registerModule(string $moduleName): void
    {
        $jsonPath = $this->modulesPath . '/' . $moduleName . '/module.json';

        if (!$this->fs->exists($jsonPath)) {
            throw new \Exception("The module.json file does not exist for module '{$moduleName}'.");
        }

        $meta = json_decode($this->fs->get($jsonPath), true);
        if (!$meta) {
            throw new \Exception("The module.json file for module '{$moduleName}' is invalid.");
        }

        $existingModule = Module::where('name', $moduleName)->first();
        
        if ($existingModule) {

            $filesystemVersion = $meta['version'] ?? '1.0.0';
            $databaseVersion = $existingModule->version ?? '1.0.0';

            if (version_compare($filesystemVersion, $databaseVersion, '>')) {
                Log::info("Updating module '{$moduleName}' from version {$databaseVersion} to {$filesystemVersion}");
                
                $existingModule->update([
                    'version' => $filesystemVersion,
                    'description' => $meta['description'] ?? $existingModule->description,
                ]);

                InstallModuleDependenciesJob::dispatch($moduleName);
            } else {
                InstallModuleDependenciesJob::dispatch($moduleName);
            }
            
            $this->cache->forget(self::CACHE_KEY);
            return;
        }

        Module::create([
            'name' => $moduleName,
            'version' => $meta['version'] ?? '1.0.0',
            'enabled' => $meta['enabled'] ?? false,
            'status' => 'installing',
            'description' => $meta['description'] ?? '',
        ]);

        InstallModuleDependenciesJob::dispatch($moduleName);

        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * @deprecated Use ComposerDependencyManager instead
     * @see ComposerDependencyManager::installDependencies()
     * 
     */
    public function handleDependencies(string $moduleName, array $dependencies): void
    {
        $composerManager = app(ComposerDependencyManager::class);
        $composerManager->installDependencies($moduleName, $dependencies);
    }
}
