<?php

namespace Core\Metrics;

use Spiral\RoadRunner\Metrics\Collector;
use Spiral\RoadRunner\Metrics\MetricsInterface;

class MetricsService
{
    private MetricsInterface $metrics;

    public function __construct(MetricsInterface $metrics)
    {
        $this->metrics = $metrics;
    }

    /**
     * Tăng một chỉ số đếm (Counter).
     * Rất hữu ích để đếm các sự kiện như: số lượng người dùng đăng ký, số đơn hàng được tạo.
     *
     * @param string $name Tên của metric, ví dụ: 'users_created_total'.
     * @param float $value Giá trị tăng thêm (mặc định là 1).
     * @param array $labels Các nhãn để phân loại, ví dụ: ['source' => 'api'].
     */
    public function incrementCounter(string $name, float $value = 1.0, array $labels = []): void
    {
        $this->getCollector($name, 'counter')->add($value, $labels);
    }

    /**
     * Đặt giá trị cho một chỉ số đo lường (Gauge).
     * Hữu ích để theo dõi một giá trị có thể tăng hoặc giảm, ví dụ: số lượng kết nối đang hoạt động, dung lượng bộ nhớ đang sử dụng.
     *
     * @param string $name Tên của metric, ví dụ: 'active_websocket_connections'.
     * @param float $value Giá trị cần đặt.
     * @param array $labels Các nhãn.
     */
    public function setGauge(string $name, float $value, array $labels = []): void
    {
        $this->getCollector($name, 'gauge')->set($value, $labels);
    }

    private function getCollector(string $name, string $type): Collector
    {
        // Đảm bảo rằng tên metric tuân thủ quy ước của Prometheus
        $cleanedName = str_replace('-', '_', $name);
        return $this->metrics->get($cleanedName, $type);
    }
}
