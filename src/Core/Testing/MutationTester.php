<?php

declare(strict_types=1);

namespace Core\Testing;

use Core\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Mutation Testing
 *
 * Wrapper for Infection PHP mutation testing framework.
 * Auto-generate mutants và verify test quality.
 *
 * Features:
 * - Auto-generate mutants
 * - Verify test quality
 * - Improve test coverage
 * - Mutation score reporting
 */
class MutationTester
{
    protected ?string $infectionBinary = null;
    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->findInfectionBinary();
    }

    /**
     * Find Infection PHP binary
     */
    protected function findInfectionBinary(): void
    {
        $paths = [
            base_path('vendor/bin/infection'),
            base_path('vendor/bin/infection.bat'),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $this->infectionBinary = $path;
                return;
            }
        }

        // Try to find in PATH
        $which = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where' : 'which';
        $process = new Process([$which, 'infection']);
        $process->run();
        
        if ($process->isSuccessful()) {
            $this->infectionBinary = trim($process->getOutput());
        }
    }

    /**
     * Run mutation testing
     *
     * @param array $options Mutation testing options
     * @return array Mutation testing results
     */
    public function run(array $options = []): array
    {
        if (!$this->infectionBinary) {
            return [
                'success' => false,
                'error' => 'Infection PHP not found. Install with: composer require --dev infection/infection',
            ];
        }

        $command = $this->buildCommand($options);
        
        $process = new Process($command, base_path());
        $process->setTimeout($options['timeout'] ?? 600); // 10 minutes default
        
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        $output = $process->getOutput();
        $exitCode = $process->getExitCode();

        return $this->parseResults($output, $exitCode);
    }

    /**
     * Build mutation testing command
     */
    protected function buildCommand(array $options): array
    {
        $command = [$this->infectionBinary];

        // Threads
        if (isset($options['threads'])) {
            $command[] = '--threads';
            $command[] = (string) $options['threads'];
        }

        // Configuration file
        if (isset($options['config'])) {
            $command[] = '--configuration';
            $command[] = $options['config'];
        }

        // Only covered
        if ($options['only_covered'] ?? false) {
            $command[] = '--only-covered';
        }

        // Show mutations
        if ($options['show_mutations'] ?? false) {
            $command[] = '--show-mutations';
        }

        // Filter
        if (isset($options['filter'])) {
            $command[] = '--filter';
            $command[] = $options['filter'];
        }

        // Test framework options
        if (isset($options['test_framework'])) {
            $command[] = '--test-framework';
            $command[] = $options['test_framework'];
        }

        // Initial tests only
        if ($options['initial_tests_only'] ?? false) {
            $command[] = '--initial-tests-only';
        }

        // Log verbosity
        if (isset($options['verbose'])) {
            $verbosity = match ($options['verbose']) {
                0 => 'none',
                1 => 'normal',
                2 => 'verbose',
                3 => 'very-verbose',
                default => 'normal',
            };
            $command[] = '--log-verbosity';
            $command[] = $verbosity;
        }

        return $command;
    }

    /**
     * Parse mutation testing results
     */
    protected function parseResults(string $output, int $exitCode): array
    {
        $results = [
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => $output,
        ];

        // Parse mutation score
        if (preg_match('/Mutation Score Indicator \(MSI\):\s*([\d.]+)%/', $output, $matches)) {
            $results['msi'] = (float) $matches[1];
        }

        // Parse covered MSI
        if (preg_match('/Covered Code MSI:\s*([\d.]+)%/', $output, $matches)) {
            $results['covered_msi'] = (float) $matches[1];
        }

        // Parse mutants
        if (preg_match('/(\d+)\s+mutants were generated/', $output, $matches)) {
            $results['mutants_generated'] = (int) $matches[1];
        }

        // Parse killed mutants
        if (preg_match('/(\d+)\s+mutants were killed/', $output, $matches)) {
            $results['mutants_killed'] = (int) $matches[1];
        }

        // Parse escaped mutants
        if (preg_match('/(\d+)\s+mutants escaped/', $output, $matches)) {
            $results['mutants_escaped'] = (int) $matches[1];
        }

        // Parse errors
        if (preg_match('/(\d+)\s+mutants had errors/', $output, $matches)) {
            $results['mutants_errors'] = (int) $matches[1];
        }

        // Parse timeouts
        if (preg_match('/(\d+)\s+mutants timed out/', $output, $matches)) {
            $results['mutants_timeouts'] = (int) $matches[1];
        }

        // Parse skipped
        if (preg_match('/(\d+)\s+mutants were skipped/', $output, $matches)) {
            $results['mutants_skipped'] = (int) $matches[1];
        }

        return $results;
    }

    /**
     * Run mutation testing with default options
     */
    public function runDefault(): array
    {
        return $this->run([
            'threads' => $this->config['threads'] ?? 4,
            'only_covered' => $this->config['only_covered'] ?? true,
            'test_framework' => $this->config['test_framework'] ?? 'phpunit',
        ]);
    }

    /**
     * Check if Infection is available
     */
    public function isAvailable(): bool
    {
        return $this->infectionBinary !== null && file_exists($this->infectionBinary);
    }

    /**
     * Get Infection binary path
     */
    public function getBinaryPath(): ?string
    {
        return $this->infectionBinary;
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return [
            'available' => $this->isAvailable(),
            'binary_path' => $this->infectionBinary,
            'config' => $this->config,
        ];
    }
}
