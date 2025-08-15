<?php

namespace Core\Console\Commands\Server;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\FileSystem\FileSystemWatcher;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ServeWatchCommand extends BaseCommand
{
    protected Application $app;

    public function __construct(Application $app)
    {
        parent::__construct();
        $this->app = $app;
    }

    public function signature(): string
    {
        return 'serve:watch {--host=127.0.0.1 : The host to bind the server to}';
    }

    public function description(): string
    {
        return 'Start the Swoole server and watch for file changes to automatically reload.';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = $input->getOption('host');

        $output->writeln('<comment>Starting server in watch mode... (Press Ctrl+C to stop)</comment>');

        $serverProcess = null;

        $restartServer = function () use (&$serverProcess, $host, $output) {
            if ($serverProcess && $serverProcess->isRunning()) {
                $output->writeln('<warning>Change detected. Reloading server...</warning>');
                $serverProcess->stop(10, SIGTERM);
                $output->writeln('<comment>Server process stopped.</comment>');
            }

            // Start the serve:start command as a background process
            $serverProcess = new Process(['php', 'cli', 'serve:start', '--host', $host]);
            $serverProcess->setTimeout(null);
            $serverProcess->setIdleTimeout(null);
            $serverProcess->start();

            $output->writeln("<info>Swoole server process started with PID: {$serverProcess->getPid()}</info>");
        };

        // Initial start
        $restartServer();

        // Configure and start the watcher
        $watchedDirs = config('server.swoole.watch.directories', []);
        if (empty($watchedDirs)) {
            $output->writeln('<error>No directories to watch. Please configure `server.swoole.watch.directories` in your config.</error>');
            if (isset($serverProcess) && $serverProcess->isRunning()) {
                $serverProcess->stop();
            }
            return self::FAILURE;
        }

        FileSystemWatcher::create()
            ->paths($watchedDirs)
            ->extensions(['php', 'env', 'yaml', 'json', 'blade.php', 'css', 'js'])
            ->onStateChange(function (string $type, string $path) use ($restartServer, $output) {
                $relativePath = str_replace($this->app->basePath() . DIRECTORY_SEPARATOR, '', $path);
                $output->writeln("File {$type}: <comment>{$relativePath}</comment>.");
                $restartServer();
            })
            ->start();

        return self::SUCCESS;
    }

    public function handle(): int
    {
        // This method is now unused but kept for compatibility with the abstract parent.
        return 0;
    }
}
