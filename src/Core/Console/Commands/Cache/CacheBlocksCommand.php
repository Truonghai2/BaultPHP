<?php

namespace Core\Console\Commands\Cache;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\Cms\Domain\Services\BlockRegistry;

class CacheBlocksCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    protected string $signature = 'cache:blocks';
    protected string $description = 'Cache the list of registered CMS blocks for faster loading.';

    public function signature(): string
    {
        return $this->signature;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function handle(): int
    {
        $cachePath = $this->getCacheFilePath();

        // Resolve BlockRegistry từ container
        /** @var BlockRegistry $blockRegistry */
        $blockRegistry = $this->app->make(BlockRegistry::class);

        // Lấy danh sách các block
        $blocks = $blockRegistry->getBlocks();

        // Lưu danh sách vào file cache
        $this->writeCacheFile($cachePath, $blocks);

        $this->info("CMS blocks cached successfully to: {$cachePath}");

        return 0; // Mã trả về thành công
    }

    /**
     * Lấy đường dẫn đến file cache.
     */
    protected function getCacheFilePath(): string
    {
        // Sử dụng helper function `storage_path()`
        return storage_path('cache/cms_blocks.php');
    }

    /**
     * Ghi dữ liệu vào file cache.
     */
    protected function writeCacheFile(string $path, array $data): void
    {
        $content = "<?php\n\nreturn " . var_export($data, true) . ";\n";
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $content);
    }
}
