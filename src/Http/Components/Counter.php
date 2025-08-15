<?php

namespace Http\Components;

use Core\Frontend\Attributes\CallableMethod;
use Core\Frontend\Component;

/**
 * Một component Counter đơn giản để minh họa.
 */
class Counter extends Component
{
    /**
     * State (trạng thái) của component.
     * Các thuộc tính public sẽ tự động được gửi đến client.
     */
    public int $count = 0;

    /**
     * Một action có thể được gọi từ client thông qua wire:click="increment".
     * Attribute #[CallableMethod] là bắt buộc để đảm bảo an toàn.
     */
    #[CallableMethod]
    public function increment(): void
    {
        $this->count++;
    }

    public function render()
    {
        // Trả về một instance của view.
        // Tất cả các thuộc tính public của component (như $count) sẽ tự động có sẵn trong view này.
        return view('components.counter');
    }
}
