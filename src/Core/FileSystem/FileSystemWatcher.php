<?php

namespace Core\FileSystem;

use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Một class đơn giản để theo dõi sự thay đổi của file hệ thống.
 * Nó hoạt động bằng cách quét định kỳ các đường dẫn được chỉ định và
 * so sánh thời gian sửa đổi file (mtime).
 */
class FileSystemWatcher
{
    /**
     * Các đường dẫn đến thư mục cần theo dõi.
     * @var string[]
     */
    protected array $paths = [];

    /**
     * Các phần mở rộng file được phép theo dõi (ví dụ: ['php', 'env']).
     * Nếu rỗng, tất cả các file sẽ được theo dõi.
     * @var string[]
     */
    protected array $allowedExtensions = [];

    /**
     * Callback sẽ được gọi khi có sự thay đổi.
     * @var callable|null
     */
    protected $onChangeCallback = null;

    /**
     * Khoảng thời gian nghỉ giữa mỗi lần quét (tính bằng mili giây).
     * @var int
     */
    protected int $sleep = 1000;

    /**
     * Lưu trữ trạng thái các file (đường dẫn => mtime) từ lần quét trước.
     * @var array<string, int>
     */
    protected array $fileStates = [];

    /**
     * @param string[] $paths
     */
    public function __construct(array $paths = [])
    {
        $this->paths($paths);
    }

    /**
     * @param string[] $paths
     */
    public static function create(array $paths = []): self
    {
        return new self($paths);
    }

    /**
     * Thiết lập các đường dẫn cần theo dõi.
     *
     * @param string[] $paths
     * @return $this
     */
    public function paths(array $paths): self
    {
        $this->paths = array_filter($paths, 'is_dir');
        return $this;
    }

    /**
     * Thiết lập các phần mở rộng file cần theo dõi.
     * Việc này giúp tối ưu hóa bằng cách bỏ qua các file không liên quan.
     *
     * @param string[] $extensions Mảng các phần mở rộng không có dấu chấm (ví dụ: ['php', 'env']).
     * @return $this
     */
    public function extensions(array $extensions): self
    {
        // Chuyển tất cả về chữ thường để so sánh không phân biệt hoa/thường
        $this->allowedExtensions = array_map('strtolower', $extensions);
        return $this;
    }

    /**
     * Đăng ký một callback để xử lý khi có thay đổi.
     * Callback sẽ nhận 2 tham số: `string $type` ('created', 'modified', 'deleted') và `string $path`.
     *
     * @param callable $callback
     * @return $this
     */
    public function onStateChange(callable $callback): self
    {
        $this->onChangeCallback = $callback;
        return $this;
    }

    /**
     * Bắt đầu quá trình theo dõi.
     * Đây là một vòng lặp vô tận, nó sẽ block tiến trình.
     *
     * @throws LogicException Nếu chưa đăng ký callback.
     */
    public function start(): void
    {
        if (is_null($this->onChangeCallback)) {
            throw new LogicException('No callback registered. Use onStateChange() to register a callback.');
        }

        $this->fileStates = $this->scanFiles();

        while (true) {
            usleep($this->sleep * 1000);

            $newStates = $this->scanFiles();

            $createdOrModified = array_diff_assoc($newStates, $this->fileStates);
            $deleted = array_diff_key($this->fileStates, $newStates);

            if (!empty($createdOrModified) || !empty($deleted)) {
                foreach ($createdOrModified as $path => $mtime) {
                    $type = isset($this->fileStates[$path]) ? 'modified' : 'created';
                    call_user_func($this->onChangeCallback, $type, $path);
                }

                foreach (array_keys($deleted) as $path) {
                    call_user_func($this->onChangeCallback, 'deleted', $path);
                }

                $this->fileStates = $newStates;
            }
        }
    }

    protected function scanFiles(): array
    {
        $files = [];
        foreach ($this->paths as $path) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
            /** @var SplFileInfo $fileInfo */
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                // Lọc theo phần mở rộng nếu được chỉ định
                if (!empty($this->allowedExtensions) && !in_array(strtolower($fileInfo->getExtension()), $this->allowedExtensions, true)) {
                    continue;
                }

                // Chỉ thêm file vào danh sách nếu nó có đường dẫn thực
                if ($realPath = $fileInfo->getRealPath()) {
                    $files[$realPath] = $fileInfo->getMTime();
                }
            }
        }
        return $files;
    }
}
