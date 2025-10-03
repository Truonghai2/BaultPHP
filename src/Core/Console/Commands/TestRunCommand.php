<?php

namespace Core\Console\Commands;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Symfony\Component\Process\Process;

class TestRunCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'test
                {--filter= : Filter which tests to run.}
                {--coverage : Generate a code coverage report.}
                {--watch : Watch files for changes and re-run tests automatically.}';
    }

    public function description(): string
    {
        return 'Run the application tests using Pest.';
    }

    public function handle(): int
    {
        $pestBinary = base_path('vendor/bin/pest');

        if (!file_exists($pestBinary)) {
            $this->error('Pest test runner not found. Please install it with: composer require pestphp/pest --dev --with-all-dependencies');
            return 1;
        }

        $command = [$pestBinary];

        if ($filter = $this->option('filter')) {
            $command[] = '--filter';
            $command[] = $filter;
        }

        if ($this->option('coverage')) {
            $command[] = '--coverage';
        }

        if ($this->option('watch')) {
            $command[] = '--watch';
        }

        $process = new Process($command, base_path(), null, null, null);
        $process->setTty(Process::isTtySupported());
        $process->run(fn ($type, $buffer) => $this->getOutput()->write($buffer));

        return $process->getExitCode();
    }
}
