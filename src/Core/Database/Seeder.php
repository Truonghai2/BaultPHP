<?php

namespace Core\Database;

use Core\Application;

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
            $seeder = $this->container->make($seederClass);
            $seeder->setContainer($this->container);
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
}
