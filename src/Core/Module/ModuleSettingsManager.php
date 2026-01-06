<?php

namespace Core\Module;

use Core\Contracts\StatefulService;
use Core\Encryption\Encrypter;
use Psr\SimpleCache\CacheInterface;

/**
 * ModuleSettingsManager
 *
 * Manages module settings with caching, encryption, and validation support.
 */
class ModuleSettingsManager implements StatefulService
{
    private array $cache = [];
    private array $schema = [];

    public function __construct(
        private ?CacheInterface $cacheStore = null,
        private ?Encrypter $encrypter = null,
    ) {
    }

    /**
     * Get a setting value for a module.
     */
    public function get(string $moduleName, string $key, mixed $default = null): mixed
    {
        $cacheKey = $this->getCacheKey($moduleName, $key);

        // Check runtime cache
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Check cache store
        if ($this->cacheStore) {
            $cached = $this->cacheStore->get($cacheKey);
            if ($cached !== null) {
                $this->cache[$cacheKey] = $cached;
                return $cached;
            }
        }

        // Load from database
        $setting = ModuleSettings::forModule($moduleName)
            ->where('key', $key)
            ->first();

        if (!$setting) {
            return $default;
        }

        $value = $setting->getCastedValue();

        // Decrypt if needed
        if ($setting->is_encrypted && $this->encrypter) {
            try {
                $value = $this->encrypter->decrypt($value);
            } catch (\Exception $e) {
                logger()->error("Failed to decrypt setting {$moduleName}.{$key}: " . $e->getMessage());
                return $default;
            }
        }

        // Cache it
        $this->cache[$cacheKey] = $value;
        if ($this->cacheStore) {
            $this->cacheStore->set($cacheKey, $value, 3600);
        }

        return $value;
    }

    /**
     * Set a setting value for a module.
     */
    public function set(string $moduleName, string $key, mixed $value, array $options = []): bool
    {
        $type = $options['type'] ?? $this->inferType($value);
        $isEncrypted = $options['encrypted'] ?? false;

        // Validate against schema if exists
        if (isset($this->schema[$moduleName][$key])) {
            if (!$this->validateValue($value, $this->schema[$moduleName][$key])) {
                throw new \InvalidArgumentException("Invalid value for setting {$moduleName}.{$key}");
            }
        }

        // Encrypt if needed
        if ($isEncrypted && $this->encrypter) {
            $value = $this->encrypter->encrypt($value);
        }

        $setting = ModuleSettings::forModule($moduleName)
            ->where('key', $key)
            ->first();

        if ($setting) {
            $setting->setCastedValue($value);
            $setting->type = $type;
            $setting->is_encrypted = $isEncrypted;
            $setting->save();
        } else {
            $setting = new ModuleSettings();
            $setting->module_name = $moduleName;
            $setting->key = $key;
            $setting->type = $type;
            $setting->is_encrypted = $isEncrypted;
            $setting->description = $options['description'] ?? null;
            $setting->group = $options['group'] ?? 'general';
            $setting->is_public = $options['public'] ?? false;
            $setting->order = $options['order'] ?? 0;
            $setting->setCastedValue($value);
            $setting->save();
        }

        // Clear cache
        $this->clearCache($moduleName, $key);

        return true;
    }

    /**
     * Get all settings for a module.
     */
    public function getAll(string $moduleName, ?string $group = null): array
    {
        $query = ModuleSettings::forModule($moduleName)->orderBy('order');

        if ($group) {
            $query->inGroup($group);
        }

        $settings = $query->get();
        $result = [];

        foreach ($settings as $setting) {
            $value = $setting->getCastedValue();

            if ($setting->is_encrypted && $this->encrypter) {
                try {
                    $value = $this->encrypter->decrypt($value);
                } catch (\Exception $e) {
                    continue;
                }
            }

            $result[$setting->key] = $value;
        }

        return $result;
    }

