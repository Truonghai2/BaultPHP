<?php

namespace Core\Module;

use Core\Application;

// Sử dụng Model mới

class ModuleManager
{
    public function __construct(private Application $app)
    {
    }

    /**
     * Lấy tất cả module từ cơ sở dữ liệu.
     * Giao diện admin sẽ hiển thị danh sách này.
     */
    public function getAllModules(): array
    {
        return Module::all()->toArray();
    }

    /**
     * Kích hoạt một module.
     */
    public function enable(string $moduleName): void
    {
        $module = Module::where('name', '=', $moduleName)->first();
        if (!$module) {
            throw new \Exception("Module '{$moduleName}' not found in the database. Run 'php cli module:sync'.");
        }
        $module->enabled = true;
        $module->save();
        $this->clearCache();
    }

    /**
     * Vô hiệu hóa một module.
     */
    public function disable(string $moduleName): void
    {
        $module = Module::where('name', '=', $moduleName)->first();
        if (!$module) {
            throw new \Exception("Module '{$moduleName}' not found in the database.");
        }
        $module->enabled = false;
        $module->save();
        $this->clearCache();
    }

    /**
     * Lấy danh sách tên các module đang được kích hoạt.
     * Phương thức này sẽ được AppKernel sử dụng.
     */
    public function getEnabledModuleNames(): array
    {
        // Có thể cache kết quả này để tăng hiệu năng
        return Module::where('enabled', '=', true)->pluck('name')->all();
    }

    /**
     * Clears the module cache file.
     * This is called automatically when a module is enabled or disabled.
     */
    private function clearCache(): void
    {
        $cachePath = $this->app->basePath('bootstrap/cache/modules.php');
        if (file_exists($cachePath)) {
            unlink($cachePath);
        }
    }
}
