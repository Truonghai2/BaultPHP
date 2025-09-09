<?php

namespace Core\Http;

use Core\Support\Facades\Storage;
use Psr\Http\Message\UploadedFileInterface;

class UploadedFile
{
    public function __construct(protected UploadedFileInterface $psrUploadedFile)
    {
    }

    /**
     * Tự động tạo tên file duy nhất và lưu vào thư mục chỉ định.
     *
     * @param string $path Thư mục lưu trữ (ví dụ: 'avatars').
     * @param string|null $disk Disk để lưu file.
     * @return string|false Đường dẫn tương đối của file đã lưu.
     */
    public function store(string $path, ?string $disk = null): string|false
    {
        $hashName = bin2hex(random_bytes(20)) . '.' . $this->getClientOriginalExtension();
        return $this->storeAs($path, $hashName, $disk);
    }

    /**
     * Lưu file với tên chỉ định.
     *
     * @param string $path Thư mục lưu trữ.
     * @param string $name Tên file.
     * @param string|null $disk Disk để lưu file.
     * @return string|false Đường dẫn tương đối của file đã lưu.
     */
    public function storeAs(string $path, string $name, ?string $disk = null): string|false
    {
        $disk = $disk ?: config('filesystems.default');
        $fullPath = rtrim($path, '/') . '/' . $name;

        Storage::disk($disk)->put($fullPath, $this->psrUploadedFile->getStream());

        return $fullPath;
    }

    public function getClientOriginalExtension(): string
    {
        return pathinfo($this->psrUploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    }

    public function __call($method, $parameters)
    {
        return $this->psrUploadedFile->$method(...$parameters);
    }
}
