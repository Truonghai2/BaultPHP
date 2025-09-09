<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;
use Core\Filesystem\Filesystem;

class StorageLinkCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'storage:link';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create the symbolic links configured for the application';

    /**
     * The filesystem instance.
     *
     * @var \Core\Filesystem\Filesystem
     */
    protected Filesystem $files;

    /**
     * Create a new command instance.
     *
     * @param  \Core\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function signature(): string
    {
        return $this->signature;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $links = config('filesystems.links', []);

        if (empty($links)) {
            $this->info('No symbolic links are configured.');
            return 0;
        }

        foreach ($links as $link => $target) {
            if (!$this->files->exists($target)) {
                $this->error("The target [$target] for the link does not exist.");
                continue;
            }

            if ($this->files->exists($link)) {
                if (!is_link($link)) {
                    $this->error("The [$link] already exists and is not a link.");
                    continue;
                }
                $this->comment("The [$link] link already exists, skipping.");
                continue;
            }

            try {
                $this->files->link($target, $link);
                $this->info("The [$link] link has been connected to [$target].");
            } catch (\Exception $e) {
                $this->error("Failed to create the symbolic link: {$e->getMessage()}");
                if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
                    $this->warn('On Windows, you may need to run your terminal as an administrator to create symbolic links.');
                }
                return 1;
            }
        }

        $this->info('The symbolic links have been created successfully.');

        return 0;
    }
}
