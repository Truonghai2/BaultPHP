<?php

declare(strict_types=1);

namespace Core\Module;

use Core\Exceptions\Module\InvalidModuleStructureException;

/**
 * Value object representing a parsed and validated module.json manifest.
 *
 * Schema version 2 adds: api_version, min_core_version, capabilities, permissions, hooks.
 * Lazy loading (2.4): activate, route_prefix / route_prefixes.
 * All new fields are optional and backward compatible with v1 manifests.
 */
final class ModuleManifest
{
    public const SCHEMA_VERSION = 2;
    public const MIN_REQUIRED_FIELDS = ['name', 'enabled', 'providers'];

    /** Load at app bootstrap (default). */
    public const ACTIVATE_ON_BOOT = 'on_boot';
    /** Load when a request path matches route_prefix(es). */
    public const ACTIVATE_ON_REQUEST = 'on_request';
    /** Load on first use (e.g. first extension point that needs this module). */
    public const ACTIVATE_ON_FIRST_USE = 'on_first_use';

    public function __construct(
        /** Module name, e.g. "User" */
        public readonly string $name,
        /** Semver string, e.g. "1.0.0" */
        public readonly string $version,
        /** Whether the module should be loaded */
        public readonly bool $enabled,
        /** List of service provider FQCNs */
        public readonly array $providers,
        /** Human-readable description */
        public readonly string $description,
        /** Short alias, e.g. "user" */
        public readonly string $alias,
        /** Manifest schema version (1 = legacy, 2 = current) */
        public readonly int $apiVersion,
        /** Minimum BaultFrame core version required, e.g. "1.0.0" */
        public readonly string $minCoreVersion,
        /**
         * Capabilities this module provides, e.g. ["auth", "cms.blocks"].
         * Other modules can depend on these instead of concrete class names.
         */
        public readonly array $capabilities,
        /**
         * System resources this module needs, e.g. ["cache:read", "storage:write", "events:subscribe"].
         * Used for permission checks and future sandboxing.
         */
        public readonly array $permissions,
        /**
         * FQCN of a class implementing ModuleLifecycle.
         * Called during install / enable / disable / uninstall.
         */
        public readonly ?string $hookClass,
        /** Composer package dependencies, e.g. {"vendor/pkg": "^1.0"} */
        public readonly array $require,
        /** Optional SHA-256 content signature */
        public readonly ?string $signature,
        /**
         * When to load this module: on_boot (default), on_request, on_first_use.
         * @see self::ACTIVATE_ON_BOOT, self::ACTIVATE_ON_REQUEST, self::ACTIVATE_ON_FIRST_USE
         */
        public readonly string $activate,
        /**
         * Single path prefix that triggers loading when activate=on_request, e.g. "/admin/users".
         * If set, route_prefixes is ignored for discovery.
         */
        public readonly ?string $routePrefix,
        /**
         * Path prefixes that trigger loading when activate=on_request.
         * Used when route_prefix is not set. Longest match wins.
         */
        public readonly array $routePrefixes,
    ) {
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Parse a module.json file from disk.
     *
     * @throws InvalidModuleStructureException
     */
    public static function fromPath(string $jsonPath): self
    {
        if (!file_exists($jsonPath)) {
            throw new InvalidModuleStructureException("module.json not found at: {$jsonPath}");
        }

        $content = file_get_contents($jsonPath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidModuleStructureException('JSON parse error in module.json: ' . json_last_error_msg());
        }

        return self::fromArray($data);
    }

    /**
     * Build a manifest from a Composer package (composer.json with extra.bault.module or type bault-module).
     *
     * @param string $composerJsonPath Path to vendor/vendor/package/composer.json
     * @param bool   $enabled          Whether this composer module is enabled (e.g. not in modules.composer_disabled)
     * @throws InvalidModuleStructureException
     */
    public static function fromComposerPackage(string $composerJsonPath, bool $enabled = true): self
    {
        if (!is_file($composerJsonPath)) {
            throw new InvalidModuleStructureException("composer.json not found at: {$composerJsonPath}");
        }
        $json = json_decode((string) file_get_contents($composerJsonPath), true);
        if (!is_array($json)) {
            throw new InvalidModuleStructureException('Invalid composer.json');
        }
        $extra = $json['extra'] ?? [];
        $bault = $extra['bault']['module'] ?? $extra['bault.module'] ?? null;
        if (!is_array($bault)) {
            if (($json['type'] ?? '') !== 'bault-module') {
                throw new InvalidModuleStructureException('composer.json must have extra.bault.module or type "bault-module"');
            }
            $name = self::inferModuleNameFromPackage($json['name'] ?? '');
            $bault = ['name' => $name, 'providers' => []];
        }
        if (empty($bault['name']) || !isset($bault['providers']) || !is_array($bault['providers'])) {
            throw new InvalidModuleStructureException('extra.bault.module must have "name" and "providers" (array)');
        }
        $version = $json['version'] ?? '1.0.0';
        if (is_string($version) && preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9.]+)?$/', $version)) {
            // keep as-is
        } else {
            $version = '1.0.0';
        }
        $data = [
            'name'               => $bault['name'],
            'version'            => $version,
            'enabled'            => $enabled,
            'providers'          => $bault['providers'],
            'description'        => $bault['description'] ?? $json['description'] ?? '',
            'alias'              => $bault['alias'] ?? strtolower($bault['name']),
            'api_version'        => $bault['api_version'] ?? 2,
            'min_core_version'   => $bault['min_core_version'] ?? '1.0.0',
            'capabilities'       => $bault['capabilities'] ?? [],
            'permissions'        => $bault['permissions'] ?? [],
            'hooks'              => $bault['hooks'] ?? null,
            'require'            => $bault['require'] ?? [],
            'signature'          => $bault['signature'] ?? null,
            'activate'           => $bault['activate'] ?? self::ACTIVATE_ON_BOOT,
            'route_prefix'       => $bault['route_prefix'] ?? null,
            'route_prefixes'     => $bault['route_prefixes'] ?? [],
        ];
        return self::fromArray($data);
    }

    private static function inferModuleNameFromPackage(string $packageName): string
    {
        $parts = explode('/', $packageName);
        $last = end($parts);
        if ($last === false || $last === '') {
            return 'Module';
        }
        $last = preg_replace('/^bault-module-/', '', $last);
        return str_replace('-', '', ucwords($last, '-'));
    }

    /**
     * Build from a raw associative array (already decoded JSON).
     *
     * @throws InvalidModuleStructureException
     */
    public static function fromArray(array $data): self
    {
        // Required fields
        foreach (self::MIN_REQUIRED_FIELDS as $field) {
            if (!isset($data[$field])) {
                throw new InvalidModuleStructureException("Missing required field '{$field}' in module.json.");
            }
        }

        // name: letters, numbers, underscores; must start with letter
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]+$/', $data['name'])) {
            throw new InvalidModuleStructureException(
                "Invalid module name '{$data['name']}'. Must start with a letter and contain only letters, numbers, underscores.",
            );
        }

        if (!is_array($data['providers'])) {
            throw new InvalidModuleStructureException("Field 'providers' must be an array in module.json.");
        }

        if (!is_bool($data['enabled'])) {
            throw new InvalidModuleStructureException("Field 'enabled' must be a boolean in module.json.");
        }

        $version = $data['version'] ?? '1.0.0';
        if (!preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9.]+)?$/', $version)) {
            throw new InvalidModuleStructureException("Invalid version format '{$version}'. Expected: x.y.z or x.y.z-suffix");
        }

        $require = $data['require'] ?? [];
        if (!is_array($require)) {
            throw new InvalidModuleStructureException("Field 'require' must be an array in module.json.");
        }

        // Validate permissions format: "<resource>:<action>" or free string
        $permissions = $data['permissions'] ?? [];
        if (!is_array($permissions)) {
            throw new InvalidModuleStructureException("Field 'permissions' must be an array in module.json.");
        }

        // Validate capabilities
        $capabilities = $data['capabilities'] ?? [];
        if (!is_array($capabilities)) {
            throw new InvalidModuleStructureException("Field 'capabilities' must be an array in module.json.");
        }

        $apiVersion = (int) ($data['api_version'] ?? 1);
        $minCoreVersion = $data['min_core_version'] ?? '1.0.0';

        // hookClass must be a string if provided
        $hookClass = $data['hooks'] ?? null;
        if ($hookClass !== null && !is_string($hookClass)) {
            throw new InvalidModuleStructureException("Field 'hooks' must be a class name string in module.json.");
        }

        $activate = $data['activate'] ?? self::ACTIVATE_ON_BOOT;
        $validActivate = [self::ACTIVATE_ON_BOOT, self::ACTIVATE_ON_REQUEST, self::ACTIVATE_ON_FIRST_USE];
        if (!is_string($activate) || !in_array($activate, $validActivate, true)) {
            throw new InvalidModuleStructureException(
                "Field 'activate' must be one of: " . implode(', ', $validActivate) . ".",
            );
        }

        $routePrefix = isset($data['route_prefix']) && $data['route_prefix'] !== '' ? (string) $data['route_prefix'] : null;
        $routePrefixes = $data['route_prefixes'] ?? [];
        if (!is_array($routePrefixes)) {
            throw new InvalidModuleStructureException("Field 'route_prefixes' must be an array in module.json.");
        }
        $routePrefixes = array_values(array_filter(array_map('strval', $routePrefixes)));

        return new self(
            name: $data['name'],
            version: $version,
            enabled: (bool) $data['enabled'],
            providers: $data['providers'],
            description: $data['description'] ?? '',
            alias: $data['alias'] ?? strtolower($data['name']),
            apiVersion: $apiVersion,
            minCoreVersion: $minCoreVersion,
            capabilities: $capabilities,
            permissions: $permissions,
            hookClass: $hookClass ?: null,
            require: $require,
            signature: $data['signature'] ?? null,
            activate: $activate,
            routePrefix: $routePrefix,
            routePrefixes: $routePrefixes,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Serialize back to an associative array suitable for json_encode.
     */
    public function toArray(): array
    {
        $out = array_filter([
            'name'             => $this->name,
            'alias'            => $this->alias,
            'version'          => $this->version,
            'description'      => $this->description,
            'enabled'          => $this->enabled,
            'api_version'      => $this->apiVersion,
            'min_core_version' => $this->minCoreVersion,
            'capabilities'     => $this->capabilities ?: null,
            'permissions'      => $this->permissions ?: null,
            'hooks'            => $this->hookClass,
            'providers'        => $this->providers,
            'require'          => $this->require ?: null,
            'signature'        => $this->signature,
        ], fn ($v) => $v !== null);
        if ($this->activate !== self::ACTIVATE_ON_BOOT) {
            $out['activate'] = $this->activate;
        }
        if ($this->routePrefix !== null) {
            $out['route_prefix'] = $this->routePrefix;
        }
        if ($this->routePrefixes !== []) {
            $out['route_prefixes'] = $this->routePrefixes;
        }
        return $out;
    }

    /**
     * Persist manifest back to its module.json file.
     */
    public function save(string $jsonPath): void
    {
        file_put_contents($jsonPath, json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Return a new manifest with enabled flag changed.
     */
    public function withEnabled(bool $enabled): self
    {
        return new self(
            name: $this->name,
            version: $this->version,
            enabled: $enabled,
            providers: $this->providers,
            description: $this->description,
            alias: $this->alias,
            apiVersion: $this->apiVersion,
            minCoreVersion: $this->minCoreVersion,
            capabilities: $this->capabilities,
            permissions: $this->permissions,
            hookClass: $this->hookClass,
            require: $this->require,
            signature: $this->signature,
            activate: $this->activate,
            routePrefix: $this->routePrefix,
            routePrefixes: $this->routePrefixes,
        );
    }

    public function hasHooks(): bool
    {
        return $this->hookClass !== null && class_exists($this->hookClass);
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function requiresPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    /**
     * Check if the manifest API version is supported by the given core version.
     */
    public function isCompatibleWith(string $coreVersion): bool
    {
        return version_compare($coreVersion, $this->minCoreVersion, '>=');
    }

    /** Whether this module is loaded only when a matching request is handled (on_request). */
    public function isActivateOnRequest(): bool
    {
        return $this->activate === self::ACTIVATE_ON_REQUEST;
    }

    /** Whether this module is loaded at app bootstrap. */
    public function isActivateOnBoot(): bool
    {
        return $this->activate === self::ACTIVATE_ON_BOOT;
    }

    /**
     * Path prefixes that trigger loading when activate=on_request.
     * Normalized: no trailing slash, with leading slash.
     *
     * @return list<string>
     */
    public function getPathPrefixes(): array
    {
        if ($this->routePrefix !== null) {
            return [rtrim($this->routePrefix, '/') ?: '/'];
        }
        $out = [];
        foreach ($this->routePrefixes as $p) {
            $p = '/' . trim((string) $p, '/');
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return array_values(array_unique($out));
    }
}
