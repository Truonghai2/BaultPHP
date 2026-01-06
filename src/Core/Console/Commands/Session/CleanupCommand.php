<?php

namespace Core\Console\Commands\Session;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

/**
 * Command để cleanup expired session files manually.
 *
 * Usage:
 *   php cli session:cleanup
 *   php cli session:cleanup --force
 *   php cli session:cleanup --dry-run
 */
class CleanupCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'session:cleanup {--force : Force cleanup without confirmation} {--dry-run : Show what would be deleted without actually deleting}';
    }

    public function description(): string
    {
        return 'Cleanup expired session files';
    }

    public function handle(): int
    {
        $config = config('session');

        $driver = $config['driver'] ?? 'file';

        if ($driver !== 'file') {
            $this->io->warning("Session driver is '{$driver}', not 'file'. This command only works with file driver.");
            return self::FAILURE;
        }

        $lifetime = $config['lifetime'] * 60; // minutes to seconds
        $path = $config['files'];

        if (!is_dir($path)) {
            $this->io->error("Session directory does not exist: {$path}");
            return self::FAILURE;
        }

        $this->io->title('Session Cleanup');
        $this->io->text([
            "Session directory: <info>{$path}</info>",
            'Lifetime: <info>' . ($lifetime / 60) . ' minutes</info>',
            'Current time: <info>' . date('Y-m-d H:i:s') . '</info>',
        ]);

        $files = glob($path . '/*');
        $totalFiles = count($files);
        $expiredFiles = [];
        $now = time();

        $this->io->section('Scanning files...');

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $fileTime = filemtime($file);
            $age = $now - $fileTime;

            if ($age > $lifetime) {
                $expiredFiles[] = [
                    'path' => $file,
                    'name' => basename($file),
                    'age' => $age,
                    'modified' => date('Y-m-d H:i:s', $fileTime),
                ];
            }
        }

        $expiredCount = count($expiredFiles);

        $this->io->text([
            "Total session files: <info>{$totalFiles}</info>",
            "Expired files: <comment>{$expiredCount}</comment>",
        ]);

        if ($expiredCount === 0) {
            $this->io->success('No expired sessions to cleanup!');
            return self::SUCCESS;
        }

        // Show sample of files to be deleted
        if ($expiredCount > 0 && $this->option('verbose')) {
            $this->io->section('Sample of expired files (first 5):');
            $sample = array_slice($expiredFiles, 0, 5);
            foreach ($sample as $file) {
                $this->io->text(sprintf(
                    '  - %s (age: %d min, modified: %s)',
                    $file['name'],
                    round($file['age'] / 60),
                    $file['modified'],
                ));
            }
        }

        if ($this->option('dry-run')) {
            $this->io->warning("DRY RUN: Would delete {$expiredCount} files");
            return self::SUCCESS;
        }

        // Confirm deletion
        if (!$this->option('force')) {
            if (!$this->io->confirm("Delete {$expiredCount} expired session files?", false)) {
                $this->io->info('Cleanup cancelled.');
                return self::SUCCESS;
            }
        }

        // Delete files
        $this->io->section('Deleting expired sessions...');
        $deleted = 0;
        $failed = 0;

        $progressBar = $this->io->createProgressBar($expiredCount);
        $progressBar->start();

        foreach ($expiredFiles as $file) {
            try {
                if (unlink($file['path'])) {
                    $deleted++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                if ($this->option('verbose')) {
                    $this->io->error("Failed to delete {$file['name']}: " . $e->getMessage());
                }
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->io->newLine(2);

        // Summary
        if ($failed === 0) {
            $this->io->success("Successfully deleted {$deleted} expired session files!");
        } else {
            $this->io->warning("Deleted {$deleted} files, but {$failed} failed.");
        }

        // Show disk space saved (estimate)
        $avgSize = 200; // bytes per session file (estimate)
        $spaceSaved = ($deleted * $avgSize) / 1024; // KB
        $this->io->info(sprintf('Estimated disk space freed: %.2f KB', $spaceSaved));

        return self::SUCCESS;
    }
}
