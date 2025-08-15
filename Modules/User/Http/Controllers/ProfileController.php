<?php

namespace Modules\User\Http\Controllers;

use Core\Contracts\View\Factory as ViewFactory;
use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use Psr\Http\Message\ResponseInterface;

class ProfileController
{
    /**
     * The view factory instance, injected by the DI container.
     *
     * @var \Illuminate\Contracts\View\Factory
     */
    protected ViewFactory $view;

    // Framework sẽ tự động "tiêm" (inject) ViewFactory vào đây khi khởi tạo controller.
    public function __construct(ViewFactory $view)
    {
        $this->view = $view;
    }

    #[Route('/profile', method: 'GET', group: 'web')]
    public function index(): ResponseInterface
    {
        // QUY TRÌNH ĐÚNG:
        // 1. Dùng ViewFactory đã được inject để render file 'welcome.blade.php' thành chuỗi HTML.
        // View 'welcome' cần biến 'version', chúng ta sẽ truyền nó vào đây để tránh lỗi.
        $htmlContent = $this->view->make('welcome', ['version' => app()->version()])->render();

        // 2. Dùng helper `response()` để tạo một Response hợp lệ từ chuỗi HTML đó.
        return response($htmlContent);
    }

    /**
     * Lấy thông tin profile của người dùng đã xác thực.
     * Việc xác thực được xử lý bởi middleware group 'api'.
     */
    #[Route('/api/profile', method: 'GET', group: 'api')]
    public function show(): array
    {
        /** @var \Modules\User\Infrastructure\Models\User $user */
        // Middleware group 'api' đã đảm bảo người dùng được xác thực,
        // vì vậy chúng ta có thể tin tưởng rằng Auth::user() sẽ không trả về null.
        $user = Auth::user();

        // Framework sẽ tự động chuyển đổi mảng này thành một JsonResponse.
        return [
            'id' => $user->getAuthIdentifier(),
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
