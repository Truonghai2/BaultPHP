<?php

namespace Core\Console\Commands\Security;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Security\TemplateSecurityAuditor;

/**
 * Audit Blade Templates Command.
 * 
 * Scans all Blade templates for security vulnerabilities.
 * 
 * Usage: php artisan security:audit-templates
 */
class AuditTemplatesCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'security:audit-templates 
                {--path= : Custom path to scan}
                {--fix : Automatically fix common issues}';
    }

    public function description(): string
    {
        return 'Audit Blade templates for security vulnerabilities';
    }

    public function handle(): int
    {
        $this->info('🔍 Auditing Blade templates for security issues...');
        $this->line('');

        $auditor = new TemplateSecurityAuditor();

        // Scan directories
        $directories = [
            base_path('resources/views'),
            base_path('Modules'),
        ];

        if ($customPath = $this->option('path')) {
            $directories = [$customPath];
        }

        $totalSafe = 0;
        $totalUnsafe = 0;
        $allIssues = [];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $this->comment("Scanning: {$dir}");

            $result = $auditor->auditDirectory($dir);

            $totalSafe += $result['safe'] ?? 0;
            $totalUnsafe += $result['unsafe'] ?? 0;

            if (!empty($result['issues'])) {
                $allIssues = array_merge($allIssues, $result['issues']);
            }
        }

        $this->line('');
        $this->info('📊 Audit Results:');
        $this->line("Total templates: " . ($totalSafe + $totalUnsafe));
        $this->line("Safe: {$totalSafe}");
        $this->line("Unsafe: {$totalUnsafe}");
        $this->line('');

        if (empty($allIssues)) {
            $this->info('✅ No security issues found!');
            return 0;
        }

        // Display issues
        $this->error('⚠️  Security Issues Found:');
        $this->line('');

        foreach ($allIssues as $file => $issues) {
            $this->comment("File: {$file}");
            
            foreach ($issues as $issue) {
                $severity = strtoupper($issue['severity']);
                $this->line("  [{$severity}] {$issue['message']}");
                
                if (!empty($issue['match'])) {
                    $this->line("    Match: " . substr($issue['match'], 0, 100));
                }
            }
            
            $this->line('');
        }

        $this->warn('Please review and fix the issues above.');

        return 1; // Return error code
    }
}
