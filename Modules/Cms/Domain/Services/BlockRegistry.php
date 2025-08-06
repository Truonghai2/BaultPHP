<?php

namespace Modules\Cms\Domain\Services;

use Core\Cache\CacheManager;

class BlockRegistry
{
    private const CACHE_KEY = 'cms:available_blocks';
    private const CACHE_TTL = 3600; // Cache trong 1 giờ

    public function __construct(private readonly CacheManager $cache)
    {
    }

    /**
     * Get all available block components, using cache for performance.
     *
     * @return array<string, array<string, string>>
     */
    public function getBlocks(): array
    {
        $cachedBlocks = $this->cache->store()->get(self::CACHE_KEY);

        if ($cachedBlocks) {
            return json_decode($cachedBlocks, true);
        }

        // Logic để tìm các block.
        // Trong một ứng dụng thực tế, logic này có thể quét các thư mục,
        // đọc file config, hoặc dùng reflection để tìm các class component.
        // Ở đây, chúng ta sẽ giả định một danh sách tĩnh.
        $blocks = $this->discoverBlocks();

        $this->cache->put(self::CACHE_KEY, json_encode($blocks), self::CACHE_TTL);

        return $blocks;
    }

    /**
     * Placeholder for block discovery logic.
     *
     * @return array<string, array<string, string>>
     */
    private function discoverBlocks(): array
    {
        // Ví dụ:
        return [
            'Modules\Cms\Blocks\TextBlock' => ['name' => 'Text Block', 'description' => 'A simple block for text content.'],
            'Modules\Cms\Blocks\ImageBlock' => ['name' => 'Image Block', 'description' => 'A block for displaying an image.'],
        ];
    }
}
