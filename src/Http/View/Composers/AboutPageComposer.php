<?php

namespace App\Http\View\Composers;

use Core\Contracts\View\View;

class AboutPageComposer
{
    /**
     * Create a new composer.
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
        // Ví dụ: Cung cấp dữ liệu về các thành viên trong nhóm cho trang 'about'
        $view->with('teamMembers', [
            ['name' => 'Bault Developer', 'role' => 'Lead Developer'],
            ['name' => 'Framework User', 'role' => 'UI/UX Designer'],
        ]);
    }
}
