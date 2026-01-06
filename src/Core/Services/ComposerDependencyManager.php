<?php

namespace Core\Services;

use Core\Exceptions\Module\ModuleDependencyException;
use Core\FileSystem\Filesystem;
use Core\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Quản lý việc cài đặt, cập nhật và xóa Composer dependencies cho modules.
 *
 * Service này cung cấp các phương thức để:
 * - Cài đặt dependencies từ module.json
 * - Merge composer.json của module vào root composer.json
 * - Validate dependencies trước khi cài
 * - Rollback khi có lỗi
 * - Track progress của quá trình cài đặt
 */
class ComposerDependencyManager
{
    private const COMPOSER_TIMEOUT = 600; // 10 minutes
    private const BACKUP_SUFFIX = '.backup';

    public function __construct(
        private Filesystem $fs,
    ) {
    }

    /**
     * Cài đặt dependencies từ module.json hoặc composer.json của module.
     *
     * @param string $moduleName Tên module
     * @param array|null $dependencies Dependencies từ module.json (nếu không dùng composer.json)
     * @param bool $updateOnly Chỉ update dependencies, không require mới
     * @return array Kết quả cài đặt với thông tin chi tiết
     * @throws ModuleDependencyException
     */
    public function installDependencies(
        string $moduleName,
        ?array $dependencies = null,
        bool $updateOnly = false,
    ): array {
        $modulePath = base_path("Modules/{$moduleName}");

        // Kiểm tra module có composer.json riêng không
        $moduleComposerPath = "{$modulePath}/composer.json";
        $hasModuleComposer = $this->fs->exists($moduleComposerPath);

        if ($hasModuleComposer) {
            Log::info("Module '{$moduleName}' has its own composer.json, will merge with root");
            return $this->installFromModuleComposer($moduleName, $moduleComposerPath);
        }

        // Nếu không có composer.json riêng, dùng dependencies từ module.json
        if (empty($dependencies)) {
            Log::info("No dependencies specified for module '{$moduleName}'");
            return [
                'status' => 'success',
                'message' => 'No dependencies to install',
                'installed' => [],
            ];
        }

        return $this->installFromDependencyArray($moduleName, $dependencies, $updateOnly);
    }

    /**
     * Cài đặt dependencies từ composer.json của module.
     * Merge các dependencies vào root composer.json.
     */
    private function installFromModuleComposer(string $moduleName, string $moduleComposerPath): array
    {
        $rootComposerPath = base_path('composer.json');

        // Backup root composer.json
        $this->backupComposerJson($rootComposerPath);

        try {
            $moduleComposer = json_decode($this->fs->get($moduleComposerPath), true);
            if (!$moduleComposer) {
                throw new \Exception("Invalid composer.json in module '{$moduleName}'");
            }

            $rootComposer = json_decode($this->fs->get($rootComposerPath), true);

            // Merge dependencies
            $merged = $this->mergeComposerDependencies($rootComposer, $moduleComposer, $moduleName);

            // Save merged composer.json
            $this->fs->put(
                $rootComposerPath,
                json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );

            Log::info("Merged composer.json for module '{$moduleName}'", [
                'new_require' => $moduleComposer['require'] ?? [],
                'new_require_dev' => $moduleComposer['require-dev'] ?? [],
            ]);

            // Run composer update
            $result = $this->runComposerUpdate($moduleName);

            // Clean up backup if successful
            $this->removeBackup($rootComposerPath);

            return $result;
        } catch (\Throwable $e) {
            // Rollback on error
            $this->rollbackComposerJson($rootComposerPath);

            throw new ModuleDependencyException(
                "Failed to install dependencies from composer.json for module '{$moduleName}': " . $e->getMessage(),
                0,
                $e,
            );
        }
    }

