<?php

namespace Core\Metrics;

use Swoole\Table;

class SwooleMetricsService
{
    protected Table $metrics;

    /**
     * Các bucket mặc định cho việc đo thời gian (tính bằng giây).
     * @var float[]
     */
    public const DEFAULT_DURATION_BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10];

    protected array $metricInfo = [];
    protected array $histogramBuckets = [];
    protected int $tableSize;
    protected int $keyLength;

    public function __construct(array $config = [])
    {
        // Tăng kích thước bảng mặc định để xử lý hàng triệu metrics.
        // 2^20 = 1,048,576, một kích thước khởi đầu tốt.
        $this->tableSize = (int) ($config['size'] ?? 65536);
        $this->keyLength = (int) ($config['key_length'] ?? 256); // Độ dài cho full_key
        $this->histogramBuckets = $config['histogram_buckets'] ?? [];
        $this->metricInfo = $config['definitions'] ?? [];

        // Sử dụng một bảng duy nhất cho tất cả các loại metrics để đơn giản hóa.
        $this->metrics = new Table($this->tableSize);
        $this->metrics->column('full_key', Table::TYPE_STRING, $this->keyLength);
        $this->metrics->column('value', Table::TYPE_FLOAT);
        // Thêm cột 'type' để phân biệt khi xuất dữ liệu
        $this->metrics->column('metric_name', Table::TYPE_STRING, 128);
        $this->metrics->create();
    }

    /**
     * Sử dụng MD5 để tạo key có độ dài cố định cho Swoole Table, tránh lỗi key quá dài.
     */
    private function getHashedKey(string $fullKey): string
    {
        return md5($fullKey);
    }

    /**
     * Tăng một bộ đếm.
     */
    public function increment(string $name, float $value = 1.0, array $labels = []): void
    {
        $fullKey = $this->generateKey($name, $labels);
        $hashedKey = $this->getHashedKey($fullKey);

        // Thao tác incr là atomic. Nếu key không tồn tại, nó sẽ trả về false.
        if ($this->metrics->incr($hashedKey, 'value', $value) === false) {
            if (($this->metrics->count() >= $this->metrics->size)) {
                error_log("SwooleMetricsService: Bảng metrics đã đầy. Không thể thêm metric '{$name}'.");
                return;
            }
            // Đặt giá trị ban đầu nếu key chưa tồn tại.
            $this->metrics->set($hashedKey, [
                'full_key' => $fullKey,
                'value' => $value,
                'metric_name' => $name,
            ]);
        }
    }

    /**
     * Thiết lập giá trị cho một gauge.
     */
    public function setGauge(string $name, float $value, array $labels = []): void
    {
        $fullKey = $this->generateKey($name, $labels);
        $hashedKey = $this->getHashedKey($fullKey);

        if (($this->metrics->count() >= $this->metrics->size) && !$this->metrics->exist($hashedKey)) {
            error_log("SwooleMetricsService: Bảng metrics đã đầy. Không thể thêm gauge '{$name}'.");
            return;
        }

        $this->metrics->set($hashedKey, [
            'full_key' => $fullKey,
            'value' => $value,
            'metric_name' => $name,
        ]);
    }

    /**
     * Ghi nhận một giá trị cho histogram.
     */
    public function observe(string $name, float $value, array $labels = []): void
    {
        $buckets = $this->histogramBuckets[$name] ?? self::DEFAULT_DURATION_BUCKETS;

        // Tăng giá trị cho các bucket phù hợp
        foreach ($buckets as $bucket) {
            if ($value <= $bucket) {
                $this->increment($name . '_bucket', array_merge($labels, ['le' => (string)$bucket]));
            }
        }

        // Thêm bucket cho +Inf
        $this->increment($name . '_bucket', array_merge($labels, ['le' => '+Inf']));

        // Tăng tổng
        $this->increment($name . '_sum', $labels, $value);

        // Tăng số lượng
        $this->increment($name . '_count', $labels);
    }

    /**
     * Lấy tất cả metrics và định dạng chúng theo chuẩn Prometheus.
     * Đây là phiên bản đã được tối ưu và sửa lỗi.
     */
    public function getMetricsAsPrometheus(): string
    {
        $groupedMetrics = [];

        // Nhóm các metrics theo tên
        foreach ($this->metrics as $row) {
            $metricName = $row['metric_name'];
            if (!isset($groupedMetrics[$metricName])) {
                $groupedMetrics[$metricName] = [];
            }
            $groupedMetrics[$metricName][] = [
                'full_key' => $row['full_key'],
                'value' => $row['value'],
            ];
        }

        $output = [];
        foreach ($groupedMetrics as $name => $metrics) {
            $info = $this->metricInfo[$name] ?? null;
            if ($info) {
                $output[] = '# HELP ' . $name . ' ' . ($info['help'] ?? 'No help text provided.');
                $output[] = '# TYPE ' . $name . ' ' . ($info['type'] ?? 'untyped');
            }

            foreach ($metrics as $metric) {
                // Xuất full_key, không phải hashed key
                $output[] = sprintf('%s %s', $metric['full_key'], $metric['value']);
            }
            $output[] = ''; // Thêm dòng trống giữa các group
        }

        return implode("\n", $output);
    }

    /**
     * Tạo một key duy nhất từ tên metric và các label.
     */
    private function generateKey(string $name, array $labels): string
    {
        if (empty($labels)) {
            return $name;
        }

        ksort($labels);
        $labelParts = [];
        foreach ($labels as $key => $value) {
            $labelParts[] = sprintf('%s="%s"', $key, addslashes((string)$value));
        }

        return $name . '{' . implode(',', $labelParts) . '}';
    }
}