    /**
     * Get all settings with metadata.
     */
    public function getAllWithMeta(string $moduleName, ?string $group = null): array
    {
        $query = ModuleSettings::forModule($moduleName)->orderBy('group')->orderBy('order');

        if ($group) {
            $query->inGroup($group);
        }

        $settings = $query->get();
        $result = [];

        foreach ($settings as $setting) {
            $value = $setting->getCastedValue();

            if ($setting->is_encrypted && $this->encrypter) {
                try {
                    $value = $this->encrypter->decrypt($value);
                } catch (\Exception $e) {
                    $value = null;
                }
            }

            $result[] = [
                'key' => $setting->key,
                'value' => $value,
                'type' => $setting->type,
                'description' => $setting->description,
                'group' => $setting->group,
                'is_public' => $setting->is_public,
                'is_encrypted' => $setting->is_encrypted,
                'order' => $setting->order,
            ];
        }

        return $result;
    }

    /**
     * Delete a setting.
     */
    public function delete(string $moduleName, string $key): bool
    {
        $deleted = ModuleSettings::forModule($moduleName)
            ->where('key', $key)
            ->delete();

        if ($deleted) {
            $this->clearCache($moduleName, $key);
        }

        return $deleted > 0;
    }

    /**
     * Delete all settings for a module.
     */
    public function deleteAll(string $moduleName): bool
    {
        $deleted = ModuleSettings::forModule($moduleName)->delete();

        if ($deleted) {
            $this->clearCache($moduleName);
        }

        return $deleted > 0;
    }

    /**
     * Check if a setting exists.
     */
    public function has(string $moduleName, string $key): bool
    {
        return ModuleSettings::forModule($moduleName)
            ->where('key', $key)
            ->exists();
    }

    /**
     * Bulk set settings.
     */
    public function setMany(string $moduleName, array $settings, array $options = []): void
    {
        foreach ($settings as $key => $value) {
            $this->set($moduleName, $key, $value, $options);
        }
    }

    /**
     * Register settings schema for validation.
     */
    public function registerSchema(string $moduleName, array $schema): void
    {
        $this->schema[$moduleName] = $schema;
    }

    /**
     * Get settings groups for a module.
     */
    public function getGroups(string $moduleName): array
    {
        return ModuleSettings::forModule($moduleName)
            ->select('group')
            ->distinct()
            ->whereNotNull('group')
            ->pluck('group')
            ->toArray();
    }

    /**
     * Clear cache for a module or specific setting.
     */
    public function clearCache(string $moduleName, ?string $key = null): void
    {
        if ($key) {
            $cacheKey = $this->getCacheKey($moduleName, $key);
            unset($this->cache[$cacheKey]);
            if ($this->cacheStore) {
                $this->cacheStore->delete($cacheKey);
            }
        } else {
            // Clear all settings for this module
            $pattern = "module_setting:{$moduleName}:*";
            foreach ($this->cache as $cacheKey => $value) {
                if (str_starts_with($cacheKey, "module_setting:{$moduleName}:")) {
                    unset($this->cache[$cacheKey]);
                }
            }
            // Note: Cache store doesn't have a deleteByPattern method in PSR-16
            // You might need to implement this based on your cache driver
        }
    }

    /**
     * Reset state (for StatefulService).
     */
    public function resetState(): void
    {
        $this->cache = [];
    }

    /**
     * Get cache key.
     */
    private function getCacheKey(string $moduleName, string $key): string
    {
        return "module_setting:{$moduleName}:{$key}";
    }

    /**
     * Infer type from value.
     */
    private function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_array($value) => 'array',
            default => 'string',
        };
    }

    /**
     * Validate value against schema.
     */
    private function validateValue(mixed $value, array $schema): bool
    {
        // Type validation
        if (isset($schema['type'])) {
            $expectedType = $schema['type'];
            $actualType = $this->inferType($value);

            if ($expectedType !== $actualType) {
                return false;
            }
        }

        // Required validation
        if (isset($schema['required']) && $schema['required'] && $value === null) {
            return false;
        }

        // Min/Max validation for numbers
        if (is_numeric($value)) {
            if (isset($schema['min']) && $value < $schema['min']) {
                return false;
            }
            if (isset($schema['max']) && $value > $schema['max']) {
                return false;
            }
        }

        // Enum validation
        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            return false;
        }

        // Pattern validation for strings
        if (is_string($value) && isset($schema['pattern'])) {
            if (!preg_match($schema['pattern'], $value)) {
                return false;
            }
        }

        return true;
    }
}