    /**
     * Merge composer.json của module vào root composer.json.
     */
    private function mergeComposerDependencies(array $root, array $module, string $moduleName): array
    {
        // Merge require
        if (!empty($module['require'])) {
            $root['require'] = array_merge(
                $root['require'] ?? [],
                $module['require'],
            );
            ksort($root['require']);
        }

        // Merge require-dev
        if (!empty($module['require-dev'])) {
            $root['require-dev'] = array_merge(
                $root['require-dev'] ?? [],
                $module['require-dev'],
            );
            ksort($root['require-dev']);
        }

        // Merge autoload PSR-4
        if (!empty($module['autoload']['psr-4'])) {
            $root['autoload']['psr-4'] = array_merge(
                $root['autoload']['psr-4'] ?? [],
                $module['autoload']['psr-4'],
            );
        }

        // Merge autoload files
        if (!empty($module['autoload']['files'])) {
            $root['autoload']['files'] = array_merge(
                $root['autoload']['files'] ?? [],
                array_map(
                    fn ($file) => "Modules/{$moduleName}/{$file}",
                    $module['autoload']['files'],
                ),
            );
        }

        // Merge repositories (nếu module cần custom repos)
        if (!empty($module['repositories'])) {
            $root['repositories'] = array_merge(
                $root['repositories'] ?? [],
                $module['repositories'],
            );
        }

        return $root;
    }

    /**
     * Cài đặt dependencies từ array (module.json format).
     */
    private function installFromDependencyArray(
        string $moduleName,
        array $dependencies,
        bool $updateOnly = false,
    ): array {
        // Validate dependencies
        $validated = $this->validateDependencies($dependencies);

        if (empty($validated['packages'])) {
            return [
                'status' => 'success',
                'message' => 'No valid packages to install (only PHP/extensions)',
                'installed' => [],
                'skipped' => $validated['skipped'],
            ];
        }

        // Backup composer.json
        $composerPath = base_path('composer.json');
        $this->backupComposerJson($composerPath);

        try {
            if ($updateOnly) {
                $result = $this->runComposerUpdate($moduleName, array_keys($validated['packages']));
            } else {
                $result = $this->runComposerRequire($moduleName, $validated['packages']);
            }

            // Clean up backup if successful
            $this->removeBackup($composerPath);

            return $result;
        } catch (\Throwable $e) {
            // Rollback on error
            $this->rollbackComposerJson($composerPath);
            throw $e;
        }
    }

    /**
     * Validate dependencies và filter out PHP/extensions.
     */
    private function validateDependencies(array $dependencies): array
    {
        $packages = [];
        $skipped = [];

        foreach ($dependencies as $package => $version) {
            // Skip PHP platform requirements
            if ($package === 'php') {
                $skipped[] = "{$package}: {$version} (PHP version requirement)";
                continue;
            }

            // Skip PHP extensions
            if (str_starts_with($package, 'ext-')) {
                $skipped[] = "{$package}: {$version} (PHP extension)";
                continue;
            }

            // Validate version constraint
            if (!$this->isValidVersionConstraint($version)) {
                $skipped[] = "{$package}: {$version} (invalid version constraint)";
                Log::warning("Invalid version constraint for {$package}: {$version}");
                continue;
            }

            $packages[$package] = $version;
        }

        return [
            'packages' => $packages,
            'skipped' => $skipped,
        ];
    }

    /**
     * Kiểm tra version constraint có hợp lệ không.
     */
    private function isValidVersionConstraint(string $version): bool
    {
        // Basic validation - can be improved
        return !empty(trim($version)) && preg_match('/^[\d\.\^\~\*\|\-\@dev\,\s\<\>\=]+$/', $version);
    }

