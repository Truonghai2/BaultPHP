<?php

namespace Core\Services;

use Core\Filesystem\Filesystem;
use Core\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * ModuleInstallerService handles the installation of modules from ZIP files.
 * It validates, extracts, and verifies module integrity before installation.
 */
class ModuleInstallerService
{
    public function __construct(protected Filesystem $fs)
    {
        // Filesystem is now injected by the DI container.
    }

    public function install(string $zipPath): void
    {
        $this->ensureZipIsValid($zipPath);

        $tmpDir = storage_path('app/tmp_module_' . md5($zipPath . time()));
        $this->extractZip($zipPath, $tmpDir);

        $jsonPath = $tmpDir . '/module.json';
        $moduleMeta = $this->verifyModuleJson($jsonPath);

        $this->verifySignature($moduleMeta, $tmpDir);
        $this->scanForDangerousCode($tmpDir);
        $this->validateStructure($tmpDir, $moduleMeta);

        $targetDir = base_path('Modules/' . $moduleMeta['name']);
        if ($this->fs->exists($targetDir)) {
            throw new \Exception("Module '{$moduleMeta['name']}' đã tồn tại.");
        }

        // --- Cải tiến Bảo mật: Sử dụng `moveDirectory` thay vì `copyDirectory` ---
        // Việc di chuyển (move) là một hành động nguyên tử hơn trên nhiều hệ thống file,
        // giúp giảm thiểu nguy cơ tấn công Race Condition (TOCTOU), nơi kẻ tấn công
        // có thể thay đổi file trong thư mục tạm sau khi đã được xác thực nhưng trước khi được sao chép.
        $this->fs->moveDirectory($tmpDir, $targetDir);
        // Không cần xóa $tmpDir nữa vì nó đã được di chuyển.

        event(new \Core\Events\ModuleInstalled($moduleMeta['name'], auth()->id() ?? null));
        Log::info("Module '{$moduleMeta['name']}' đã được cài đặt thành công.", ['installer' => 'zip']);
    }

    protected function ensureZipIsValid(string $zipPath): void
    {
        if (!file_exists($zipPath) || mime_content_type($zipPath) !== 'application/zip') {
            throw new \Exception('File không hợp lệ hoặc không phải là file ZIP.');
        }

        if (filesize($zipPath) > 50 * 1024 * 1024) { // 50MB
            throw new \Exception('File module vượt quá giới hạn 50MB.');
        }
    }

    protected function extractZip(string $zipPath, string $extractTo): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \Exception('Không thể mở file module ZIP.');
        }

        // --- Cải tiến Bảo mật: Chống Zip Bomb (Denial of Service) ---
        // Tính tổng kích thước file sau khi giải nén để tránh bị tấn công làm đầy ổ đĩa.
        $totalUncompressedSize = 0;
        $maxUncompressedSize = 250 * 1024 * 1024; // Giới hạn 250MB
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (substr($stat['name'], -1) !== '/') { // Chỉ tính file, bỏ qua thư mục
                $totalUncompressedSize += $stat['size'];
            }
        }
        if ($totalUncompressedSize > $maxUncompressedSize) {
            $zip->close();
            throw new \Exception('Kích thước module sau khi giải nén vượt quá giới hạn 250MB.');
        }

        // --- Cải tiến Bảo mật: Chống Zip Slip (Path Traversal) ---
        // Kiểm tra tất cả các đường dẫn file trong ZIP trước khi giải nén.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (str_contains($filename, '..')) {
                $zip->close();
                throw new \Exception("Phát hiện đường dẫn không an toàn trong file ZIP: {$filename}");
            }
        }

        // SỬA LỖI: Nếu tất cả các kiểm tra đã qua, tiến hành giải nén.
        // Lệnh này đã bị thiếu trong phiên bản trước.
        $this->fs->ensureDirectoryExists($extractTo);
        $zip->extractTo($extractTo);
        $zip->close();
    }

    protected function verifyModuleJson(string $jsonPath): array
    {
        if (!$this->fs->exists($jsonPath)) {
            throw new \Exception('module.json không tồn tại.');
        }

        $json = json_decode(file_get_contents($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Lỗi định dạng JSON trong module.json.');
        }

        $required = ['name', 'enabled', 'providers', 'signature'];
        foreach ($required as $field) {
            if (!isset($json[$field])) {
                throw new \Exception("Thiếu trường '$field' trong module.json.");
            }
        }

        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]+$/', $json['name'])) {
            throw new \Exception("Tên module '{$json['name']}' không hợp lệ.");
        }

        return $json;
    }

    protected function verifySignature(array $meta, string $dir): void
    {
        $hashFile = $dir . '/HASH';
        if (!$this->fs->exists($hashFile)) {
            throw new \Exception('Không tìm thấy file HASH để kiểm tra chữ ký.');
        }

        $expected = trim(file_get_contents($hashFile));

        $allFiles = collect($this->fs->allFiles($dir))
            ->filter(fn ($file) => !in_array($file->getFilename(), ['HASH']))
            ->sortBy(fn ($f) => $f->getRelativePathname())
            ->values();

        // --- Cải tiến Hiệu năng: Sử dụng streaming để tính hash ---
        // Thay vì đọc tất cả file vào bộ nhớ, chúng ta xử lý từng file một.
        // Điều này giúp giảm đáng kể việc sử dụng bộ nhớ và ngăn ngừa lỗi
        // "memory exhausted" với các module lớn.
        $hashContext = hash_init('sha256');
        foreach ($allFiles as $file) {
            // Sử dụng hash_update_stream để hiệu quả hơn là file_get_contents
            $stream = fopen($file->getRealPath(), 'r');
            if ($stream === false) {
                throw new \Exception("Không thể đọc file để xác thực: {$file->getRelativePathname()}");
            }
            hash_update_stream($hashContext, $stream);
            fclose($stream);
        }
        $actual = hash_final($hashContext);

        if (!hash_equals($expected, $actual)) {
            throw new \Exception('Chữ ký HASH không khớp, module có thể bị sửa đổi.');
        }
    }

    protected function scanForDangerousCode(string $dir): void
    {
        $dangerous = ['eval', 'exec', 'shell_exec', 'passthru', 'system', 'proc_open', 'popen'];
        $phpFiles = collect($this->fs->allFiles($dir))
            ->filter(fn ($f) => $f->getExtension() === 'php');

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            foreach ($dangerous as $func) {
                if (preg_match("/\\b{$func}\\s*\\(/i", $content)) {
                    throw new \Exception("Phát hiện hàm nguy hiểm '{$func}' trong file: {$file->getRelativePathname()}");
                }
            }
        }
    }

    protected function validateStructure(string $dir, array $meta): void
    {
        $invalid = collect($this->fs->allFiles($dir))->filter(function ($file) {
            $path = $file->getPathname();
            return Str::contains($path, [
                'Core/', 'src/Core', 'bootstrap/', 'config/app.php', 'routes/web.php',
            ]);
        });

        if ($invalid->count() > 0) {
            throw new \Exception('❌ Module không được phép ghi đè Core/framework hoặc file hệ thống.');
        }

        if (!$this->fs->exists($dir . '/Providers/ModuleServiceProvider.php')) {
            throw new \Exception('❌ Module phải có Providers/ModuleServiceProvider.php');
        }
    }
}
