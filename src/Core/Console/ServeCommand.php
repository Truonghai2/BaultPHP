<?php

namespace Core\Console;

use Core\Console\Contracts\CommandInterface;

class ServeCommand implements CommandInterface
{
    public function signature(): string
    {
        return 'serve';
    }

    public function handle(array $arguments): void
    {
        $host = $arguments[0] ?? '127.0.0.1';
        $port = $arguments[1] ?? '8000';
        $docRoot = base_path('public');

        if (!is_dir($docRoot)) {
            echo "Không tìm thấy thư mục 'public'.\n";
            return;
        }

        echo "Đang chạy server tại http://$host:$port ...\n";
        echo "Nhấn Ctrl+C để dừng.\n";

        passthru("php -S {$host}:{$port} -t {$docRoot}");
    }
}