    /**
     * Chạy composer require để cài packages mới.
     */
    private function runComposerRequire(string $moduleName, array $packages): array
    {
        $command = [$this->findComposer(), 'require'];

        $installedPackages = [];
        foreach ($packages as $package => $version) {
            $packageSpec = "{$package}:{$version}";
            $command[] = $packageSpec;
            $installedPackages[] = $packageSpec;
        }

        // Composer options
        $command[] = '--no-interaction';
        $command[] = '--no-progress';
        $command[] = '--prefer-dist';
        $command[] = '--optimize-autoloader';
        $command[] = '--no-scripts'; // Skip scripts để tránh side effects
        $command[] = '--with-all-dependencies';

        Log::info("Running composer require for module '{$moduleName}'", [
            'packages' => $installedPackages,
            'command' => implode(' ', $command),
        ]);

        $process = new Process($command, base_path(), null, null, self::COMPOSER_TIMEOUT);

        // Capture output in real-time
        $output = '';
        $errorOutput = '';

        $process->run(function ($type, $buffer) use (&$output, &$errorOutput) {
            if (Process::ERR === $type) {
                $errorOutput .= $buffer;
                Log::debug('Composer STDERR: ' . $buffer);
            } else {
                $output .= $buffer;
                Log::debug('Composer STDOUT: ' . $buffer);
            }
        });

        if (!$process->isSuccessful()) {
            throw new ModuleDependencyException(
                "Failed to install dependencies for module '{$moduleName}'.\n\n" .
                'Command: ' . implode(' ', $command) . "\n\n" .
                "Error Output:\n" . $errorOutput . "\n\n" .
                "Standard Output:\n" . $output,
            );
        }

        Log::info("Successfully installed dependencies for module '{$moduleName}'");

        return [
            'status' => 'success',
            'message' => 'Successfully installed ' . count($packages) . ' package(s)',
            'installed' => $installedPackages,
            'output' => $output,
        ];
    }

    /**
     * Chạy composer update cho các packages cụ thể.
     */
    private function runComposerUpdate(string $moduleName, array $packages = []): array
    {
        $command = [$this->findComposer(), 'update'];

        if (!empty($packages)) {
            $command = array_merge($command, $packages);
        }

        // Composer options
        $command[] = '--no-interaction';
        $command[] = '--no-progress';
        $command[] = '--prefer-dist';
        $command[] = '--optimize-autoloader';
        $command[] = '--with-all-dependencies';

        Log::info("Running composer update for module '{$moduleName}'", [
            'packages' => $packages ?: ['all'],
            'command' => implode(' ', $command),
        ]);

        $process = new Process($command, base_path(), null, null, self::COMPOSER_TIMEOUT);

        $output = '';
        $errorOutput = '';

        $process->run(function ($type, $buffer) use (&$output, &$errorOutput) {
            if (Process::ERR === $type) {
                $errorOutput .= $buffer;
            } else {
                $output .= $buffer;
            }
        });

        if (!$process->isSuccessful()) {
            throw new ModuleDependencyException(
                "Failed to update dependencies for module '{$moduleName}': " . $errorOutput,
            );
        }

        return [
            'status' => 'success',
            'message' => 'Dependencies updated successfully',
            'updated' => $packages ?: ['all'],
            'output' => $output,
        ];
    }

