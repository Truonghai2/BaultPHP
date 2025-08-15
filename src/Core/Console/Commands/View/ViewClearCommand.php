<?php

namespace Core\Console\Commands\View;

use Core\Config;
use Core\Console\Contracts\BaseCommand;
use Core\Filesystem\Filesystem;
use InvalidArgumentException;

class ViewClearCommand extends BaseCommand
{
    protected string $cachePath;

    /**
     * Tạo một instance command mới.
     * Framework sẽ tự động inject các dependency (Filesystem, Config) vào đây.
     */
    public function __construct(protected Filesystem $files, Config $config)
    {
        parent::__construct();

        $path = $config->get('view.compiled');
        if (empty($path)) {
            throw new InvalidArgumentException('Đường dẫn cache cho view chưa được cấu hình trong config/view.php.');
        }
        $this->cachePath = $path;
    }

    public function signature(): string
    {
        return 'view:clear';
    }

    public function description(): string
    {
        return 'Xóa tất cả các file view đã được biên dịch';
    }

    public function handle(): int
    {
        if (!$this->files->isDirectory($this->cachePath)) {
            $this->io->info('Thư mục view đã biên dịch không tồn tại. Không có gì để xóa.');
            return self::SUCCESS;
        }

        $files = glob($this->cachePath . '/*');
        $deletedCount = 0;

        foreach ($files as $file) {
            // Giữ lại file .gitignore để thư mục không bị xóa khỏi Git
            if (basename($file) !== '.gitignore') {
                if ($this->files->delete($file)) {
                    $deletedCount++;
                }
            }
        }

        $this->io->success("Đã xóa thành công {$deletedCount} file view đã được biên dịch.");

        return self::SUCCESS;
    }
}
