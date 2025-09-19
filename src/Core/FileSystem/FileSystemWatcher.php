<?php

namespace Core\Filesystem;

use LogicException;
use RecursiveDirectoryIterator;
use RecursiveFilterIterator;
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
     * Các đường dẫn đến thư mục/file cần bỏ qua.
     * @var string[]
     */
    protected array $ignorePaths = [];

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
     * Điều kiện để vòng lặp tiếp tục chạy.
     * @var callable|null
     */
    protected $loopCondition = null;

    /**
     * @param string[] $paths Các thư mục cần theo dõi.
     * @param string[] $ignorePaths Các thư mục/file cần bỏ qua.
     */
    public function __construct(array $paths = [], array $ignorePaths = [])
    {
        // Mặc định, vòng lặp sẽ chạy mãi mãi nếu không có điều kiện nào được thiết lập.
        $this->loopCondition = fn () => true;
        $this->paths($paths);
        $this->ignore($ignorePaths);
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
        $this->paths = array_map([$this, 'normalizePath'], array_filter($paths, 'is_dir'));
        return $this;
    }

    /**
     * Thiết lập các đường dẫn cần bỏ qua.
     *
     * @param string[] $paths
     * @return $this
     */
    public function ignore(array $paths): self
    {
        $this->ignorePaths = array_map(function ($path) {
            // Nếu là file, chuẩn hóa và trả về. Nếu là thư mục, thêm dấu / cuối để so sánh chính xác.
            return is_dir($path) ? rtrim($this->normalizePath($path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : $this->normalizePath($path);
        }, $paths);
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
     * Thiết lập một điều kiện để kiểm tra trong mỗi vòng lặp.
     * Vòng lặp sẽ dừng khi callback trả về false.
     *
     * @param callable $condition
     * @return $this
     */
    public function setLoopCondition(callable $condition): self
    {
        $this->loopCondition = $condition;
        return $this;
    }

    /**
     * Bắt đầu quá trình theo dõi.
     * Đây là một vòng lặp, nó sẽ block tiến trình cho đến khi điều kiện lặp trả về false.
     *
     * @throws \LogicException Nếu chưa đăng ký callback.
     */
    public function start(): void
    {
        if (is_null($this->onChangeCallback)) {
            throw new LogicException('No callback registered. Use onStateChange() to register a callback.');
        }

        $this->fileStates = $this->scanFiles();

        while (call_user_func($this->loopCondition)) {
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
            if (!is_dir($path)) {
                continue;
            }

            $directoryIterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);

            // Sử dụng RecursiveFilterIterator để lọc hiệu quả
            $filterIterator = new class ($directoryIterator, $this->ignorePaths, $this->allowedExtensions) extends RecursiveFilterIterator {
                private array $ignorePaths;
                private array $allowedExtensions;

                public function __construct(\RecursiveIterator $iterator, array $ignorePaths, array $allowedExtensions)
                {
                    parent::__construct($iterator);
                    $this->ignorePaths = $ignorePaths;
                    $this->allowedExtensions = $allowedExtensions;
                }

                public function accept(): bool
                {
                    /** @var \SplFileInfo $fileInfo */
                    $fileInfo = $this->current();
                    $realPath = $fileInfo->getRealPath();

                    if (!$realPath) {
                        return false;
                    }

                    $normalizedPath = str_replace('\\', '/', $realPath);

                    foreach ($this->ignorePaths as $ignorePath) {
                        if (str_starts_with($normalizedPath, $ignorePath)) {
                            return false;
                        }
                    }

                    if ($fileInfo->isDir()) {
                        return true;
                    }

                    if (!empty($this->allowedExtensions)) {
                        return in_array(strtolower($fileInfo->getExtension()), $this->allowedExtensions, true);
                    }

                    return true;
                }

                public function getChildren(): self
                {
                    return new self($this->getInnerIterator()->getChildren(), $this->ignorePaths, $this->allowedExtensions);
                }
            };

            $iterator = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::SELF_FIRST);

            /** @var SplFileInfo $fileInfo */
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isFile() && ($realPath = $fileInfo->getRealPath())) {
                    $files[$realPath] = $fileInfo->getMTime();
                }
            }
        }
        return $files;
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
