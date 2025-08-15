<?php

namespace Core\Frontend;

/**
 * Dịch vụ chuyên trách việc tạo và xác thực checksum cho snapshot của component.
 * Việc tách logic này ra một class riêng giúp tái sử dụng và dễ dàng kiểm thử.
 */
class ChecksumService
{
    public function __construct(private string $appKey)
    {
    }

    /**
     * Tạo checksum cho một snapshot.
     */
    public function generate(string $class, array $data): string
    {
        // BẢO MẬT: Sắp xếp dữ liệu theo key để đảm bảo chuỗi JSON luôn nhất quán,
        // tránh việc checksum bị sai một cách ngẫu nhiên.
        ksort($data);
        return hash_hmac('sha256', $class . json_encode($data), $this->appKey);
    }

    /**
     * Xác thực checksum của một snapshot.
     */
    public function verify(string $class, array $data, string $checksum): bool
    {
        $expectedChecksum = $this->generate($class, $data);
        return hash_equals($expectedChecksum, $checksum);
    }
}
