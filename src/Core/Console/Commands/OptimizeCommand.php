<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;
use Throwable;

class OptimizeCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'optimize';
    }

    public function description(): string
    {
        return 'Cache the framework bootstrap files for a performance boost.';
    }

    public function handle(): int
    {
        $this->info('Caching framework bootstrap files...');

        try {
            $this->call('config:cache');
            $this->call('route:cache');
            $this->call('optimize:compile');
        } catch (Throwable $e) {
            $this->error('An error occurred during optimization: ' . $e->getMessage());
            // Cố gắng xóa các cache đã được tạo một phần để tránh trạng thái lỗi
            $this->call('cache:clear');
            return self::FAILURE;
        }

        $this->info('Framework cached successfully!');

        return self::SUCCESS;
    }
}
