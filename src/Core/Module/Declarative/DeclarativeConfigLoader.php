<?php

declare(strict_types=1);

namespace Core\Module\Declarative;

use Core\Application;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads declarative manifest (manifest.yaml or manifest.json) per module.
 *
 * Returns ModuleDeclarativeConfig for each enabled module that has a manifest.
 */
final class DeclarativeConfigLoader
{
    private const MANIFEST_YAML = 'manifest.yaml';
    private const MANIFEST_JSON = 'manifest.json';

    public function __construct(
        private readonly Application $app,
    ) {
    }

    /**
     * Load config for a single module by name.
     *
     * @return ModuleDeclarativeConfig|null null if no manifest file or invalid
     */
    public function loadForModule(string $moduleName): ?ModuleDeclarativeConfig
    {
        $basePath = $this->app->bound(\Core\Module\ModulePathResolver::class)
            ? $this->app->make(\Core\Module\ModulePathResolver::class)->pathFor($moduleName)
            : $this->app->basePath('Modules/' . $moduleName);
        $data = $this->readManifestFile($basePath);
        if ($data === null || !is_array($data)) {
            return null;
        }
        return $this->hydrate($moduleName, $data);
    }

    /**
     * Load configs for all enabled modules that have a manifest.
     *
     * @return list<ModuleDeclarativeConfig>
     */
    public function loadAll(): array
    {
        $configs = [];
        foreach ($this->getEnabledModuleNames() as $name) {
            $config = $this->loadForModule($name);
            if ($config !== null) {
                $configs[] = $config;
            }
        }
        return $configs;
    }

    /**
     * Read manifest from manifest.yaml or manifest.json.
     *
     * @return array<string, mixed>|null
     */
    private function readManifestFile(string $modulePath): ?array
    {
        $yamlPath = $modulePath . '/' . self::MANIFEST_YAML;
        if (file_exists($yamlPath)) {
            $content = file_get_contents($yamlPath);
            if ($content !== false) {
                try {
                    return Yaml::parse($content);
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        $jsonPath = $modulePath . '/' . self::MANIFEST_JSON;
        if (file_exists($jsonPath)) {
            $content = file_get_contents($jsonPath);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                return is_array($decoded) ? $decoded : null;
            }
        }

        return null;
    }

    private function hydrate(string $moduleName, array $data): ModuleDeclarativeConfig
    {
        $namespace = 'Modules\\' . $moduleName;
        $routes = $this->normaliseRoutes($data['routes'] ?? []);
        $permissions = $this->normaliseStrings($data['permissions'] ?? []);
        $menuAdmin = $this->normaliseMenuItems($data['menu']['admin'] ?? $data['menu_admin'] ?? []);
        $menuFrontend = $this->normaliseMenuItems($data['menu']['frontend'] ?? $data['menu_frontend'] ?? []);

        return new ModuleDeclarativeConfig(
            moduleName: $moduleName,
            moduleNamespace: $namespace,
            routes: $routes,
            permissions: $permissions,
            menuAdmin: $menuAdmin,
            menuFrontend: $menuFrontend,
        );
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array{method: string, uri: string, action: string, name?: string, middleware?: list<string>, group?: string}>
     */
    private function normaliseRoutes(array $items): array
    {
        $out = [];
        foreach ($items as $r) {
            if (!is_array($r) || empty($r['uri'])) {
                continue;
            }
            $method = strtoupper((string) ($r['method'] ?? 'GET'));
            $uri = '/' . trim((string) $r['uri'], '/');
            if ($uri !== '/') {
                $uri = rtrim($uri, '/');
            }
            $action = (string) ($r['action'] ?? $r['controller'] ?? '');
            if ($action === '') {
                continue;
            }
            $entry = [
                'method'  => $method,
                'uri'     => $uri,
                'action'  => $action,
            ];
            if (!empty($r['name'])) {
                $entry['name'] = (string) $r['name'];
            }
            if (isset($r['middleware'])) {
                $entry['middleware'] = $this->normaliseStrings(is_array($r['middleware']) ? $r['middleware'] : [$r['middleware']]);
            }
            if (!empty($r['group'])) {
                $entry['group'] = (string) $r['group'];
            }
            $out[] = $entry;
        }
        return $out;
    }

    /**
     * @param array<int, mixed> $items
     * @return list<array{label: string, url: string, icon?: string, order?: int, children?: array}>
     */
    private function normaliseMenuItems(array $items): array
    {
        $out = [];
        foreach ($items as $m) {
            if (!is_array($m) || empty($m['label']) || empty($m['url'])) {
                continue;
            }
            $entry = [
                'label' => (string) $m['label'],
                'url'   => (string) $m['url'],
            ];
            if (isset($m['icon'])) {
                $entry['icon'] = (string) $m['icon'];
            }
            if (isset($m['order'])) {
                $entry['order'] = (int) $m['order'];
            }
            if (!empty($m['children']) && is_array($m['children'])) {
                $entry['children'] = $this->normaliseMenuItems($m['children']);
            }
            $out[] = $entry;
        }
        return $out;
    }

    /**
     * @return list<string>
     */
    private function normaliseStrings(array $arr): array
    {
        $out = [];
        foreach ($arr as $v) {
            if (is_string($v) && $v !== '') {
                $out[] = $v;
            }
        }
        return $out;
    }

    /**
     * @return list<string>
     */
    private function getEnabledModuleNames(): array
    {
        $cachedPath = $this->app->basePath('bootstrap/cache/modules.php');
        if (file_exists($cachedPath)) {
            $list = require $cachedPath;
            return is_array($list) ? $list : [];
        }
        $names = [];
        foreach (glob($this->app->basePath('Modules/*/module.json')) ?: [] as $path) {
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data) && !empty($data['name']) && ($data['enabled'] ?? false) === true) {
                $names[] = $data['name'];
            }
        }
        if ($this->app->bound(\Core\Module\Composer\ComposerModuleDiscovery::class)) {
            foreach ($this->app->make(\Core\Module\Composer\ComposerModuleDiscovery::class)->getManifests() as $manifest) {
                $names[] = $manifest->name;
            }
        }
        return array_values(array_unique($names));
    }
}
