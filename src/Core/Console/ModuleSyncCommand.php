<?php

namespace Core\Console;

use Core\Application;
use Core\Module\ModuleSynchronizer;
use Core\Console\Contracts\BaseCommand;
use Throwable;

class ModuleSyncCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected string $signature = 'module:sync';

    /**
     * The console command description.
     */
    protected string $description = 'Synchronize filesystem modules with the database.';

    /**
     * Create a new command instance.
     */
    public function __construct(private Application $app)
    {
        parent::__construct();
    }

    public function signature(): string
    {
        return $this->signature;
    }

    public function handle(array $args = []): void
    {
        $this->io->title('Synchronizing Modules');

        try {
            /** @var ModuleSynchronizer $synchronizer */
            $synchronizer = $this->app->make(ModuleSynchronizer::class);

            // Call the synchronizer service
            $result = $synchronizer->sync();

            // Display results to the console
            if (!empty($result['added'])) {
                $this->io->section('Registering new modules...');
                foreach ($result['added'] as $moduleName) {
                    $this->io->writeln("  <fg=green>+ Registered:</> {$moduleName}");
                }
                $this->io->success('New modules have been registered as disabled. Please enable them via the admin panel.');
            } else {
                $this->io->writeln('No new modules found to register.');
            }

            if (!empty($result['removed'])) {
                $this->io->section('Cleaning up stale module records...');
                foreach ($result['removed'] as $moduleName) {
                    $this->io->writeln("  <fg=red>- Removed stale record:</> {$moduleName}");
                }
                $this->io->success('Stale module records have been removed.');
            } else {
                $this->io->writeln('No stale module records to remove.');
            }

            $this->io->newLine();
            $this->io->success('Module synchronization complete.');
        } catch (Throwable $e) {
            $this->io->error('An error occurred during module synchronization: ' . $e->getMessage());
            // Optionally log to file if a logger service is available and configured
            // For example: $this->app->make(\Psr\Log\LoggerInterface::class)->error($e->getMessage(), ['exception' => $e]);
        }
    }
}