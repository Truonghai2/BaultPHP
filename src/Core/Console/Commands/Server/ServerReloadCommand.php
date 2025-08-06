<?php

namespace Core\Console\Commands\Server;

use Core\Console\Contracts\BaseCommand;

class ServerReloadCommand extends BaseCommand
{
    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * The console command signature.
     *
     * @return string
     */
    public function signature(): string
    {
        return 'server:reload';
    }

    /**
     * The console command description.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Reloads the Swoole server gracefully (zero-downtime)';
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $pidFile = config('server.swoole.pid_file');

        if (!file_exists($pidFile)) {
            $this->error('Swoole server is not running or PID file not found.');
            $this->line(" > PID file expected at: <comment>{$pidFile}</comment>");
            $this->line(' > This command requires the server to be running in daemon mode.');
            $this->line(' > To start in daemon mode, set <comment>SWOOLE_DAEMONIZE=true</comment> in your .env file and restart the server.');
            return self::FAILURE;
        }

        $pid = (int) file_get_contents($pidFile);

        if (!$pid) {
            $this->error('PID file is empty or invalid.');
            return self::FAILURE;
        }

        // Check if the process is actually running.
        // posix_kill with signal 0 doesn't send a signal but checks for process existence.
        if (!function_exists('posix_kill') || !posix_kill($pid, 0)) {
            $this->error("Server process with PID {$pid} is not running (stale PID file).");
            $this->comment('You may need to delete the PID file manually: ' . $pidFile);
            return self::FAILURE;
        }

        $this->info("Sending SIGUSR1 signal to Swoole master process (PID: {$pid}) for graceful reload...");

        // Send the graceful reload signal.
        posix_kill($pid, SIGUSR1);

        $this->info('Reload signal sent successfully. Workers will be reloaded one by one.');

        return self::SUCCESS;
    }
}