    /**
     * Xóa dependencies của module khỏi composer.json.
     */
    public function removeDependencies(string $moduleName, array $packages): array
    {
        if (empty($packages)) {
            return [
                'status' => 'success',
                'message' => 'No packages to remove',
                'removed' => [],
            ];
        }

        $composerPath = base_path('composer.json');
        $this->backupComposerJson($composerPath);

        try {
            $command = [$this->findComposer(), 'remove'];
            $command = array_merge($command, $packages);
            $command[] = '--no-interaction';
            $command[] = '--no-progress';
            $command[] = '--update-with-all-dependencies';

            Log::info("Removing dependencies for module '{$moduleName}'", ['packages' => $packages]);

            $process = new Process($command, base_path(), null, null, self::COMPOSER_TIMEOUT);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ModuleDependencyException(
                    'Failed to remove dependencies: ' . $process->getErrorOutput(),
                );
            }

            $this->removeBackup($composerPath);

            return [
                'status' => 'success',
                'message' => 'Successfully removed ' . count($packages) . ' package(s)',
                'removed' => $packages,
            ];

        } catch (\Throwable $e) {
            $this->rollbackComposerJson($composerPath);
            throw $e;
        }
    }

    /**
     * Kiểm tra xem composer đã cài đặt chưa và version.
     */
    public function checkComposerInstallation(): array
    {
        try {
            $process = new Process([$this->findComposer(), '--version'], base_path(), null, null, 30);
            $process->run();

            if ($process->isSuccessful()) {
                $output = $process->getOutput();
                preg_match('/Composer version ([^\s]+)/', $output, $matches);

                return [
                    'installed' => true,
                    'version' => $matches[1] ?? 'unknown',
                    'output' => $output,
                ];
            }

            return [
                'installed' => false,
                'error' => $process->getErrorOutput(),
            ];

        } catch (\Throwable $e) {
            return [
                'installed' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate composer.json của module.
     */
    public function validateModuleComposer(string $modulePath): array
    {
        $composerPath = "{$modulePath}/composer.json";

        if (!$this->fs->exists($composerPath)) {
            return [
                'valid' => false,
                'error' => 'composer.json not found',
            ];
        }

        try {
            $json = json_decode($this->fs->get($composerPath), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'valid' => false,
                    'error' => 'Invalid JSON: ' . json_last_error_msg(),
                ];
            }

            // Validate required fields
            $errors = [];

            if (empty($json['name'])) {
                $errors[] = 'Missing required field: name';
            }

            if (!empty($json['require'])) {
                foreach ($json['require'] as $package => $version) {
                    if (!$this->isValidVersionConstraint($version)) {
                        $errors[] = "Invalid version constraint for {$package}: {$version}";
                    }
                }
            }

            if (!empty($errors)) {
                return [
                    'valid' => false,
                    'errors' => $errors,
                ];
            }

            return [
                'valid' => true,
                'data' => $json,
            ];

        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Backup composer.json trước khi modify.
     */
    private function backupComposerJson(string $composerPath): void
    {
        $backupPath = $composerPath . self::BACKUP_SUFFIX;

        if ($this->fs->exists($composerPath)) {
            $this->fs->copy($composerPath, $backupPath);
            Log::debug("Backed up composer.json to {$backupPath}");
        }
    }

    /**
     * Rollback composer.json từ backup.
     */
    private function rollbackComposerJson(string $composerPath): void
    {
        $backupPath = $composerPath . self::BACKUP_SUFFIX;

        if ($this->fs->exists($backupPath)) {
            $this->fs->copy($backupPath, $composerPath);
            $this->fs->delete($backupPath);
            Log::info('Rolled back composer.json from backup');
        }
    }

    /**
     * Xóa backup file.
     */
    private function removeBackup(string $composerPath): void
    {
        $backupPath = $composerPath . self::BACKUP_SUFFIX;

        if ($this->fs->exists($backupPath)) {
            $this->fs->delete($backupPath);
            Log::debug('Removed composer.json backup');
        }
    }

    /**
     * Tìm đường dẫn thực thi của Composer.
     */
    private function findComposer(): string
    {
        // Ưu tiên composer.phar trong thư mục gốc nếu có
        if ($this->fs->exists(base_path('composer.phar'))) {
            return PHP_BINARY . ' ' . base_path('composer.phar');
        }

        // Fallback to global composer
        return 'composer';
    }

    /**
     * Chạy composer dump-autoload sau khi cài dependencies.
     */
    public function dumpAutoload(bool $optimize = true): array
    {
        $command = [$this->findComposer(), 'dump-autoload'];

        if ($optimize) {
            $command[] = '--optimize';
        }

        $command[] = '--no-interaction';

        Log::info('Running composer dump-autoload', ['command' => implode(' ', $command)]);

        $process = new Process($command, base_path(), null, null, 60);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                'Failed to dump autoload: ' . $process->getErrorOutput(),
            );
        }

        return [
            'status' => 'success',
            'message' => 'Autoload files regenerated',
            'output' => $process->getOutput(),
        ];
    }
}
