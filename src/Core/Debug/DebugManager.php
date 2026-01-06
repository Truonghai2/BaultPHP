<?php

namespace Core\Debug;

use Core\Contracts\StatefulService;

/**
 * Quản lý việc thu thập dữ liệu debug cho một request duy nhất.
 * Implement StatefulService để đảm bảo trạng thái được reset sau mỗi request trong Swoole.
 */
class DebugManager implements StatefulService
{
    protected array $data = [];
    protected bool $enabled = false;

    public function __construct()
    {
        $this->resetState();
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Ghi lại một mục dữ liệu vào một "collector" cụ thể.
     *
     * @param string $collector Tên của bộ thu thập (vd: 'queries', 'logs').
     * @param mixed $entry Dữ liệu cần ghi lại.
     */
    public function add(string $collector, mixed $entry): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->data[$collector][] = $entry;
    }

    /**
     * Set data trực tiếp cho một collector (không wrap trong array).
     * Dùng cho cookies, session, cache summary, etc.
     *
     * @param string $collector Tên của bộ thu thập.
     * @param mixed $data Dữ liệu cần set.
     */
    public function set(string $collector, mixed $data): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->data[$collector] = $data;
    }

    /**
     * Lấy tất cả dữ liệu đã thu thập.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Sets the debug data, merging it with existing data.
     * This is used to import data from the main DebugBar service.
     */
    public function setData(array $data): void
    {
        if (!$this->enabled) {
            return;
        }
        // Recursively replace data to ensure nested arrays are merged correctly.
        $this->data = array_replace_recursive($this->data, $data);
    }

    /**
     * Reset trạng thái của manager, sẵn sàng cho request tiếp theo.
     */
    public function resetState(): void
    {
        $this->data = [
            'info' => [],
            'queries' => [],
            'logs' => [],
            'events' => [],
            'exceptions' => [],
            'dumps' => [],
            'config' => [],
        ];
        // Giữ lại trạng thái enabled
    }

    /**
     * Ghi lại toàn bộ cấu hình ứng dụng.
     */
    public function recordConfig(array $config): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->data['config'] = $config;
    }

    /**
     * Ghi lại thông tin chung về request.
     */
    public function recordRequestInfo(array $info): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->data['info'] = array_merge($this->data['info'], $info);
    }

    /**
     * Ghi lại một event đã được dispatched.
     *
     * @param string $name Tên của event.
     * @param mixed $payload Dữ liệu của event.
     */
    public function recordEvent(string $name, mixed $payload): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        $this->data['events'][] = [
            'name' => $name,
            'payload' => is_string($payload) ? $payload : json_encode($payload, JSON_PRETTY_PRINT),
            'timestamp' => microtime(true),
        ];
    }

    public function recordQuery(string $query, array $bindings = [], float $time = 0.0): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        $this->data['queries'][] = [
            'query' => $query,
            'bindings' => $bindings,
            'time' => $time,
        ];
    }

    public function recordBrowserEvent(string $event, mixed $payload = null): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->data['browser_events'][] = [
            'event' => $event,
            'payload' => $payload,
            'timestamp' => microtime(true),
        ];
    }
}
