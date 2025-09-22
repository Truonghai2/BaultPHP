<?php

namespace App\Http\View\Composers;

use Core\Auth\AuthManager;
use Core\Contracts\View\View;

class CurrentUserComposer
{
    /**
     * Create a new composer.
     *
     * @param \Core\Auth\AuthManager $auth
     */
    public function __construct(protected AuthManager $auth)
    {
    }

    /**
     * Bind data to the view.
     *
     * @param  \Core\Contracts\View\View  $view
     */
    public function compose(View $view): void
    {
        // Chia sẻ thông tin người dùng đang đăng nhập cho tất cả các view.
        $view->with('currentUser', $this->auth->user());
    }
}
