<?php

namespace Core\Console\Commands;

use Core\Application;
use Core\Audit\Models\AuditLog;
use Core\Console\Contracts\BaseCommand;

class AuditLogsCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'audit:logs 
                {--category= : Filter by category (oauth, auth, crud, security, system)}
                {--type= : Filter by event type}
                {--user= : Filter by user ID}
                {--severity= : Filter by severity (debug, info, warning, error, critical)}
                {--sensitive : Show only sensitive operations}
                {--limit=50 : Number of logs to show}
                {--from= : Start date (Y-m-d)}
                {--to= : End date (Y-m-d)}';
    }

    public function description(): string
    {
        return 'View audit logs with various filters';
    }

    public function handle(): int
    {
        $query = AuditLog::query();

        // Apply filters
        if ($category = $this->option('category')) {
            $query->category($category);
        }

        if ($type = $this->option('type')) {
            $query->eventType($type);
        }

        if ($userId = $this->option('user')) {
            $query->byUser($userId);
        }

        if ($severity = $this->option('severity')) {
            $query->severity($severity);
        }

        if ($this->option('sensitive')) {
            $query->sensitive();
        }

        if ($from = $this->option('from')) {
            $to = $this->option('to') ?: date('Y-m-d');
            $query->dateRange($from, $to);
        }

        $limit = (int) $this->option('limit');
        $logs = $query->recent($limit)->get();

        if ($logs->isEmpty()) {
            $this->io->warning('No audit logs found matching the criteria.');
            return self::SUCCESS;
        }

        $this->io->title('Audit Logs');

        $rows = [];
        foreach ($logs as $log) {
            $rows[] = [
                $log->id,
                $log->created_at->format('Y-m-d H:i:s'),
                $this->colorSeverity($log->severity),
                $log->event_category,
                $log->event_type,
                $log->user_id ?? 'N/A',
                $log->ip_address ?? 'N/A',
                $this->truncate($log->description ?? '-', 50),
            ];
        }

        $this->io->table(
            ['ID', 'Time', 'Severity', 'Category', 'Event', 'User', 'IP', 'Description'],
            $rows
        );

        $this->io->writeln("\nTotal logs: " . count($rows));

        if ($this->io->confirm('Show detailed view of a log?', false)) {
            $logId = $this->io->ask('Enter log ID');
            $this->showDetail($logId);
        }

        return self::SUCCESS;
    }

    protected function showDetail(int $logId): void
    {
        $log = AuditLog::find($logId);

        if (!$log) {
            $this->io->error("Log #{$logId} not found.");
            return;
        }

        $this->io->title("Audit Log Details - #{$log->id}");

        $details = [
            ['Field', 'Value'],
            ['ID', $log->id],
            ['Time', $log->created_at->format('Y-m-d H:i:s')],
            ['Event Type', $log->event_type],
            ['Category', $log->event_category],
            ['Severity', $this->colorSeverity($log->severity)],
            ['Sensitive', $log->is_sensitive ? 'Yes' : 'No'],
            ['User ID', $log->user_id ?? 'N/A'],
            ['User Type', $log->user_type ?? 'N/A'],
            ['IP Address', $log->ip_address ?? 'N/A'],
            ['User Agent', $this->truncate($log->user_agent ?? 'N/A', 80)],
            ['Auditable Type', $log->auditable_type ?? 'N/A'],
            ['Auditable ID', $log->auditable_id ?? 'N/A'],
            ['Description', $log->description ?? 'N/A'],
        ];

        $this->io->table(['Field', 'Value'], $details);

        if ($log->old_values) {
            $this->io->section('Old Values');
            $this->io->writeln(json_encode($log->old_values, JSON_PRETTY_PRINT));
        }

        if ($log->new_values) {
            $this->io->section('New Values');
            $this->io->writeln(json_encode($log->new_values, JSON_PRETTY_PRINT));
        }

        if ($log->metadata) {
            $this->io->section('Metadata');
            $this->io->writeln(json_encode($log->metadata, JSON_PRETTY_PRINT));
        }
    }

    protected function colorSeverity(string $severity): string
    {
        return match ($severity) {
            'debug' => "<fg=gray>{$severity}</>",
            'info' => "<fg=green>{$severity}</>",
            'warning' => "<fg=yellow>{$severity}</>",
            'error' => "<fg=red>{$severity}</>",
            'critical' => "<fg=white;bg=red>{$severity}</>",
            default => $severity,
        };
    }

    protected function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }
}

