<?php

declare(strict_types=1);

namespace Core\Queue;

/**
 * Class Job
 *
 * Lớp cơ sở cho tất cả các job trong ứng dụng.
 * Các job cụ thể nên kế thừa từ lớp này.
 *
 * Việc có một lớp cơ sở chung giúp dễ dàng xác định và xử lý các job
 * một cách nhất quán trong toàn bộ hệ thống hàng đợi.
 *
 * @package Core\Queue
 */
abstract class Job
{
    // Lớp này có thể được để trống. Nó hoạt động như một "marker" và một điểm
    // mở rộng trong tương lai cho tất cả các job. Ví dụ: bạn có thể thêm các
    // phương thức hoặc thuộc tính chung như quản lý số lần thử lại hoặc timeout.
}
