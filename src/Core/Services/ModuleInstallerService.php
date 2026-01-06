<?php

namespace Core\Services;

use Core\Exceptions\Module\DangerousCodeDetectedException;
use Core\Exceptions\Module\InvalidModuleFileException;
use Core\Exceptions\Module\InvalidModuleSignatureException;
use Core\Exceptions\Module\InvalidModuleStructureException;
use Core\Exceptions\Module\ModuleAlreadyExistsException;
use Core\FileSystem\Filesystem;
use Core\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * ModuleInstallerService handles the installation of modules from ZIP files.
 * It validates, extracts, and verifies module integrity before installation.
 */
class ModuleInstallerService
{
    /**
     * @param Filesystem $fs The filesystem utility.
     * @param int $maxZipSize Maximum allowed size for the ZIP file in bytes.
     * @param int $maxUncompressedSize Maximum allowed total size of files after extraction.
     */
    public function __construct(
        protected Filesystem $fs,
        protected int $maxZipSize = 50 * 1024 * 1024, // 50MB
        protected int $maxUncompressedSize = 250 * 1024 * 1024, // 250MB
    ) {
    }

    public function install(string $zipPath): void
    {
        $tmpDir = storage_path('app/tmp_module_' . md5($zipPath . time()));

        try {
            $this->ensureZipIsValid($zipPath);
            $this->extractZip($zipPath, $tmpDir);

            $jsonPath = $tmpDir . '/module.json';
            $moduleMeta = $this->verifyModuleJson($jsonPath);

            $this->verifySignature($moduleMeta, $tmpDir);
            $this->scanForDangerousCode($tmpDir);
            $this->validateStructure($tmpDir, $moduleMeta);

            $targetDir = base_path('Modules/' . $moduleMeta['name']);
            if ($this->fs->exists($targetDir)) {
                throw new ModuleAlreadyExistsException("Module '{$moduleMeta['name']}' already exists.");
            }

            $this->fs->moveDirectory($tmpDir, $targetDir);
            try {
                event(new \Core\Events\ModuleInstalled($moduleMeta['name'], auth()->id() ?? null));
                Log::info("Module '{$moduleMeta['name']}' has been installed successfully.", ['installer' => 'zip']);
            } catch (\Throwable $e) {
                $this->fs->deleteDirectory($targetDir);
                Log::warning("Rolled back installation of module '{$moduleMeta['name']}' due to an error after moving files.", ['error' => $e->getMessage()]);
                throw $e;
            }
        } finally {
            if ($this->fs->exists($tmpDir)) {
                $this->fs->deleteDirectory($tmpDir);
            }
        }
    }

    protected function ensureZipIsValid(string $zipPath): void
    {
        if (!file_exists($zipPath) || mime_content_type($zipPath) !== 'application/zip') {
            throw new InvalidModuleFileException('Invalid file or not a ZIP file.');
        }

        if (filesize($zipPath) > $this->maxZipSize) {
            throw new InvalidModuleFileException('Module file exceeds the limit of ' . ($this->maxZipSize / 1024 / 1024) . 'MB.');
        }
    }

    protected function extractZip(string $zipPath, string $extractTo): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new InvalidModuleFileException('Cannot open module ZIP file.');
        }

        $totalUncompressedSize = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (substr($stat['name'], -1) !== '/') {
                $totalUncompressedSize += $stat['size'];
            }
        }
        if ($totalUncompressedSize > $this->maxUncompressedSize) {
            $zip->close();
            throw new InvalidModuleFileException('Module size after extraction exceeds the limit of ' . ($this->maxUncompressedSize / 1024 / 1024) . 'MB.');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (str_contains($filename, '..')) {
                $zip->close();
                throw new InvalidModuleStructureException("Unsafe path detected in ZIP file: {$filename}");
            }
        }

        $this->fs->ensureDirectoryExists($extractTo);
        $zip->extractTo($extractTo);
        $zip->close();
    }

    protected function verifyModuleJson(string $jsonPath): array
    {
        if (!$this->fs->exists($jsonPath)) {
            throw new InvalidModuleStructureException('module.json does not exist.');
        }

        $json = json_decode(file_get_contents($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidModuleStructureException('JSON parsing error in module.json.');
        }

        $required = ['name', 'enabled', 'providers', 'signature'];
        foreach ($required as $field) {
            if (!isset($json[$field])) {
                throw new InvalidModuleStructureException("Missing field '$field' in module.json.");
            }
        }

        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]+$/', $json['name'])) {
            throw new InvalidModuleStructureException("Invalid module name '{$json['name']}'.");
        }

        return $json;
    }

    protected function verifySignature(array $meta, string $dir): void
    {
        $hashFile = $dir . '/HASH';
        if (!$this->fs->exists($hashFile)) {
            throw new InvalidModuleSignatureException('HASH file not found to verify signature.');
        }

        $expected = trim(file_get_contents($hashFile));

        $allFiles = collect($this->fs->allFiles($dir))
            ->filter(fn ($file) => !in_array($file->getFilename(), ['HASH']))
            ->sortBy(fn ($f) => $f->getRelativePathname())
            ->values();

        $hashContext = hash_init('sha256');
        foreach ($allFiles as $file) {
            $stream = fopen($file->getRealPath(), 'r');
            if ($stream === false) {
                throw new InvalidModuleSignatureException("Cannot read file to verify: {$file->getRelativePathname()}");
            }
            hash_update_stream($hashContext, $stream);
            fclose($stream);
        }
        $actual = hash_final($hashContext);

        if (!hash_equals($expected, $actual)) {
            throw new InvalidModuleSignatureException('HASH signature does not match, module may have been modified.');
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
                    throw new DangerousCodeDetectedException("Dangerous function '{$func}' detected in file: {$file->getRelativePathname()}");
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
            throw new InvalidModuleStructureException('Module is not allowed to overwrite Core/framework or system files.');
        }

        if (!$this->fs->exists($dir . '/Providers/ModuleServiceProvider.php')) {
            throw new InvalidModuleStructureException('Module must have Providers/ModuleServiceProvider.php');
        }
    }
}
