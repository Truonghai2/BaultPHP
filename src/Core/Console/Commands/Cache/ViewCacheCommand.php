<?php

namespace Core\Console\Commands\Cache;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\View\Compiler;
use Throwable;

class ViewCacheCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    /**
     * The signature of the command.
     */
    public function signature(): string
    {
        return 'view:cache';
    }

    /**
     * The description of the command.
     */
    public function description(): string
    {
        return 'Compile all Blade templates for faster rendering.';
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->callCommand(ViewClearCommand::class);

        $this->info('Caching Blade templates...');

        /** @var Compiler $compiler */
        $compiler = $this->app->make(Compiler::class);

        $paths = config('view.paths', []);
        $viewCount = 0;

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($iterator as $file) {
                // Giả định các file view có đuôi .blade.php
                if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                try {
                    $compiler->compile($file->getRealPath());
                    $viewCount++;
                } catch (Throwable $e) {
                    $this->error("Failed to compile view [{$file->getRealPath()}]: {$e->getMessage()}");
                }
            }
        }

        if ($viewCount > 0) {
            $this->info("✔ {$viewCount} Blade templates cached successfully!");
        } else {
            $this->comment('No Blade templates found to cache.');
        }

        return self::SUCCESS;
    }
}
