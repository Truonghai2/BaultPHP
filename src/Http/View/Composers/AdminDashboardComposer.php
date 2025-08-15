<?php

namespace Http\View\Composers;

use Core\Contracts\View\View;

class AdminDashboardComposer
{
    /**
     * Create a new admin dashboard composer.
     *
     * Trong một ứng dụng thực tế, bạn có thể inject các service cần thiết
     * vào đây thông qua constructor, và DI container sẽ tự động cung cấp chúng.
     *
     * Ví dụ:
     * public function __construct(protected UserRepository $users) {}
     */
    public function __construct()
    {
        //
    }

    /**
     * Bind data to the view.
     *
     * @param  \Core\Contracts\View\View  $view
     */
    public function compose(View $view): void
    {
        // Logic để lấy dữ liệu, ví dụ: truy vấn CSDL
        $view->with('userCount', 150);
    }
}
