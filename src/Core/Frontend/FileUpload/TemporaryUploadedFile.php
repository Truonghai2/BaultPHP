<?php

namespace Core\Frontend\FileUpload;

use Illuminate\Http\UploadedFile;
use League\Flysystem\Filesystem;

class TemporaryUploadedFile
{
    protected string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Tạo một instance từ một file đã được upload.
     */
    public static function createFromUploadedFile(UploadedFile $file): self
    {
        $disk = self::getTemporaryDisk();
        $filename = 'bault-tmp-' . bin2hex(random_bytes(10)) . '.' . $file->getClientOriginalExtension();
        
        // Lưu file vào thư mục tạm
        $disk->write($filename, $file->getContent());

        return new static($filename);
    }

    /**
     * Tạo một instance từ đường dẫn file tạm.
     */
    public static function createFromTemporaryPath(string $path): self
    {
        return new static($path);
    }

    /**
     * Kiểm tra xem một giá trị có phải là đường dẫn file tạm không.
     */
    public static function isTemporaryFile($value): bool
    {
        return is_string($value) && str_starts_with($value, 'bault-tmp-');
    }

    /**
     * Lưu file vào nơi lưu trữ cuối cùng.
     */
    public function store(string $path, $diskName = null): string
    {
        $disk = app('filesystem')->disk($diskName ?? config('filesystems.default'));
        $temporaryDisk = self::getTemporaryDisk();

        $stream = $temporaryDisk->readStream($this->path);
        $newPath = rtrim($path, '/') . '/' . basename($this->path);

        $disk->writeStream($newPath, $stream);

        // Xóa file tạm sau khi đã lưu
        $temporaryDisk->delete($this->path);

        return $newPath;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    protected static function getTemporaryDisk(): Filesystem
    {
        // Giả định bạn có một disk tên là 'local_temp' để lưu file tạm
        return app('filesystem')->disk('local_temp');
    }
}
