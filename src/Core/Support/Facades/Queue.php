<?php

namespace Core\Support\Facades;

use Core\Contracts\Queue\Job;

/**
 * @method static void push(Job $job, string $queue = null)
 * @method static void later(int|\DateInterval|\DateTimeInterface $delay, Job $job, string $queue = null)
 * @method static \Core\Contracts\Queue\Queue connection(string $name = null)
 */
class Queue extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'queue';
    }

    /**
     * Một helper tiện lợi để đẩy job vào queue mặc định.
     */
    public static function dispatch(Job $job, ?string $queue = null): void
    {
        static::getFacadeRoot()->connection()->push($job, $queue);
    }
}
