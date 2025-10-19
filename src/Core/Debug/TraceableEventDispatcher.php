<?php

namespace Core\Debug;

use App\Services\RealtimeDebugService;
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
        private readonly ?RealtimeDebugService $realtimeService = null,
    ) {
    }

    /**
     * Ghi lại event vào collector, sau đó dispatch nó bằng dispatcher gốc.
     */
    public function dispatch(object $event): void
    {
        $eventName = get_class($event);
        $this->collector->addEvent($eventName, $event);

        if ($this->realtimeService) {
            try {
                // Cố gắng serialize payload để xem trước.
                $payload = json_encode($event, \JSON_PARTIAL_OUTPUT_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_PRETTY_PRINT);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $payload = '"Could not serialize event payload: ' . json_last_error_msg() . '"';
                }
            } catch (\Throwable $e) {
                $payload = '"Could not serialize event payload: ' . $e->getMessage() . '"';
            }

            $this->realtimeService->publish('event', [
                'name' => $eventName,
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
