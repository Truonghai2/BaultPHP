<?php

namespace Core\Console\Commands\Cache;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\FileSystem\Filesystem;

class BootstrapClearCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'bootstrap:clear';
    }

    public function description(): string
    {
        return 'Remove the cached framework bootstrap files (services and modules).';
    }

    public function handle(): int
    {
        /** @var Filesystem $files */
        $files = $this->app->make(Filesystem::class);

        $servicesCache = $this->app->bootstrapPath('cache/services.php');
        if ($files->exists($servicesCache)) {
            $files->delete($servicesCache);
            $this->info('✔ Framework service provider cache cleared!');
        } else {
            $this->comment('› Framework service provider cache not found. Nothing to clear.');
        }

        $modulesCache = $this->app->bootstrapPath('cache/modules.php');
        if ($files->exists($modulesCache)) {
            $files->delete($modulesCache);
            $this->info('✔ Enabled module list cache cleared!');
        } else {
            $this->comment('› Enabled module list cache not found. Nothing to clear.');
        }

        return self::SUCCESS;
    }
}
