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
     * Lấy tất cả dữ liệu đã thu thập.
     */
    public function getData(): array
    {
        return $this->data;
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
}
