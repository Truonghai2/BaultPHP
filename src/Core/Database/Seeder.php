<?php

namespace Core\Database;

use Core\Application;

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

    public function setContainer(Application $container): static
    {
        $this->container = $container;
        return $this;
    }
}
