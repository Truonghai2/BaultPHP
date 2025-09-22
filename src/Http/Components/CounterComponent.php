<?php

namespace App\Http\Components;

use Core\Frontend\Attributes\CallableMethod;
use Core\Frontend\Component;

class CounterComponent extends Component
{
    public int $count = 0;
    public int $initialValue = 0;
    public int $step = 1;

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
