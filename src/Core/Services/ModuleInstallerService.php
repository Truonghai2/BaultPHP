<?php

namespace Core\Services;

use Core\Exceptions\Module\DangerousCodeDetectedException;
use Core\Exceptions\Module\InvalidModuleFileException;
use Core\Exceptions\Module\InvalidModuleSignatureException;
use Core\Exceptions\Module\InvalidModuleStructureException;
use Core\Exceptions\Module\ModuleAlreadyExistsException;
use Core\Exceptions\Module\ModuleInstallationException;
use Core\FileSystem\Filesystem;
use Core\ORM\MigrationManager;
use Core\Services\ModuleService;
use Core\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Admin\Application\Jobs\InstallModuleDependenciesJob;
use Modules\Admin\Infrastructure\Models\Module;
use ZipArchive;

/**
 * ModuleInstallerService handles the installation of modules from ZIP files.
 * It validates, extracts, and verifies module integrity before installation.
 */
class ModuleInstallerService
{
    /**
     * @param Filesystem $fs The filesystem utility.
     * @param ModuleService $moduleService Service for module management.
     * @param MigrationManager|null $migrationManager Optional migration manager for running migrations.
     * @param int $maxZipSize Maximum allowed size for the ZIP file in bytes.
     * @param int $maxUncompressedSize Maximum allowed total size of files after extraction.
     */
    public function __construct(
        protected Filesystem $fs,
        protected ModuleService $moduleService,
        protected ?MigrationManager $migrationManager = null,
        protected int $maxZipSize = 50 * 1024 * 1024, // 50MB
        protected int $maxUncompressedSize = 250 * 1024 * 1024, // 250MB
    ) {
    }

