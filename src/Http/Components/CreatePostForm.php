<?php

namespace App\Http\Components;

use App\Rules\NoProfanityRule;
use Core\Frontend\Attributes\CallableMethod;
use Core\Frontend\Component;

class CreatePostForm extends Component
{
    public string $title = '';
    public string $content = '';

    /**
     * Định nghĩa các quy tắc validation cho các thuộc tính public.
     * Bạn có thể sử dụng tất cả các rule của Laravel/Illuminate.
     */
    protected array $rules = [
        'title' => ['required', 'min:6', 'max:255', new NoProfanityRule()],
        'content' => ['required'],
    ];

    #[CallableMethod]
    public function save(): void
    {
        // Kích hoạt validation. Nếu thất bại, nó sẽ throw exception
        // và ComponentController sẽ bắt lại.
        $this->validate();

        // Logic lưu bài viết vào CSDL chỉ chạy khi validation thành công...
        // ...

        // Reset form
        $this->title = '';
        $this->content = '';

        // Phát sự kiện 'post-created' để thông báo cho component cha
        $this->dispatch('post-created');
    }

    public function render()
    {
        return view('components.create-post-form');
    }
}
