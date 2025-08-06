<?php

namespace Core\ORM\Events;

use Core\ORM\Model;

trait Observable
{
    /**
     * The registered model observers.
     * @var array
     */
    protected static array $observers = [];

    /**
     * Register an observer with the Model.
     *
     * @param  string|object  $class
     * @return void
     */
    public static function observe($class): void
    {
        $className = is_object($class) ? get_class($class) : $class;
        static::$observers[static::class][] = $className;
    }

    /**
     * Fire the given event for the model.
     *
     * @param  string  $event
     * @return bool
     */
    protected function fireModelEvent(string $event): bool
    {
        if (!isset(static::$observers[static::class])) {
            return true;
        }

        foreach (static::$observers[static::class] as $observerClass) {
            $observer = app($observerClass); // Resolve observer from container

            if (method_exists($observer, $event) && $observer->{$event}($this) === false) {
                return false;
            }
        }

        return true;
    }
}
