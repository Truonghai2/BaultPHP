<?php

namespace Http\Components;

use Core\Frontend\Attributes\CallableMethod;
use Core\Frontend\Component;

class Counter extends Component
{
    public int $count = 0;

    #[CallableMethod]
    public function increment(): void
    {
        $this->count++;
    }

    public function render()
    {
        return view('components.counter');
    }
}
