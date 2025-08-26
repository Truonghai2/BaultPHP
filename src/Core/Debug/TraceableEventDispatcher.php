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
        private readonly DebugManager $debugManager,
    ) {
    }

    /**
     * Ghi lại event, sau đó dispatch nó bằng dispatcher gốc.
     */
    public function dispatch(object $event): void
    {
        if ($this->debugManager->isEnabled()) {
            $payload = 'null';
            try {
                $payload = json_encode($event, \JSON_PARTIAL_OUTPUT_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $payload = '"Could not serialize event payload: ' . json_last_error_msg() . '"';
                }
            } catch (\Throwable $e) {
                $payload = '"Could not serialize event payload: ' . $e->getMessage() . '"';
            }

            $this->debugManager->add('events', [
                'name' => get_class($event),
                'payload' => $payload,
            ]);
        }

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
