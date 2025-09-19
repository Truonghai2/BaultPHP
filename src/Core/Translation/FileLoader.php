<?php

namespace Core\Translation;

use Core\FileSystem\Filesystem;
use Illuminate\Contracts\Translation\Loader;

/**
 * Custom FileLoader for the translation service.
 * This loader uses the framework's own Core\FileSystem\Filesystem,
 * removing the dependency on illuminate/filesystem.
 */
class FileLoader implements Loader
{
    /**
     * The registered namespace hints.
     *
     * @var array
     */
    protected array $hints = [];

    /**
     * Create a new file loader instance.
     *
     * @param \Core\FileSystem\Filesystem $files
     * @param string $path
     */
    public function __construct(protected Filesystem $files, protected string $path)
    {
    }

    /**
     * Load the messages for the given locale.
     *
     * @param string $locale
     * @param string $group
     * @param string|null $namespace
     * @return array
     */
    public function load($locale, $group, $namespace = null): array
    {
        // Nếu group là ký tự đại diện (*), chúng ta đang tải các file dịch JSON.
        if ($group === '*' && $namespace === '*') {
            return $this->loadJsonPath($this->path, $locale);
        }

        if ($namespace !== null && $namespace !== '*') {
            return $this->loadNamespaced($locale, $group, $namespace);
        }

        return $this->loadPath($this->path, $locale, $group);
    }

    protected function loadNamespaced($locale, $group, $namespace): array
    {
        if (isset($this->hints[$namespace])) {
            return $this->loadPath($this->hints[$namespace], $locale, $group);
        }
        return [];
    }

    protected function loadPath($path, $locale, $group): array
    {
        $fullPath = "{$path}/{$locale}/{$group}.php";
        if ($this->files->exists($fullPath)) {
            return $this->files->getRequire($fullPath);
        }
        return [];
    }

    /**
     * Tải một locale từ một file JSON cho trước.
     *
     * @param  string  $path
     * @param  string  $locale
     * @return array
     */
    protected function loadJsonPath(string $path, string $locale): array
    {
        $fullPath = "{$path}/{$locale}.json";

        if ($this->files->exists($fullPath)) {
            $content = $this->files->get($fullPath);
            $decoded = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Trong ứng dụng thực tế, bạn có thể muốn throw exception cho file JSON không hợp lệ.
                // Hiện tại, chúng ta trả về mảng rỗng để tương thích với cách hoạt động của Laravel.
                return [];
            }

            return $decoded ?? [];
        }

        return [];
    }

    public function addNamespace($namespace, $hint)
    {
        $this->hints[$namespace] = $hint;
    }

    /**
     * Add a new JSON path to the loader.
     *
     * @param  string  $path
     * @return void
     */
    public function addJsonPath($path)
    {
        // This custom loader currently only supports a single language path,
        // so this method is implemented for interface compatibility.
    }

    public function namespaces(): array
    {
        return $this->hints;
    }
}
