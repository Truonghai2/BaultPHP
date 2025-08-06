<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;
use Core\ORM\Model;
use Core\Support\Collection;
use Psy\Configuration;
use Psy\Shell;

/**
 * Class TinkerCommand
 * Cung cấp một môi trường REPL (Read-Eval-Print Loop) tương tác với ứng dụng.
 * Tương tự như lệnh `tinker` của Laravel.
 */
class TinkerCommand extends BaseCommand
{
    /**
     * Chữ ký của lệnh, được sử dụng để gọi lệnh từ terminal.
     *
     * @var string
     */
    protected string $signature = 'tinker';

    /**
     * Mô tả của lệnh, sẽ được hiển thị khi chạy `php cli list`.
     *
     * @var string
     */
    protected string $description = 'Interact with your application';

    public function signature(): string
    {
        return $this->signature;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * Logic chính của lệnh sẽ được thực thi tại đây.
     */
    public function handle(): int
    {
        $this->comment('Starting BaultPHP Tinker Shell...');

        $this->app->boot();

        // Khởi tạo cấu hình trước để tránh các vấn đề về thứ tự khởi tạo trong PsySH.
        $config = new Configuration([
            'updateCheck' => 'never',
            // Truyền instance của Application vào scope của shell
            // để có thể dùng $app->make(...)
            'scopeVariables' => ['app' => $this->app],
        ]);

        // Thêm casters một cách riêng biệt sau khi đối tượng đã được khởi tạo hoàn toàn.
        // Điều này ngăn chặn lỗi "Typed property $hasPcntl must not be accessed before initialization".
        $config->addCasters([
            Model::class      => 'Core\Console\Tinker\Caster::castModel',
            Collection::class => 'Core\Console\Tinker\Caster::castCollection',
        ]);

        $shell = new Shell($config);
        $shell->run(); // Vòng lặp REPL sẽ chạy ở đây

        return self::SUCCESS;
    }
}
