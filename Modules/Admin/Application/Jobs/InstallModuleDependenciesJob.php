<?php

namespace Modules\Admin\Application\Jobs;

use Core\Queue\Dispatchable;
use Core\Queue\Job;
use Core\Services\ModuleService;
use Core\Support\Facades\Log;
use Modules\Admin\Infrastructure\Models\Module;

class InstallModuleDependenciesJob extends Job
{
    use Dispatchable;

    public function __construct(public string $moduleName)
    {
    }

    public function handle(
        ModuleService $moduleService,
        Log $logger,
    ): void {
        $module = Module::where('name', $this->moduleName)->first();
        if (!$module) {
            Log::error("Module '{$this->moduleName}' not found in database for dependency installation job.");
            return;
        }

        try {
            $logger->info("Starting dependency installation for module '{$this->moduleName}'.");

            $jsonPath = base_path('Modules/' . $this->moduleName . '/module.json');
            $meta = json_decode(file_get_contents($jsonPath), true);
            $dependencies = $meta['require'] ?? [];

            $moduleService->handleDependencies($this->moduleName, $dependencies);

            $module->status = 'installed';
            $module->description = $module->description ?: ($meta['description'] ?? '');
            $module->save();

            $logger->info("Successfully installed dependencies for module '{$this->moduleName}'.");
        } catch (\Throwable $e) {
            $logger->error("Failed to install dependencies for module '{$this->moduleName}': " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $module->status = 'installation_failed';
            $module->description = 'Lỗi cài đặt thư viện: ' . substr($e->getMessage(), 0, 250) . '...';
            $module->save();
        }
    }
}