    /**
     * Install a module from a ZIP file.
     * 
     * This method performs the following steps:
     * 1. Validate ZIP file
     * 2. Extract ZIP to temporary directory
     * 3. Verify module.json structure
     * 4. Verify signature (if present)
     * 5. Scan for dangerous code
     * 6. Validate module structure
     * 7. Move to Modules directory
     * 8. Register module in database
     * 9. Run migrations (if any)
     * 10. Dispatch dependency installation job (if needed)
     * 11. Fire installation event
     * 
     * @param string $zipPath Path to the ZIP file
     * @param bool $runMigrations Whether to run migrations immediately (default: true)
     * @param bool $installDependencies Whether to install dependencies via job (default: true)
     * @return array Installation result with module info
     * @throws ModuleInstallationException
     */
    public function install(string $zipPath, bool $runMigrations = true, bool $installDependencies = true): array
    {
        $tmpDir = storage_path('app/tmp_module_' . md5($zipPath . time()));
        $moduleMeta = null;
        $targetDir = null;

        try {
            Log::info("Starting module installation from ZIP", ['zip_path' => $zipPath]);
            
            // Step 1: Validate ZIP file
            $this->ensureZipIsValid($zipPath);
            
            // Step 2: Extract ZIP
            $this->extractZip($zipPath, $tmpDir);

            // Step 3: Verify module.json
            $jsonPath = $tmpDir . '/module.json';
            $moduleMeta = $this->verifyModuleJson($jsonPath);
            $moduleName = $moduleMeta['name'];
            
            Log::info("Module metadata verified", ['module' => $moduleName, 'version' => $moduleMeta['version'] ?? 'unknown']);

            // Step 4: Verify signature (if present)
            $this->verifySignature($moduleMeta, $tmpDir);
            
            // Step 5: Scan for dangerous code
            $this->scanForDangerousCode($tmpDir);
            
            // Step 6: Validate structure
            $this->validateStructure($tmpDir, $moduleMeta);

            // Step 7: Check if module already exists
            $targetDir = base_path('Modules/' . $moduleName);
            if ($this->fs->exists($targetDir)) {
                throw new ModuleAlreadyExistsException("Module '{$moduleName}' already exists.");
            }

            // Step 8: Move to Modules directory
            $this->moveDirectory($tmpDir, $targetDir);
            Log::info("Module files moved to target directory", ['module' => $moduleName, 'target' => $targetDir]);

            try {
                // Step 9: Register module in database
                $module = $this->registerModule($moduleMeta, $targetDir);
                
                // Step 10: Run migrations if requested
                if ($runMigrations) {
                    $this->runModuleMigrations($moduleName);
                }
                
                // Step 11: Dispatch dependency installation job if needed
                if ($installDependencies && !empty($moduleMeta['require'])) {
                    $this->dispatchDependencyInstallation($moduleName);
                }
                
                // Step 12: Fire installation event
                event(new \Core\Events\ModuleInstalled($moduleName, auth()->id() ?? null));
                
                Log::info("Module '{$moduleName}' has been installed successfully.", [
                    'installer' => 'zip',
                    'version' => $moduleMeta['version'] ?? 'unknown',
                    'migrations_run' => $runMigrations,
                    'dependencies_queued' => $installDependencies && !empty($moduleMeta['require']),
                ]);
                
                return [
                    'success' => true,
                    'module' => $moduleName,
                    'version' => $moduleMeta['version'] ?? 'unknown',
                    'module_id' => $module->id ?? null,
                    'status' => $module->status ?? 'installed',
                ];
                
            } catch (\Throwable $e) {
                // Rollback: Delete module directory if registration failed
                if ($targetDir && $this->fs->exists($targetDir)) {
                    $this->fs->deleteDirectory($targetDir);
                    Log::warning("Rolled back installation of module '{$moduleName}' due to an error after moving files.", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
                throw new ModuleInstallationException("Failed to complete module installation: " . $e->getMessage(), 0, $e);
            }
        } catch (ModuleAlreadyExistsException | InvalidModuleFileException | InvalidModuleStructureException | InvalidModuleSignatureException | DangerousCodeDetectedException $e) {
            // Re-throw known exceptions as-is
            throw $e;
        } catch (\Throwable $e) {
            throw new ModuleInstallationException("Module installation failed: " . $e->getMessage(), 0, $e);
        } finally {
            // Cleanup temporary directory
            if ($this->fs->exists($tmpDir)) {
                $this->fs->deleteDirectory($tmpDir);
            }
        }
    }
    
    /**
     * Register module in database.
     */
    protected function registerModule(array $moduleMeta, string $modulePath): Module
    {
        $moduleName = $moduleMeta['name'];
        
        // Check if module already exists in database
        $existing = Module::where('name', $moduleName)->first();
        if ($existing) {
            Log::warning("Module '{$moduleName}' already exists in database, updating record", ['module_id' => $existing->id]);
            $module = $existing;
        } else {
            $module = new Module();
            $module->name = $moduleName;
        }
        
        // Update module properties
        $module->version = $moduleMeta['version'] ?? '1.0.0';
        $module->description = $moduleMeta['description'] ?? '';
        $module->enabled = $moduleMeta['enabled'] ?? false;
        $module->status = 'installed';
        
        // Check for composer.json
        $composerPath = $modulePath . '/composer.json';
        if ($this->fs->exists($composerPath)) {
            $module->status = 'installing_dependencies';
        }
        
        $module->save();
        
        Log::info("Module registered in database", [
            'module' => $moduleName,
            'module_id' => $module->id,
            'status' => $module->status,
        ]);
        
        return $module;
    }
    
    /**
     * Run migrations for the installed module.
     */
    protected function runModuleMigrations(string $moduleName): void
    {
        if (!$this->migrationManager) {
            Log::debug("MigrationManager not available, skipping migrations for module '{$moduleName}'");
            return;
        }
        
        $migrationsPath = base_path("Modules/{$moduleName}/Infrastructure/Migrations");
        
        // Also check for legacy migrations path
        if (!is_dir($migrationsPath)) {
            $migrationsPath = base_path("Modules/{$moduleName}/migrations");
        }
        
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
            'path' => $migrationsPath,
        ]);
        
        try {
            // Get migration paths from config
            $config = app('config');
            $paths = $config->get('database.migrations.paths', []);
            
            // Add module migration path
            if (!in_array($migrationsPath, $paths)) {
                $paths[] = $migrationsPath;
                $config->set('database.migrations.paths', $paths);
            }
            
            // Run migrations
            $this->migrationManager->run($paths);
            
            Log::info("Migrations completed for module '{$moduleName}'");
        } catch (\Throwable $e) {
            Log::warning("Failed to run migrations for module '{$moduleName}': " . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw - migrations can be run manually later
        }
    }
    
    /**
     * Dispatch dependency installation job.
     */
    protected function dispatchDependencyInstallation(string $moduleName): void
    {
        try {
            InstallModuleDependenciesJob::dispatch($moduleName);
            Log::info("Dependency installation job dispatched for module '{$moduleName}'");
        } catch (\Throwable $e) {
            Log::warning("Failed to dispatch dependency installation job for module '{$moduleName}': " . $e->getMessage());
            // Don't throw - dependencies can be installed manually
        }
    }

    protected function ensureZipIsValid(string $zipPath): void
    {
        if (!file_exists($zipPath) || mime_content_type($zipPath) !== 'application/zip') {
            throw new InvalidModuleFileException('Invalid file or not a ZIP file.');
        }

        if (filesize($zipPath) > $this->maxZipSize) {
            throw new InvalidModuleFileException('Module file exceeds the limit of ' . ($this->maxZipSize / 1024 / 1024) . 'MB.');
        }
    }

    protected function extractZip(string $zipPath, string $extractTo): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new InvalidModuleFileException('Cannot open module ZIP file.');
        }

