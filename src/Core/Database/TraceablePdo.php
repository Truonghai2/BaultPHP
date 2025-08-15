<?php

namespace Core\Database;

use DebugBar\DataCollector\PDO\PDOCollector;
use DebugBar\DataCollector\PDO\TraceablePDO;
use PDO;

/**
 * Class TraceablePdo
 *
 * Lớp này mở rộng TraceablePDO của Debugbar để tương thích với cách BaultPHP
 * sử dụng PDO. Nó đảm bảo rằng tất cả các phương thức gọi đến PDO đều được
 * ghi lại bởi Debugbar.
 *
 * @mixin PDO
 */
class TraceablePdo extends TraceablePDO
{
    /**
     * Ghi đè constructor để chỉ nhận vào một đối tượng PDO đã được kết nối.
     *
     * @param PDO $pdo Đối tượng PDO thật để bao bọc.
     */
    public function __construct(PDO $pdo)
    {
        // Không gọi parent constructor vì nó sẽ cố gắng tạo kết nối mới.
        // Chúng ta chỉ bao bọc một kết nối đã tồn tại.
        $this->pdo = $pdo;
    }

    /**
     * Thêm một collector để theo dõi các truy vấn.
     *
     * @param PDOCollector $collector
     */
    public function addCollector(PDOCollector $collector): void
    {
        $this->pdo_collector = $collector;
    }

    /**
     * Ghi đè phương thức __call để đảm bảo tất cả các lời gọi phương thức
     * không được định nghĩa rõ ràng trong lớp này (như getAttribute, setAttribute, etc.)
     * đều được chuyển tiếp đến đối tượng PDO thật.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->pdo, $method], $args);
    }

    /**
     * Ghi đè phương thức __get để truy cập các thuộc tính của đối tượng PDO thật.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->pdo->{$key};
    }

    /**
     * Ghi đè phương thức __set để thiết lập các thuộc tính của đối tượng PDO thật.
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        $this->pdo->{$key} = $value;
    }

    /**
     * Trả về đối tượng PDO gốc bên trong.
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Cần triển khai phương thức này vì nó là abstract trong TraceablePDO,
     * mặc dù chúng ta không sử dụng nó trực tiếp trong logic của mình.
     *
     * @param string $dsn
     * @param string|null $username
     * @param string|null $password
     * @param array|null $options
     * @return void
     */
    public function connect(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        // Không làm gì cả vì chúng ta đã có một kết nối được truyền vào.
    }
}
