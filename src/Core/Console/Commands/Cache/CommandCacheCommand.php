<?php

namespace Core\Console\Commands\Cache;

use App\Providers\ConsoleServiceProvider;
use Core\Application;
use Core\Console\Contracts\BaseCommand;

class CommandCacheCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'command:cache';
    }

    public function description(): string
    {
        return 'Discover and cache all console commands for faster startup.';
    }

    public function handle(): int
    {
        $this->comment('Caching application commands...');

        $cachePath = $this->app->getCachedCommandsPath();

        if (file_exists($cachePath)) {
            unlink($cachePath);
        }

        $cacheDir = dirname($cachePath);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        // Use the ConsoleServiceProvider's logic to discover commands
        /** @var ConsoleServiceProvider $provider */
        $provider = $this->app->make(ConsoleServiceProvider::class);
        $commands = $provider->discoverCommands();

        $content = '<?php' . PHP_EOL . PHP_EOL . 'return ' . var_export($commands, true) . ';' . PHP_EOL;
        file_put_contents($cachePath, $content);

        $this->info('✔ Commands cached successfully!');
        return self::SUCCESS;
    }
}
