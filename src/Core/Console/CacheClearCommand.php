<?php

namespace Core\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

class CacheClearCommand extends BaseCommand
{
    public function __construct(private Application $app)
    {
        parent::__construct();
    }

    public function signature(): string
    {
        return 'cache:clear';
    }

    public function description(): string
    {
        return 'Flush the application cache (config, route, and module).';
    }

    public function fire(): void
    {
        $this->io->title('Clearing All Application Caches');

        $this->clearConfigCache();
        $this->clearRouteCache();
        $this->clearModuleCache();

        $this->io->success('All application caches have been cleared!');
    }

    private function clearConfigCache(): void
    {
        $cachePath = $this->app->getCachedProvidersPath();
        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->io->writeln('<info>✔ Config cache cleared.</info>');
        } else {
            $this->io->writeln('<comment>› Config cache not found.</comment>');
        }
    }

    private function clearRouteCache(): void
    {
        $cachePath = $this->app->getCachedRoutesPath();
        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->io->writeln('<info>✔ Route cache cleared.</info>');
        } else {
            $this->io->writeln('<comment>› Route cache not found.</comment>');
        }
    }

    private function clearModuleCache(): void
    {
        $cachePath = $this->app->basePath('bootstrap/cache/modules.php');
        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->io->writeln('<info>✔ Module cache cleared.</info>');
        } else {
            $this->io->writeln('<comment>› Module cache not found.</comment>');
        }
    }
}