<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;
use Symfony\Component\Process\Process;

class ServeCommand extends BaseCommand
{
    /**
     * The signature of the command.
     *
     * @return string
     */
    public function signature(): string
    {
        return 'serve';
    }

    /**
     * The description of the command.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Starts the RoadRunner application server for local development.';
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->io->title('Starting BaultPHP Development Server (via RoadRunner)');
 
        // Determine the binary name based on the OS (rr.exe on Windows)
        $binaryName = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'rr.exe' : 'rr';
        $rrBinary = $this->app->basePath($binaryName);

        if (!is_executable($rrBinary)) {
            $this->io->error("RoadRunner binary '{$binaryName}' not found or is not executable in the project root.");
            $this->io->writeln("Please ensure 'rr' binary is in the project root. You can download it or run 'composer require spiral/roadrunner-cli --dev'.");
            return self::FAILURE;
        }

        $this->io->info("Starting RoadRunner server... Press Ctrl+C to stop.");
        $this->io->writeln("HTTP server available at: <href=http://localhost:8080>http://localhost:8080</> (from .rr.yml)");
        $this->io->newLine();

        $process = new Process([$rrBinary, 'serve'], $this->app->basePath());
        $process->setTty(Process::isTtySupported());
        $process->setTimeout(null);

        return $process->run(fn ($type, $buffer) => $this->io->write($buffer));
    }
}