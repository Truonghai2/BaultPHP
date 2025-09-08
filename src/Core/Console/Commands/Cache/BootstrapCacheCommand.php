<?php

namespace Core\Console\Commands\Cache;

use Core\Console\Contracts\BaseCommand;

class BootstrapCacheCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'bootstrap:cache';
    }

    public function description(): string
    {
        return "Cache the framework's service provider bootstrap file for faster loading.";
    }

    public function handle(): int
    {
        $this->comment('Caching framework bootstrap file...');

        $coreProviders = $this->getCoreProviders();
        $moduleProviders = $this->getModuleProviders();

        $allProviders = array_values(array_unique(array_merge($coreProviders, $moduleProviders)));
        sort($allProviders);

        $cachePath = $this->app->bootstrapPath('cache/services.php');
        $content = '<?php return ' . var_export($allProviders, true) . ';';
        file_put_contents($cachePath, $content);

        $this->info('✔ Framework bootstrap file cached successfully!');

        return self::SUCCESS;
    }

    protected function getCoreProviders(): array
    {
        $appConfigPath = $this->app->basePath('config/app.php');
        $appConfig = file_exists($appConfigPath) ? require $appConfigPath : [];
        return $appConfig['providers'] ?? [];
    }

    protected function getModuleProviders(): array
    {
        $providers = [];
        $moduleJsonPaths = glob($this->app->basePath('Modules/*/module.json'));

        if ($moduleJsonPaths === false) {
            return [];
        }

        foreach ($moduleJsonPaths as $path) {
            $data = json_decode(file_get_contents($path), true);
            if (!empty($data['name']) && !empty($data['enabled']) && $data['enabled'] === true) {
                foreach ($data['providers'] ?? [] as $providerClass) {
                    if (class_exists($providerClass)) {
                        $providers[] = $providerClass;
                    }
                }
            }
        }

        return $providers;
    }
}
