<?php

namespace Core\Debug;

use Core\Events\EventDispatcherInterface;

/**
 * Một lớp "wrapper" cho EventDispatcher để ghi lại các event được dispatch.
 * Nó implement cùng interface và ủy quyền tất cả các lệnh gọi đến dispatcher gốc,
 * đồng thời ghi lại dữ liệu vào DebugManager.
 */
class TraceableEventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly EventCollector $collector,
    ) {
    }

    /**
     * Ghi lại event vào collector, sau đó dispatch nó bằng dispatcher gốc.
     */
    public function dispatch(object $event): void
    {
        $this->collector->addEvent(get_class($event), $event);

        $this->dispatcher->dispatch($event);
    }

    public function listen(string $event, callable $listener): void
    {
        $this->dispatcher->listen($event, $listener);
    }

    public function getListenersForEvent(object $event): iterable
    {
        return $this->dispatcher->getListenersForEvent($event);
    }
}
