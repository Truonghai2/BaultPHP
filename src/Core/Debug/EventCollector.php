<?php

namespace Core\Debug;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

/**
 * Class EventCollector
 *
 * Thu thập dữ liệu về các event đã được dispatch trong ứng dụng.
 */
class EventCollector extends DataCollector implements Renderable
{
    protected array $events = [];

    /**
     * Thêm một event vào danh sách.
     *
     * @param string $name Tên của event.
     * @param object $event Instance của event.
     */
    public function addEvent(string $name, object $event): void
    {
        $payload = 'null';
        try {
            // Cố gắng serialize payload để xem trước.
            // Sử dụng các flag để xử lý lỗi một cách an toàn.
            $payload = json_encode($event, \JSON_PARTIAL_OUTPUT_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_PRETTY_PRINT);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $payload = '"Could not serialize event payload: ' . json_last_error_msg() . '"';
            }
        } catch (\Throwable $e) {
            $payload = '"Could not serialize event payload: ' . $e->getMessage() . '"';
        }

        $this->events[] = [
            'message' => $name,
            'is_string' => false, // Cho phép Debugbar định dạng nó như một đối tượng
            'data' => $payload,
            'label' => 'event',
            'time' => microtime(true),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        return [
            'count' => count($this->events),
            'records' => $this->events,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'events';
    }

    /**
     * {@inheritdoc}
     */
    public function getWidgets(): array
    {
        return [
            'events' => [
                'icon' => 'tags',
                'widget' => 'PhpDebugBar.Widgets.MessagesWidget',
                'map' => 'events.records',
                'default' => '[]',
            ],
            'events:badge' => [
                'map' => 'events.count',
                'default' => 0,
            ],
        ];
    }
}
