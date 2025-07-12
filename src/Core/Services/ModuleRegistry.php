<?php 

namespace Core\Services;

use Core\ORM\Connection;

class ModuleRegistry
{
    protected string $path;

    public function __construct(string $path = null)
    {
        $this->path = $path ?? base_path('Modules');
    }

    public function sync(): void
    {
        $modules = glob($this->path . '/*', GLOB_ONLYDIR);
        $pdo = Connection::get();

        foreach ($modules as $dir) {
            $name = basename($dir);
            $json = $dir . '/module.json';

            if (!file_exists($json)) {
                $this->markError($pdo, $name, "Không tìm thấy module.json");
                continue;
            }

            $meta = json_decode(file_get_contents($json), true);

            // Nếu chưa có trong DB → thêm
            $stmt = $pdo->prepare("SELECT id FROM modules WHERE name = ?");
            $stmt->execute([$name]);
            $exists = $stmt->fetch();

            if (!$exists) {
                $stmt = $pdo->prepare("INSERT INTO modules (name, version, enabled, status, description, created_at, updated_at)
                    VALUES (?, ?, 0, 'new', ?, NOW(), NOW())");

                $stmt->execute([
                    $meta['name'] ?? $name,
                    $meta['version'] ?? '1.0.0',
                    $meta['description'] ?? ''
                ]);
            }
        }
    }

    protected function markError($pdo, string $name, string $message): void
    {
        $stmt = $pdo->prepare("INSERT IGNORE INTO modules (name, version, status, description, created_at, updated_at)
            VALUES (?, '', 'error', ?, NOW(), NOW())");
        $stmt->execute([$name, $message]);
    }
}