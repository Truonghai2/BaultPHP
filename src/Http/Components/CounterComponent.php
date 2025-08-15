<?php

namespace Http\Components;

use Core\Frontend\Attributes\CallableMethod;
use Core\Frontend\Component;

/**
 * A simple counter component to demonstrate the interactive component system.
 *
 * This component manages a count, which can be incremented or decremented.
 * It accepts an `initialValue` and a `step` as properties from the view.
 */
class CounterComponent extends Component
{
    /**
     * The current value of the counter. This is a public property, so it will
     * be automatically included in the component's state and passed to the view.
     */
    public int $count = 0;

    /**
     * The initial value to start the counter at.
     * This property is required to prevent the "Creation of dynamic property"
     * error in PHP 8.2+ when passing props from the view.
     */
    public int $initialValue = 0;

    /**
     * The amount to increment or decrement the counter by.
     */
    public int $step = 1;

    /**
     * The mount method is called when the component is first initialized.
     * It's the perfect place to set up initial state from passed-in properties.
     */
    public function mount(): void
    {
        $this->count = $this->initialValue;
    }

    #[CallableMethod]
    public function increment(): void
    {
        $this->count += $this->step;
    }

    #[CallableMethod]
    public function decrement(): void
    {
        $this->count -= $this->step;
    }
}
