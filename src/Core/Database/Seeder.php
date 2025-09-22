<?php

namespace Core\Database;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\ORM\Connection;

/**
 * Seeder is an abstract class that provides a base for database seeders.
 * It allows for the execution of seeders and setting the application container.
 */
abstract class Seeder
{
    /**
     * The container instance.
     *
     * @var \Core\Application
     */
    protected Application $container;

    /**
     * The console command instance.
     *
     * @var \Core\Console\Contracts\BaseCommand|null
     */
    protected ?BaseCommand $command = null;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    abstract public function run(): void;

    /**
     * Seed the given seeder classes.
     *
     * @param  array|string  $class
     * @return $this
     */
    public function call(array|string $class): static
    {
        $classes = is_array($class) ? $class : [$class];

        foreach ($classes as $seederClass) {
            /** @var Seeder $seeder */
            $seeder = $this->container->make($seederClass);
            $seeder->setContainer($this->container);
            if ($this->command) {
                $seeder->setCommand($this->command);
            }
            $seeder->run();
        }

        return $this;
    }

    /**
     * Set the application container for the seeder.
     *
     * @param Application $container
     * @return $this
     */
    public function setContainer(Application $container): static
    {
        $this->container = $container;
        return $this;
    }

    /**
     * Set the console command instance.
     *
     * @param \Core\Console\Contracts\BaseCommand $command
     * @return $this
     */
    public function setCommand(BaseCommand $command): static
    {
        $this->command = $command;
        return $this;
    }

    /**
     * Truncates a list of tables.
     *
     * @param array $tables
     * @return void
     */
    protected function truncateTables(array $tables): void
    {
        if (!isset($this->container)) {
            return;
        }

        /** @var \PDO $pdo */
        $pdo = $this->container->make(Connection::class)->connection();

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
        foreach ($tables as $table) {
            $pdo->exec("TRUNCATE TABLE {$table}");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
    }
}
