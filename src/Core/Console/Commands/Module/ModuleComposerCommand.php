<?php

namespace Core\Console\Commands\Module;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Services\ComposerDependencyManager;
use Core\Support\Facades\Log;
use Modules\Admin\Infrastructure\Models\Module;

/**
 * Command để quản lý Composer dependencies cho modules.
 * 
 * Usage:
 *   php cli module:composer --install=Blog      # Cài dependencies cho module Blog
 *   php cli module:composer --update=Blog       # Update dependencies của module Blog
 *   php cli module:composer --remove=Blog       # Xóa dependencies của module Blog
 *   php cli module:composer --check=Blog        # Kiểm tra dependencies của module Blog
 *   php cli module:composer --check-composer    # Kiểm tra Composer installation
 */
class ModuleComposerCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'module:composer 
                {--install= : Install dependencies for a module}
                {--update= : Update dependencies for a module}
                {--remove= : Remove dependencies for a module}
                {--check= : Check dependencies status for a module}
                {--check-composer : Check Composer installation}
                {--dump-autoload : Regenerate composer autoload files}';
    }

    public function description(): string
    {
        return 'Manage Composer dependencies for modules';
    }

    public function handle(): int
    {
        /** @var ComposerDependencyManager $composerManager */
        $composerManager = $this->app->make(ComposerDependencyManager::class);

        // Check Composer installation
        if ($this->option('check-composer')) {
            return $this->checkComposer($composerManager);
        }

        // Dump autoload
        if ($this->option('dump-autoload')) {
            return $this->dumpAutoload($composerManager);
        }

        // Install dependencies
        if ($moduleName = $this->option('install')) {
            return $this->installDependencies($composerManager, $moduleName);
        }

        // Update dependencies
        if ($moduleName = $this->option('update')) {
            return $this->updateDependencies($composerManager, $moduleName);
        }

        // Remove dependencies
        if ($moduleName = $this->option('remove')) {
            return $this->removeDependencies($composerManager, $moduleName);
        }

        // Check dependencies
        if ($moduleName = $this->option('check')) {
            return $this->checkDependencies($composerManager, $moduleName);
        }

        $this->io->error('Please provide an option. Use --help for available options.');
        return self::FAILURE;
    }

    /**
     * Check Composer installation.
     */
    private function checkComposer(ComposerDependencyManager $composerManager): int
    {
        $this->io->title('Checking Composer Installation');

        $result = $composerManager->checkComposerInstallation();

        if ($result['installed']) {
            $this->io->success('Composer is installed');
            $this->io->writeln("Version: <info>{$result['version']}</info>");
            $this->io->writeln("\n" . trim($result['output']));
            return self::SUCCESS;
        }

        $this->io->error('Composer is not installed or not accessible');
        $this->io->writeln("Error: {$result['error']}");
        $this->io->writeln("\nPlease install Composer: https://getcomposer.org/download/");
        return self::FAILURE;
    }

    /**
     * Dump autoload files.
     */
    private function dumpAutoload(ComposerDependencyManager $composerManager): int
    {
        $this->io->title('Regenerating Composer Autoload Files');

        try {
            $result = $composerManager->dumpAutoload(true);

            $this->io->success($result['message']);
            $this->io->writeln("\n" . trim($result['output']));

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->io->error('Failed to dump autoload: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Install dependencies for a module.
     */
    private function installDependencies(ComposerDependencyManager $composerManager, string $moduleName): int
    {
        $this->io->title("Installing Dependencies for Module: {$moduleName}");

        try {
            // Check if module exists
            $module = Module::where('name', $moduleName)->first();
            if (!$module) {
                $this->io->error("Module '{$moduleName}' not found in database.");
                $this->io->writeln("Run 'php cli module:sync' first to register the module.");
                return self::FAILURE;
            }

            // Read dependencies from module.json
            $jsonPath = base_path("Modules/{$moduleName}/module.json");
            if (!file_exists($jsonPath)) {
                $this->io->error("module.json not found for '{$moduleName}'");
                return self::FAILURE;
            }

            $meta = json_decode(file_get_contents($jsonPath), true);
            if (!$meta) {
                $this->io->error("Invalid module.json for '{$moduleName}'");
                return self::FAILURE;
            }

            $dependencies = $meta['require'] ?? [];

            if (empty($dependencies)) {
                $this->io->info("No dependencies defined in module.json");
                return self::SUCCESS;
            }

            $this->io->writeln("\n<fg=yellow>Dependencies to install:</>");
            foreach ($dependencies as $package => $version) {
                $this->io->writeln("  • {$package}: {$version}");
            }

            if (!$this->io->confirm("\nProceed with installation?", true)) {
                $this->io->writeln('Installation cancelled.');
                return self::SUCCESS;
            }

            $this->io->writeln("\n<fg=yellow>Installing... (this may take several minutes)</>");

            $result = $composerManager->installDependencies($moduleName, $dependencies);

            if ($result['status'] === 'success') {
                $this->io->success($result['message']);

                if (!empty($result['installed'])) {
                    $this->io->writeln("\n<fg=green>Installed packages:</>");
                    foreach ($result['installed'] as $package) {
                        $this->io->writeln("  ✓ {$package}");
                    }
                }

                if (!empty($result['skipped'])) {
                    $this->io->writeln("\n<fg=yellow>Skipped:</>");
                    foreach ($result['skipped'] as $reason) {
                        $this->io->writeln("  ⊘ {$reason}");
                    }
                }

                // Update module status
                $module->status = 'installed';
                $module->save();

                return self::SUCCESS;
            }

            $this->io->error('Installation failed');
            return self::FAILURE;

        } catch (\Throwable $e) {
            $this->io->error('Error: ' . $e->getMessage());
            Log::error('Module composer install failed', [
                'module' => $moduleName,
                'exception' => $e,
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Update dependencies for a module.
     */
    private function updateDependencies(ComposerDependencyManager $composerManager, string $moduleName): int
    {
        $this->io->title("Updating Dependencies for Module: {$moduleName}");

        try {
            $module = Module::where('name', $moduleName)->first();
            if (!$module) {
                $this->io->error("Module '{$moduleName}' not found.");
                return self::FAILURE;
            }

            $jsonPath = base_path("Modules/{$moduleName}/module.json");
            $meta = json_decode(file_get_contents($jsonPath), true);
            $dependencies = $meta['require'] ?? [];

            if (empty($dependencies)) {
                $this->io->info("No dependencies to update");
                return self::SUCCESS;
            }

            $this->io->writeln("\n<fg=yellow>Updating dependencies...</>");

            $result = $composerManager->installDependencies($moduleName, $dependencies, true);

            if ($result['status'] === 'success') {
                $this->io->success('Dependencies updated successfully');
                return self::SUCCESS;
            }

            $this->io->error('Update failed');
            return self::FAILURE;

        } catch (\Throwable $e) {
            $this->io->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Remove dependencies for a module.
     */
    private function removeDependencies(ComposerDependencyManager $composerManager, string $moduleName): int
    {
        $this->io->title("Removing Dependencies for Module: {$moduleName}");

        try {
            $jsonPath = base_path("Modules/{$moduleName}/module.json");
            if (!file_exists($jsonPath)) {
                $this->io->error("Module '{$moduleName}' not found");
                return self::FAILURE;
            }

            $meta = json_decode(file_get_contents($jsonPath), true);
            $dependencies = $meta['require'] ?? [];

            if (empty($dependencies)) {
                $this->io->info("No dependencies to remove");
                return self::SUCCESS;
            }

            // Filter out PHP/extensions
            $packages = array_filter(array_keys($dependencies), function ($package) {
                return $package !== 'php' && !str_starts_with($package, 'ext-');
            });

            if (empty($packages)) {
                $this->io->info("No composer packages to remove (only PHP/extensions)");
                return self::SUCCESS;
            }

            $this->io->writeln("\n<fg=yellow>Packages to remove:</>");
            foreach ($packages as $package) {
                $this->io->writeln("  • {$package}");
            }

            if (!$this->io->confirm("\nProceed with removal?", false)) {
                $this->io->writeln('Removal cancelled.');
                return self::SUCCESS;
            }

            $result = $composerManager->removeDependencies($moduleName, $packages);

            if ($result['status'] === 'success') {
                $this->io->success($result['message']);
                return self::SUCCESS;
            }

            $this->io->error('Removal failed');
            return self::FAILURE;

        } catch (\Throwable $e) {
            $this->io->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Check dependencies status for a module.
     */
    private function checkDependencies(ComposerDependencyManager $composerManager, string $moduleName): int
    {
        $this->io->title("Checking Dependencies for Module: {$moduleName}");

        try {
            $modulePath = base_path("Modules/{$moduleName}");
            if (!is_dir($modulePath)) {
                $this->io->error("Module '{$moduleName}' not found");
                return self::FAILURE;
            }

            // Check module.json
            $jsonPath = "{$modulePath}/module.json";
            if (!file_exists($jsonPath)) {
                $this->io->error("module.json not found");
                return self::FAILURE;
            }

            $meta = json_decode(file_get_contents($jsonPath), true);
            $dependencies = $meta['require'] ?? [];

            $this->io->section('module.json Dependencies:');
            if (empty($dependencies)) {
                $this->io->writeln("  <fg=gray>No dependencies defined</>");
            } else {
                foreach ($dependencies as $package => $version) {
                    $this->io->writeln("  • {$package}: <fg=cyan>{$version}</>");
                }
            }

            // Check composer.json if exists
            $composerPath = "{$modulePath}/composer.json";
            if (file_exists($composerPath)) {
                $validation = $composerManager->validateModuleComposer($modulePath);

                $this->io->section('Module composer.json:');
                if ($validation['valid']) {
                    $this->io->success('composer.json is valid');
                    
                    $composerData = $validation['data'];
                    if (!empty($composerData['require'])) {
                        $this->io->writeln("\n<fg=yellow>Require:</>");
                        foreach ($composerData['require'] as $package => $version) {
                            $this->io->writeln("  • {$package}: <fg=cyan>{$version}</>");
                        }
                    }
                    
                    if (!empty($composerData['require-dev'])) {
                        $this->io->writeln("\n<fg=yellow>Require-dev:</>");
                        foreach ($composerData['require-dev'] as $package => $version) {
                            $this->io->writeln("  • {$package}: <fg=cyan>{$version}</>");
                        }
                    }
                } else {
                    $this->io->error('composer.json is invalid');
                    if (isset($validation['error'])) {
                        $this->io->writeln("Error: {$validation['error']}");
                    }
                    if (isset($validation['errors'])) {
                        foreach ($validation['errors'] as $error) {
                            $this->io->writeln("  • {$error}");
                        }
                    }
                }
            }

            // Check module status in database
            $module = Module::where('name', $moduleName)->first();
            if ($module) {
                $this->io->section('Module Status:');
                $statusColor = match($module->status) {
                    'installed' => 'green',
                    'installing', 'installing_dependencies' => 'yellow',
                    'installation_failed', 'installation_permanently_failed' => 'red',
                    default => 'gray',
                };
                $this->io->writeln("  Status: <fg={$statusColor}>{$module->status}</>");
                $this->io->writeln("  Enabled: " . ($module->enabled ? '<fg=green>Yes</>' : '<fg=red>No</>'));
                $this->io->writeln("  Version: <fg=cyan>{$module->version}</>");
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->io->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}

