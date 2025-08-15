<?php

namespace Core\Console\Commands\Cache;

use Core\Console\Contracts\BaseCommand;

class CacheClearCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'cache:clear';
    }

    public function description(): string
    {
        return 'Flush all application caches (config, route, module, and compiled container).';
    }

    public function handle(): int
    {
        $this->comment('Clearing all application caches...');

        $this->clearConfigCache();
        $this->clearRouteCache();
        $this->clearModuleCache();
        $this->clearCompiledContainerCache();

        $this->info('All application caches have been cleared!');
        return 0;
    }

    private function clearConfigCache(): void
    {
        $cachePath = $this->app->bootstrapPath('cache/config.php');
        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->line('<info>✔ Config cache cleared.</info>');
        } else {
            $this->line('<comment>› Config cache not found.</comment>');
        }
    }

    private function clearRouteCache(): void
    {
        $cachePath = $this->app->getCachedRoutesPath();
        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->line('<info>✔ Route cache cleared.</info>');
        } else {
            $this->line('<comment>› Route cache not found.</comment>');
        }
    }

    private function clearModuleCache(): void
    {
        $cachePath = $this->app->basePath('bootstrap/cache/modules.php');
        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->line('<info>✔ Module cache cleared.</info>');
        } else {
            $this->line('<comment>› Module cache not found.</comment>');
        }
    }

    private function clearCompiledContainerCache(): void
    {
        $cachePath = $this->app->bootstrapPath('cache/container.php');
        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->line('<info>✔ Compiled container cache cleared.</info>');
        } else {
            $this->line('<comment>› Compiled container cache not found.</comment>');
        }
    }
}
