<?php

namespace Core\Console\Commands\Cache;

use Core\Console\Contracts\BaseCommand;
use Core\Foundation\ProviderRepository;

class BootstrapCacheCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'bootstrap:cache {--modules-only : Only cache the enabled modules list}';
    }

    public function description(): string
    {
        return "Cache the framework's service provider and module list for faster loading.";
    }

    public function handle(): int
    {
        if ($this->option('modules-only')) {
            $this->cacheModuleList();
            return self::SUCCESS;
        }

        $this->comment('Caching framework bootstrap files...');

        $repository = new ProviderRepository($this->app);
        $allProviders = $repository->discover();

        $this->cacheServiceProviders($allProviders);
        $enabledModuleNames = $this->getEnabledModuleNames();
        $this->cacheModuleList($enabledModuleNames);

        $this->info('✔ Framework bootstrap file cached successfully!');

        return self::SUCCESS;
    }

    protected function cacheServiceProviders(array $providers): void
    {
        $cachePath = $this->app->bootstrapPath('cache/services.php');
        $content = '<?php return ' . var_export($providers, true) . ';';
        file_put_contents($cachePath, $content);
        $this->info('› Service providers cached.');
    }

    protected function cacheModuleList(?array $enabledModuleNames = null): void
    {
        $namesToCache = $enabledModuleNames ?? $this->getEnabledModuleNames();

        $cachePath = $this->app->bootstrapPath('cache/modules.php');
        $content = "<?php\n\nreturn " . var_export($namesToCache, true) . ";\n";
        file_put_contents($cachePath, $content);
        $this->info('› Enabled module list cached.');
    }

    protected function getEnabledModuleNames(): array
    {
        $enabledModuleNames = [];
        $moduleJsonPaths = glob($this->app->basePath('Modules/*/module.json'));

        foreach ($moduleJsonPaths as $path) {
            $data = json_decode(file_get_contents($path), true);
            if (!empty($data['name']) && !empty($data['enabled']) && $data['enabled'] === true) {
                $enabledModuleNames[] = $data['name'];
            }
        }
        return $enabledModuleNames;
    }
}
