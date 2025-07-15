<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class MakeMigrationCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'ddd:make-migration {module} {name}';
    }

    public function description(): string
    {
        return 'Create a new migration class in the specified module.';
    }

    public function handle(): int
    {
        $module = $this->argument('module');
        $name = $this->argument('name');

        if (!$module || !$name) {
            $this->io->error('Bạn phải truyền tên module và tên migration.');
            $this->io->writeln('Ví dụ: <info>php cli ddd:make-migration User create_users_table</info>');
            return 1;
        }
        $this->fire();
        return 0;
    }

    public function fire(): void
    {
        $module = ucfirst($this->argument('module'));
        $name = $this->argument('name');
        
        // Chuyển tên sang dạng snake_case để đặt tên file
        $name = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

        $modulePath = base_path("Modules/{$module}");
        if (!is_dir($modulePath)) {
            $this->io->error("Module '{$module}' không tồn tại.");
            return;
        }

        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_{$name}.php";
        $path = "{$modulePath}/Infrastructure/Migrations/{$fileName}";
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Chuyển tên snake_case sang StudlyCase để đặt tên class
        $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));

        $stub = <<<PHP
<?php

namespace Modules\\{$module}\\Infrastructure\\Migrations;

use Core\\ORM\\Migration\\Migration;
use PDO;

class {$className} implements Migration
{
    public function up(PDO \$pdo): void
    {
        \$pdo->exec("
            -- SQL to create table goes here
        ");
    }

    public function down(PDO \$pdo): void
    {
        \$pdo->exec("
            -- SQL to drop table goes here
        ");
    }
}
PHP;

        file_put_contents($path, $stub);
        $this->io->success("Migration [{$fileName}] đã được tạo trong module {$module}.");
    }
}