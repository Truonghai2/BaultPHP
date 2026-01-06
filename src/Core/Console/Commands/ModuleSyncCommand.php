<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Module\ModuleSyncService;

/**
 * Module Sync Command
 *
 * Command to synchronize all module data (modules, permissions, roles) from filesystem to database.
 */
class ModuleSyncCommand extends BaseCommand
{
    public function __construct(
        Application $app,
        private readonly ModuleSyncService $syncService,
    ) {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'module:sync {module? : Optional module name to sync only a specific module}';
    }

    public function description(): string
    {
        return 'Synchronize module data (modules, permissions, roles) from filesystem to database';
    }

    public function handle(): int
    {
        $moduleName = $this->argument('module');

        $this->io->title('Module Synchronization');
        $this->io->info($moduleName
            ? "Synchronizing module: {$moduleName}"
            : 'Synchronizing all modules'
        );

        try {
            $result = $this->syncService->syncAll($moduleName);

            $this->displayResults($result);

            $this->io->newLine();
            $this->io->success('Synchronization complete!');

            return 0;
        } catch (\Throwable $e) {
            $this->io->error("Synchronization failed: {$e->getMessage()}");
            $this->io->writeln($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Display synchronization results
     *
     * @param array $result
     */
    private function displayResults(array $result): void
    {
        $this->io->newLine();
        $this->io->section('Modules');
        $this->displaySectionResults($result['modules'] ?? []);

        $this->io->newLine();
        $this->io->section('Permissions');
        $this->displaySectionResults($result['permissions'] ?? []);

        $this->io->newLine();
        $this->io->section('Roles');
        $this->displaySectionResults($result['roles'] ?? []);
    }

    /**
     * Display results for a section
     *
     * @param array $section
     */
    private function displaySectionResults(array $section): void
    {
        $added = count($section['added'] ?? []);
        $updated = count($section['updated'] ?? []);
        $removed = count($section['removed'] ?? []);

        if ($added > 0) {
            $this->io->writeln("  <fg=green>Added:</> {$added}");
            foreach ($section['added'] as $item) {
                $this->io->writeln("    - {$item}");
            }
        }

        if ($updated > 0) {
            $this->io->writeln("  <fg=yellow>Updated:</> {$updated}");
            foreach ($section['updated'] as $item) {
                $this->io->writeln("    - {$item}");
            }
        }

        if ($removed > 0) {
            $this->io->writeln("  <fg=red>Removed:</> {$removed}");
            foreach ($section['removed'] as $item) {
                $this->io->writeln("    - {$item}");
            }
        }

        if ($added === 0 && $updated === 0 && $removed === 0) {
            $this->io->writeln('  <fg=cyan>No changes needed</>');
        }
    }
}

