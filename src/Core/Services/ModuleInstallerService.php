<?php

namespace Core\Services;

use Illuminate\Filesystem\Filesystem;
use ZipArchive;
use Illuminate\Support\Str;

/**
 * ModuleInstallerService handles the installation of modules from ZIP files.
 * It validates, extracts, and verifies module integrity before installation.
 */
class ModuleInstallerService
{
    protected Filesystem $fs;

    public function __construct()
    {
        $this->fs = new Filesystem;
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

        $this->fs->copyDirectory($tmpDir, $targetDir);
        $this->fs->deleteDirectory($tmpDir);

        event(new \Core\Events\ModuleInstalled($moduleMeta['name'], auth()->id() ?? null));
        echo "Module '{$moduleMeta['name']}' đã được cài đặt thành công.\n";
    }

    protected function ensureZipIsValid(string $zipPath): void
    {
        if (!file_exists($zipPath) || mime_content_type($zipPath) !== 'application/zip') {
            throw new \Exception('File không hợp lệ hoặc không phải định dạng ZIP.');
        }

        if (filesize($zipPath) > 50 * 1024 * 1024) { // 50MB
            throw new \Exception('File module vượt quá giới hạn 50MB.');
        }
    }

    protected function extractZip(string $zipPath, string $extractTo): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \Exception('Không thể giải nén file module.');
        }
        $this->fs->deleteDirectory($extractTo);
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
            ->filter(fn($file) => !in_array($file->getFilename(), ['HASH']))
            ->sortBy(fn($f) => $f->getRelativePathname())
            ->values();

        $combined = '';
        foreach ($allFiles as $file) {
            $combined .= file_get_contents($file);
        }

        $actual = hash('sha256', $combined);

        if (!hash_equals($expected, $actual)) {
            throw new \Exception('Chữ ký HASH không khớp, module có thể bị sửa đổi.');
        }
    }

    protected function scanForDangerousCode(string $dir): void
    {
        $dangerous = ['eval', 'exec', 'shell_exec', 'passthru', 'system', 'proc_open', 'popen'];
        $phpFiles = collect($this->fs->allFiles($dir))
            ->filter(fn($f) => $f->getExtension() === 'php');

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
                'Core/', 'src/Core', 'bootstrap/', 'config/app.php', 'routes/web.php'
            ]);
        });

        if ($invalid->count() > 0) {
            throw new \Exception("❌ Module không được phép ghi đè Core/framework hoặc file hệ thống.");
        }

        if (!$this->fs->exists($dir . '/Providers/ModuleServiceProvider.php')) {
            throw new \Exception("❌ Module phải có Providers/ModuleServiceProvider.php");
        }
    }
}
