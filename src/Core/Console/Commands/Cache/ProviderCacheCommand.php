<?php

namespace Core\Console\Commands\Cache;

use Core\Console\Contracts\BaseCommand;

class ProviderCacheCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'provider:cache';
    }

    public function description(): string
    {
        return 'Cache the service providers for discovered modules.';
    }

    public function handle(): int
    {
        $this->comment('Caching module service providers...');

        $cachePath = $this->app->getCachedProvidersPath();

        $providers = [];
        $moduleJsonPaths = glob($this->app->basePath('Modules/*/module.json'));

        if ($moduleJsonPaths === false) {
            $moduleJsonPaths = [];
        }

        foreach ($moduleJsonPaths as $path) {
            $data = json_decode(file_get_contents($path), true);
            if (!empty($data['name']) && !empty($data['enabled']) && $data['enabled'] === true) {
                $moduleName = $data['name'];
                $providerClass = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
                if (class_exists($providerClass)) {
                    $providers[] = $providerClass;
                }
            }
        }

        sort($providers);

        $content = '<?php return ' . var_export($providers, true) . ';';

        file_put_contents($cachePath, $content);

        $this->info('✔ Module service providers cached successfully!');

        return self::SUCCESS;
    }
}