        $totalUncompressedSize = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (substr($stat['name'], -1) !== '/') {
                $totalUncompressedSize += $stat['size'];
            }
        }
        if ($totalUncompressedSize > $this->maxUncompressedSize) {
            $zip->close();
            throw new InvalidModuleFileException('Module size after extraction exceeds the limit of ' . ($this->maxUncompressedSize / 1024 / 1024) . 'MB.');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (str_contains($filename, '..')) {
                $zip->close();
                throw new InvalidModuleStructureException("Unsafe path detected in ZIP file: {$filename}");
            }
        }

        $this->fs->ensureDirectoryExists($extractTo);
        $zip->extractTo($extractTo);
        $zip->close();
    }

    protected function verifyModuleJson(string $jsonPath): array
    {
        if (!$this->fs->exists($jsonPath)) {
            throw new InvalidModuleStructureException('module.json does not exist.');
        }

        $jsonContent = file_get_contents($jsonPath);
        $json = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidModuleStructureException('JSON parsing error in module.json: ' . json_last_error_msg());
        }

        // Required fields
        $required = ['name', 'enabled', 'providers'];
        foreach ($required as $field) {
            if (!isset($json[$field])) {
                throw new InvalidModuleStructureException("Missing required field '$field' in module.json.");
            }
        }

        // Validate module name
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]+$/', $json['name'])) {
            throw new InvalidModuleStructureException("Invalid module name '{$json['name']}'. Must start with a letter and contain only letters, numbers, and underscores.");
        }

        // Validate providers (must be array)
        if (!is_array($json['providers'])) {
            throw new InvalidModuleStructureException("Field 'providers' must be an array in module.json.");
        }

        // Validate enabled (must be boolean)
        if (!is_bool($json['enabled'])) {
            throw new InvalidModuleStructureException("Field 'enabled' must be a boolean in module.json.");
        }

        // Validate version if present
        if (isset($json['version']) && !preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9]+)?$/', $json['version'])) {
            throw new InvalidModuleStructureException("Invalid version format '{$json['version']}'. Expected format: x.y.z or x.y.z-suffix");
        }

        // Validate require if present (must be array)
        if (isset($json['require']) && !is_array($json['require'])) {
            throw new InvalidModuleStructureException("Field 'require' must be an array in module.json.");
        }

        // Signature is optional but recommended
        if (!isset($json['signature'])) {
            Log::warning("Module '{$json['name']}' does not have a signature. This is not recommended for production use.");
        }

        return $json;
    }

    protected function verifySignature(array $meta, string $dir): void
    {
        // Signature verification is optional if signature field is not present
        if (!isset($meta['signature']) || empty($meta['signature'])) {
            Log::debug("Module '{$meta['name']}' does not have signature, skipping verification");
            return;
        }

        $hashFile = $dir . '/HASH';
        if (!$this->fs->exists($hashFile)) {
            // If signature is provided in module.json but HASH file is missing, it's an error
            if (!empty($meta['signature'])) {
                throw new InvalidModuleSignatureException('HASH file not found but signature is specified in module.json.');
            }
            return;
        }

        $expected = trim(file_get_contents($hashFile));
        if (empty($expected)) {
            throw new InvalidModuleSignatureException('HASH file is empty.');
        }

        // Collect all files except HASH file
        $allFiles = collect($this->fs->allFiles($dir))
            ->filter(function ($file) use ($dir) {
                $filename = $file->getFilename();
                $relativePath = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                return !in_array($filename, ['HASH', '.gitignore', '.DS_Store']) 
                    && !str_contains($relativePath, '.git' . DIRECTORY_SEPARATOR);
            })
            ->sortBy(fn ($f) => str_replace($dir . DIRECTORY_SEPARATOR, '', $f->getPathname()))
            ->values();

        if ($allFiles->isEmpty()) {
            throw new InvalidModuleSignatureException('No files found to verify signature.');
        }

        // Calculate hash of all files
        $hashContext = hash_init('sha256');
        foreach ($allFiles as $file) {
            $stream = @fopen($file->getRealPath(), 'r');
            if ($stream === false) {
                $relativePath = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                throw new InvalidModuleSignatureException("Cannot read file to verify: {$relativePath}");
            }
            hash_update_stream($hashContext, $stream);
            fclose($stream);
        }
        $actual = hash_final($hashContext);

        if (!hash_equals($expected, $actual)) {
            throw new InvalidModuleSignatureException('HASH signature does not match, module may have been modified or corrupted.');
        }

        Log::debug("Signature verified successfully for module '{$meta['name']}'");
    }

    protected function scanForDangerousCode(string $dir): void
    {
        $dangerous = ['eval', 'exec', 'shell_exec', 'passthru', 'system', 'proc_open', 'popen'];
        $phpFiles = collect($this->fs->allFiles($dir))
            ->filter(fn ($f) => $f->getExtension() === 'php');

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file->getRealPath());
            $relativePath = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            foreach ($dangerous as $func) {
                if (preg_match("/\\b{$func}\\s*\\(/i", $content)) {
                    throw new DangerousCodeDetectedException("Dangerous function '{$func}' detected in file: {$relativePath}");
                }
            }
        }
    }

    protected function validateStructure(string $dir, array $meta): void
    {
        // Check for forbidden paths that could overwrite core system files
        $forbiddenPaths = [
            'Core/',
            'src/Core',
            'bootstrap/',
            'config/app.php',
            'routes/web.php',
            'vendor/',
            'composer.json',
            'composer.lock',
            '.env',
        ];

        $invalid = collect($this->fs->allFiles($dir))->filter(function ($file) use ($dir, $forbiddenPaths) {
            $relativePath = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            foreach ($forbiddenPaths as $forbidden) {
                if (str_starts_with($relativePath, $forbidden) || str_contains($relativePath, $forbidden)) {
                    return true;
                }
            }
            return false;
        });

        if ($invalid->count() > 0) {
            $invalidFiles = $invalid->map(fn($f) => str_replace($dir . '/', '', $f->getPathname()))->take(5)->implode(', ');
            throw new InvalidModuleStructureException(
                "Module contains forbidden paths that could overwrite system files: {$invalidFiles}" . 
                ($invalid->count() > 5 ? " (and " . ($invalid->count() - 5) . " more)" : "")
            );
        }

        // Check for required ModuleServiceProvider
        $providerPaths = [
            $dir . '/Providers/ModuleServiceProvider.php',
            $dir . '/ModuleServiceProvider.php', // Legacy path
        ];

        $providerFound = false;
        foreach ($providerPaths as $providerPath) {
            if ($this->fs->exists($providerPath)) {
                $providerFound = true;
                break;
            }
        }

        if (!$providerFound) {
            throw new InvalidModuleStructureException(
                'Module must have Providers/ModuleServiceProvider.php. ' .
                'This file is required for module registration and service provider loading.'
            );
        }

        // Validate module.json name matches directory structure expectations
        $moduleName = $meta['name'];
        $expectedProviderNamespace = "Modules\\{$moduleName}\\Providers\\ModuleServiceProvider";
        
        // Try to read provider file to validate namespace (optional check)
        foreach ($providerPaths as $providerPath) {
            if ($this->fs->exists($providerPath)) {
                $providerContent = file_get_contents($providerPath);
                if (!str_contains($providerContent, $expectedProviderNamespace) && 
                    !str_contains($providerContent, "namespace Modules\\{$moduleName}")) {
                    Log::warning("ModuleServiceProvider namespace may not match module name", [
                        'module' => $moduleName,
                        'provider_path' => $providerPath,
                    ]);
                }
                break;
            }
        }

        Log::debug("Module structure validated successfully", ['module' => $moduleName]);
    }
    
    /**
     * Move a directory from source to destination.
     * 
     * @param string $source Source directory path
     * @param string $destination Destination directory path
     * @return void
     * @throws ModuleInstallationException
     */
    protected function moveDirectory(string $source, string $destination): void
    {
        // Ensure destination parent directory exists
        $destinationParent = dirname($destination);
        $this->fs->ensureDirectoryExists($destinationParent);
        
        // Use rename for atomic move (faster and safer)
        if (!@rename($source, $destination)) {
            // Fallback: copy and delete if rename fails (e.g., cross-filesystem)
            $this->copyDirectory($source, $destination);
            $this->fs->deleteDirectory($source);
        }
    }
    
    /**
     * Copy a directory recursively.
     * 
     * @param string $source Source directory path
     * @param string $destination Destination directory path
     * @return void
     * @throws ModuleInstallationException
     */
    protected function copyDirectory(string $source, string $destination): void
    {
        $this->fs->ensureDirectoryExists($destination);
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        $sourceLength = strlen($source) + 1; // +1 for directory separator
        
        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), $sourceLength);
            $destPath = $destination . DIRECTORY_SEPARATOR . $relativePath;
            
            if ($item->isDir()) {
                $this->fs->ensureDirectoryExists($destPath);
            } else {
                $this->fs->ensureDirectoryExists(dirname($destPath));
                copy($item->getPathname(), $destPath);
            }
        }
    }
}
