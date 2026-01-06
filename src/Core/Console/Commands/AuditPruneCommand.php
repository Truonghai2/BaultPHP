<?php

namespace Core\Console\Commands;

use Carbon\Carbon;
use Core\Application;
use Core\Audit\Models\AuditLog;
use Core\Console\Contracts\BaseCommand;

class AuditPruneCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'audit:prune 
                {--days=90 : Keep logs from the last N days}
                {--keep-sensitive : Keep sensitive logs even if old}';
    }

    public function description(): string
    {
        return 'Prune old audit logs to keep database clean';
    }

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $keepSensitive = $this->option('keep-sensitive');

        $cutoffDate = Carbon::now()->subDays($days);

        $this->io->title('Pruning Audit Logs');
        $this->io->writeln("Deleting logs older than {$cutoffDate->format('Y-m-d')} ({$days} days ago)");

        $query = AuditLog::where('created_at', '<', $cutoffDate);

        if ($keepSensitive) {
            $query->where('is_sensitive', '=', false);
            $this->io->writeln('Keeping sensitive logs...');
        }

        $count = $query->count();

        if ($count === 0) {
            $this->io->success('No old logs to prune.');
            return self::SUCCESS;
        }

        $confirmed = $this->io->confirm(
            "This will delete {$count} audit log(s). Continue?",
            false,
        );

        if (!$confirmed) {
            $this->io->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->io->success("Successfully pruned {$deleted} audit log(s).");

        return self::SUCCESS;
    }
}
