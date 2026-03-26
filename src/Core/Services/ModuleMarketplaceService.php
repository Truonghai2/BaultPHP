<?php

declare(strict_types=1);

namespace Core\Services;

use Psr\Log\LoggerInterface;

/**
 * Fetches module catalog from a marketplace registry and downloads packages by URL.
 *
 * Registry JSON shape: { "modules": [ { "id", "name", "version", "description", "download_url", "package?", "permissions"?, "min_core_version"? } ] }
 */
class ModuleMarketplaceService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Fetch catalog from config registry URL.
     *
     * @return list<array{id: string, name: string, version: string, description: string, download_url: string, package?: string, permissions?: array, min_core_version?: string}>
     */
    public function getCatalog(): array
    {
        $url = config('marketplace.registry_url');
        if (!$url || !config('marketplace.enabled', true)) {
            return [];
        }
        $timeout = config('marketplace.registry_timeout', 15);
        $headers = config('marketplace.registry_headers', []);

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'header' => $this->buildHeaderLines($headers),
                ],
            ]);
            $body = file_get_contents($url, false, $context);
            if ($body === false) {
                $this->logger->warning('Module marketplace: empty or failed response', ['url' => $url]);
                return [];
            }
            $data = json_decode($body, true);
            if (!is_array($data)) {
                return [];
            }
            $modules = $data['modules'] ?? $data['packages'] ?? [];
            if (!is_array($modules)) {
                return [];
            }
            $out = [];
            foreach ($modules as $m) {
                if (!is_array($m) || empty($m['name'])) {
                    continue;
                }
                $out[] = [
                    'id' => $m['id'] ?? $m['name'],
                    'name' => (string) $m['name'],
                    'version' => (string) ($m['version'] ?? '1.0.0'),
                    'description' => (string) ($m['description'] ?? ''),
                    'download_url' => (string) ($m['download_url'] ?? ''),
                    'package' => isset($m['package']) ? (string) $m['package'] : null,
                    'permissions' => isset($m['permissions']) && is_array($m['permissions']) ? $m['permissions'] : null,
                    'min_core_version' => isset($m['min_core_version']) ? (string) $m['min_core_version'] : null,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            $this->logger->error('Module marketplace: failed to fetch catalog', ['url' => $url, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Download a ZIP from URL to a temporary file and return the path.
     * Caller is responsible for deleting the file after use.
     *
     * @throws \RuntimeException
     */
    public function downloadToTemp(string $url): string
    {
        $timeout = config('marketplace.download_timeout', 120);
        $headers = config('marketplace.registry_headers', []);
        $tmpFile = storage_path('app/tmp_marketplace_' . bin2hex(random_bytes(8)) . '.zip');

        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'header' => $this->buildHeaderLines($headers),
            ],
        ]);

        $content = file_get_contents($url, false, $context);
        if ($content === false) {
            throw new \RuntimeException('Failed to download module from URL: ' . $url);
        }
        if (file_put_contents($tmpFile, $content) === false) {
            throw new \RuntimeException('Failed to write temporary file for downloaded module.');
        }
        return $tmpFile;
    }

    private function buildHeaderLines(array $headers): string
    {
        $lines = [];
        foreach ($headers as $k => $v) {
            if ($v !== null && $v !== '') {
                $lines[] = "{$k}: {$v}";
            }
        }
        return implode("\r\n", $lines);
    }
}
