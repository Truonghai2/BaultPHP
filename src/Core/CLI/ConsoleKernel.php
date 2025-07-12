<?php

namespace Core\CLI;

class ConsoleKernel
{
    protected array $commands = [];

    public function __construct()
    {
        $this->loadCommands();
    }

    protected function loadCommands(): void
    {
        $files = glob(__DIR__ . '/../Console/*Command.php');

        foreach ($files as $file) {
            require_once $file;
            $class = 'Core\\Console\\' . basename($file, '.php');

            if (class_exists($class)) {
                $instance = new $class;
                $this->commands[$instance->signature()] = $instance;
            }
        }
    }

    public function handle(array $argv): void
    {
        $commandInput = $argv[1] ?? null;

        if (!$commandInput) {
            $this->output("No command provided. Gõ `--help` để xem danh sách.");
            return;
        }

        if ($commandInput === '--help' || $commandInput === '-h') {
            $this->output("Danh sách command:");
            foreach ($this->commands as $sig => $cmd) {
                $this->output(" - {$sig}");
            }
            return;
        }

        foreach ($this->commands as $signature => $commandInstance) {
            $cmd = explode(' ', $signature)[0];

            if (strtolower($cmd) === strtolower($commandInput)) {
                $commandInstance->handle(array_slice($argv, 2));
                return;
            }
        }

        $this->output("Command '{$commandInput}' not found.");
    }

    protected function output(string $message): void
    {
        echo $message . PHP_EOL;
    }
}
