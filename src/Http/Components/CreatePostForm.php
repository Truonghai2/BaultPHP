<?php

namespace App\Http\Components;

use App\Rules\NoProfanityRule;
use Core\Frontend\Attributes\CallableMethod;
use Core\Frontend\Component;

class CreatePostForm extends Component
{
    public string $title = '';
    public string $content = '';

    protected array $rules = [
        'title' => ['required', 'min:6', 'max:255', new NoProfanityRule()],
        'content' => ['required'],
    ];

    #[CallableMethod]
    public function save(): void
    {
        $this->validate();

        $this->title = '';
        $this->content = '';

        $this->dispatch('post-created');
    }

    public function render()
    {
        return view('components.create-post-form');
    }
}
