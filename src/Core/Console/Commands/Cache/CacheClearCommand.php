<?php

namespace Core\Console\Commands\Cache;

use Core\Console\Contracts\BaseCommand;
use Core\Contracts\Cache\Factory as CacheFactory;

class CacheClearCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'cache:clear';
    }

    public function description(): string
    {
        return 'Flush the application cache and all bootstrap caches.';
    }

    public function handle(): int
    {
        /** @var CacheFactory $cache */
        $cache = $this->app->make(CacheFactory::class);

        // Xóa cache ứng dụng (Redis, file, etc.)
        $cache->store()->clear();
        $this->info('✔ Application cache flushed.');

        // Xóa các cache khởi động
        $this->comment('› Clearing bootstrap caches...');
        $this->call('config:clear');
        $this->call('route:clear');
        $this->call('view:clear');
        $this->call('event:clear');
        $this->call('command:clear');
        $this->call('provider:clear');
        $this->call('optimize:clear');
        $this->call('module:clear');
        $this->call('bootstrap:clear');

        $this->info('✔ All caches cleared successfully!');

        return self::SUCCESS;
    }
}
