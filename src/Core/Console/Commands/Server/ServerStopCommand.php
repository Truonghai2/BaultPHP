<?php

namespace Core\Console\Commands\Server;

use Core\Console\Contracts\BaseCommand;

class ServerStopCommand extends BaseCommand
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
        return 'serve:stop';
    }

    /**
     * The console command description.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Stops the Swoole server gracefully';
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        if (!extension_loaded('swoole')) {
            $this->error('The Swoole extension is not installed or enabled. This command cannot function without it.');
            return self::FAILURE;
        }

        $pidFile = config('server.swoole.pid_file');

        if (!file_exists($pidFile)) {
            $this->warn('Swoole server does not appear to be running (PID file not found).');
            $this->line(' > This command requires the server to be running in daemon mode to function.');
            return self::SUCCESS;
        }

        $pid = (int) file_get_contents($pidFile);

        if (!$pid) {
            $this->error('PID file is empty or invalid. Deleting it.');
            unlink($pidFile);
            return self::FAILURE;
        }

        if (!function_exists('posix_kill') || !posix_kill($pid, 0)) {
            $this->error("Server process with PID {$pid} is not running (stale PID file). Deleting it.");
            unlink($pidFile);
            return self::FAILURE;
        }

        $this->info("Sending SIGTERM signal to Swoole master process (PID: {$pid}) for graceful shutdown...");

        // Gửi tín hiệu SIGTERM để yêu cầu server tắt một cách an toàn.
        posix_kill($pid, SIGTERM);

        // Chờ server tắt hẳn.
        $timeout = 30; // Chờ tối đa 30 giây.
        $startTime = time();
        while (posix_kill($pid, 0)) {
            if (time() - $startTime > $timeout) {
                $this->error("Server did not stop within {$timeout} seconds. Please check manually.");
                return self::FAILURE;
            }
            usleep(500000); // Chờ 0.5 giây.
        }

        // Dọn dẹp file PID sau khi server đã dừng hẳn.
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }

        $this->info('Server stopped successfully.');
        return self::SUCCESS;
    }
}
