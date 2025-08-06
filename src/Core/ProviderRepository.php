<?php

namespace Core;

/**
 * Class ProviderRepository
 *
 * This class is responsible for discovering and collecting all service providers
 * for the application, both from the core configuration and from enabled modules.
 * It decouples the AppKernel from the logic of how providers are found.
 */
class ProviderRepository
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get a flattened list of all service providers to be registered.
     *
     * @return string[]
     */
    public function getAllProviders(): array
    {
        $coreProviders = config('app.providers', []);
        $moduleProviders = $this->discoverModuleProviders();

        return array_unique(array_merge($coreProviders, $moduleProviders));
    }

    /**
     * Discovers service providers from all enabled modules.
     *
     * This method scans the filesystem for module.json files and loads providers
     * only from modules that are marked as "enabled".
     *
     * @return string[]
     */
    protected function discoverModuleProviders(): array
    {
        $discoveredProviders = [];
        $moduleJsonPaths = glob($this->app->basePath('Modules/*/module.json'));

        foreach ($moduleJsonPaths as $path) {
            $meta = json_decode(file_get_contents($path), true);

            // Only load providers if the module is explicitly enabled in its manifest.
            if (!empty($meta['enabled']) && $meta['enabled'] === true) {
                foreach ($meta['providers'] ?? [] as $provider) {
                    if (class_exists($provider)) {
                        $discoveredProviders[] = $provider;
                    }
                }
            }
        }

        return $discoveredProviders;
    }
}

