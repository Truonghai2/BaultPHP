<?php

declare(strict_types=1);

namespace Core\EventSourcing;

/**
 * Module Config Loader
 *
 * Loads and merges event sourcing configurations from modules
 */
class ModuleConfigLoader
{
    private array $loadedConfigs = [];
    private array $globalConfig = [];

    public function __construct()
    {
        $this->globalConfig = config('event-sourcing', []);
    }

    /**
     * Load config for a specific module
     */
    public function loadModuleConfig(string $moduleName): array
    {
        if (isset($this->loadedConfigs[$moduleName])) {
            return $this->loadedConfigs[$moduleName];
        }

        $moduleConfig = $this->readModuleConfig($moduleName);

        // Merge with global defaults
        $merged = $this->mergeWithGlobal($moduleConfig);

        $this->loadedConfigs[$moduleName] = $merged;

        return $merged;
    }

    /**
     * Get config value for module
     */
    public function get(string $moduleName, string $key, $default = null)
    {
        $config = $this->loadModuleConfig($moduleName);

        return data_get($config, $key, $default);
    }

    /**
     * Check if event sourcing is enabled for module
     */
    public function isEnabled(string $moduleName): bool
    {
        // Global switch
        if (!$this->globalConfig['enabled'] ?? true) {
            return false;
        }

        // Module-specific switch
        return $this->get($moduleName, 'enabled', true);
    }

    /**
     * Check if auto-record is enabled for module
     */
    public function isAutoRecordEnabled(string $moduleName): bool
    {
        if (!$this->isEnabled($moduleName)) {
            return false;
        }

        return $this->get(
            $moduleName,
            'auto_record.enabled',
            $this->globalConfig['auto_record'] ?? true,
        );
    }

    /**
     * Get aggregate config
     */
    public function getAggregateConfig(string $moduleName, string $aggregateName): ?array
    {
        return $this->get($moduleName, "aggregates.{$aggregateName}");
    }

    /**
     * Get all enabled aggregates for module
     */
    public function getEnabledAggregates(string $moduleName): array
    {
        $aggregates = $this->get($moduleName, 'aggregates', []);

        return array_filter($aggregates, function ($config) {
            return $config['enabled'] ?? true;
        });
    }

    /**
     * Discover all modules with event sourcing config
     */
    public function discoverModules(): array
    {
        if (!($this->globalConfig['module_discovery']['auto_discover'] ?? true)) {
            return [];
        }

        $modules = [];
        $modulePaths = $this->globalConfig['module_discovery']['module_paths'] ?? [];
        $configFilename = $this->globalConfig['module_discovery']['config_filename'] ?? 'event-sourcing.php';

        foreach ($modulePaths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $directories = glob($path . '/*', GLOB_ONLYDIR);

            foreach ($directories as $dir) {
                $moduleName = basename($dir);
                $configPath = $dir . '/config/' . $configFilename;

                if (file_exists($configPath)) {
                    $modules[] = $moduleName;
                }
            }
        }

        return $modules;
    }

    /**
     * Read module config file
     */
    private function readModuleConfig(string $moduleName): array
    {
        $configFilename = $this->globalConfig['module_discovery']['config_filename'] ?? 'event-sourcing.php';

        // Try standard module path
        $configPath = base_path("Modules/{$moduleName}/config/{$configFilename}");

        if (!file_exists($configPath)) {
            return [];
        }

        $config = require $configPath;

        return is_array($config) ? $config : [];
    }

    /**
     * Merge module config with global defaults
     */
    private function mergeWithGlobal(array $moduleConfig): array
    {
        return array_replace_recursive(
            [
                'enabled' => $this->globalConfig['enabled'] ?? true,
                'dual_write' => $this->globalConfig['dual_write'] ?? true,
                'auto_record' => $this->globalConfig['auto_record'] ?? true,
                'snapshots' => $this->globalConfig['snapshots'] ?? [],
                'publish_events' => $this->globalConfig['publish_events'] ?? [],
                'projections' => $this->globalConfig['projections'] ?? [],
                'audit' => $this->globalConfig['audit'] ?? [],
            ],
            $moduleConfig,
        );
    }

    /**
     * Get all loaded module configs
     */
    public function getAllModuleConfigs(): array
    {
        return $this->loadedConfigs;
    }

    /**
     * Clear cached configs
     */
    public function clearCache(): void
    {
        $this->loadedConfigs = [];
    }
}
