<?php

namespace Core\Support\Facades;

/**
 * Bus Facade
 * 
 * @method static \Core\Queue\PendingBatch batch(array $jobs)
 * @method static \Core\Queue\Batch findBatch(string $batchId)
 * @method static void dispatch($job)
 * @method static void dispatchNow($job)
 * @method static \Core\Queue\PendingChain chain(array $jobs)
 * 
 * @see \Core\Queue\BusDispatcher
 */
class Bus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bus';
    }
}
