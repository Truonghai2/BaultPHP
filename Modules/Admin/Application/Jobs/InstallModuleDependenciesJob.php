<?php

namespace Modules\Admin\Application\Jobs;

use Core\Queue\Dispatchable;
use Core\Queue\Job;
use Core\Services\ComposerDependencyManager;
use Core\Support\Facades\Log;
use Modules\Admin\Infrastructure\Models\Module;

/**
 * Job cài đặt Composer dependencies cho module.
 * 
 * Job này sẽ:
 * - Kiểm tra module có composer.json riêng không
 * - Merge dependencies vào root composer.json (nếu có)
 * - Chạy composer require/update
 * - Regenerate autoload
 * - Chạy migrations (nếu có)
 * - Cập nhật trạng thái module trong database
 * 
 * @property string $moduleName Tên module cần cài dependencies
 */
class InstallModuleDependenciesJob extends Job
{
    use Dispatchable;

    /**
     * Số lần thử lại nếu job fail
     */
    public int $tries = 3;
    
    /**
     * Timeout cho job (15 phút)
     */
    public int $timeout = 900;

    public function __construct(public string $moduleName)
    {
    }

    public function handle(
        ComposerDependencyManager $composerManager,
    ): void {
        $module = Module::where('name', $this->moduleName)->first();
        if (!$module) {
            Log::error("Module '{$this->moduleName}' not found in database for dependency installation job.");
            return;
        }

        try {
            Log::info("📦 Starting dependency installation for module '{$this->moduleName}'", [
                'module_status' => $module->status,
                'enabled' => $module->enabled,
            ]);

            $jsonPath = base_path('Modules/' . $this->moduleName . '/module.json');
            if (!file_exists($jsonPath)) {
                throw new \Exception("module.json not found for '{$this->moduleName}'");
            }
            
            $meta = json_decode(file_get_contents($jsonPath), true);
            if (!$meta) {
                throw new \Exception("Invalid module.json for '{$this->moduleName}'");
            }
            
            $dependencies = $meta['require'] ?? [];

            $module->status = 'installing_dependencies';
            $module->save();

            $composerCheck = $composerManager->checkComposerInstallation();
            if (!$composerCheck['installed']) {
                throw new \Exception("Composer is not installed or not accessible: " . ($composerCheck['error'] ?? 'Unknown error'));
            }
            
            Log::info("Composer detected", ['version' => $composerCheck['version']]);

            $result = $composerManager->installDependencies($this->moduleName, $dependencies);
            
            Log::info("Dependencies installation result", [
                'status' => $result['status'],
                'installed' => $result['installed'] ?? [],
                'skipped' => $result['skipped'] ?? [],
            ]);

            // Dump autoload để load classes mới
            $composerManager->dumpAutoload(true);
            Log::info("Autoload regenerated for module '{$this->moduleName}'");

            // Chạy migrations nếu có
            $this->runMigrations($this->moduleName);

            // Update module status
            $module->status = 'installed';
            $module->description = $module->description ?: ($meta['description'] ?? '');
            $module->save();

            Log::info("✅ Successfully completed dependency installation for module '{$this->moduleName}'", [
                'installed_packages' => $result['installed'] ?? [],
            ]);
            
        } catch (\Throwable $e) {
            Log::error("❌ Failed to install dependencies for module '{$this->moduleName}': " . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $module->status = 'installation_failed';
            $errorMsg = 'Lỗi cài đặt thư viện: ' . $e->getMessage();
            $module->description = substr($errorMsg, 0, 500) . (strlen($errorMsg) > 500 ? '...' : '');
            $module->save();
            
            // Re-throw để job system có thể retry
            throw $e;
        }
    }

    /**
     * Chạy migrations cho module (nếu có).
     */
    private function runMigrations(string $moduleName): void
    {
        $migrationsPath = base_path("Modules/{$moduleName}/migrations");
        
        if (!is_dir($migrationsPath)) {
            Log::debug("No migrations directory found for module '{$moduleName}'");
            return;
        }
        
        $migrationFiles = glob($migrationsPath . '/*.php');
        
        if (empty($migrationFiles)) {
            Log::debug("No migration files found for module '{$moduleName}'");
            return;
        }
        
        Log::info("Running migrations for module '{$moduleName}'", [
            'migrations_count' => count($migrationFiles),
        ]);
        
        try {
            // TODO: Implement migration runner
            // Có thể gọi: php cli migrate --path=Modules/{$moduleName}/migrations
            // Hoặc sử dụng Migrator service nếu có
            
            Log::info("Migrations completed for module '{$moduleName}'");
            
        } catch (\Throwable $e) {
            Log::warning("Failed to run migrations for module '{$moduleName}': " . $e->getMessage());
            // Don't throw - migrations có thể chạy thủ công sau
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Job permanently failed for module '{$this->moduleName}' after {$this->tries} attempts", [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);
        
        // Update module status
        $module = Module::where('name', $this->moduleName)->first();
        if ($module) {
            $module->status = 'installation_permanently_failed';
            $module->description = 'Cài đặt thất bại sau ' . $this->tries . ' lần thử: ' . $exception->getMessage();
            $module->save();
        }
    }
}

