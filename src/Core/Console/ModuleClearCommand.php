<?php

namespace Core\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

class ModuleClearCommand extends BaseCommand
{
    public function __construct(private Application $app)
    {
        parent::__construct();
    }

    public function signature(): string
    {
        return 'module:clear';
    }

    public function description(): string
    {
        return 'Remove the module cache file.';
    }

    public function fire(): void
    {
        $this->io->title('Clearing Module Cache');

        $cachePath = $this->app->basePath('bootstrap/cache/modules.php');

        if (file_exists($cachePath)) {
            unlink($cachePath);
            $this->io->success('Module cache cleared!');
        } else {
            $this->io->info('Module cache was not found. Nothing to clear.');
        }
    }
}