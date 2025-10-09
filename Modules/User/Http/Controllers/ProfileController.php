<?php

namespace Modules\User\Http\Controllers;

use Core\Auth\AuthManager;
use Core\Contracts\View\Factory as ViewFactory;
use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use Core\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use Modules\User\Http\Requests\UpdateAvatarRequest;
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
    public function __construct(ViewFactory $view, private AuthManager $auth)
    {
        $this->view = $view;
    }

    #[Route('/profile', method: 'GET', group: 'web')]
    public function index(): ResponseInterface
    {
        $htmlContent = $this->view->make('welcome', ['version' => app()->version()])->render();
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
        $user = Auth::user();

        return [
            'id' => $user->getAuthIdentifier(),
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    /**
     * Xử lý việc upload và cập nhật avatar cho người dùng.
     */
    #[Route('/profile/avatar', method: 'POST', group: 'web')]
    public function updateAvatar(UpdateAvatarRequest $request): ResponseInterface
    {
        /** @var \Core\Http\UploadedFile $uploadedFile */
        $uploadedFile = $request->file('avatar');

        // Tạo một tên file duy nhất với phần mở rộng gốc
        $filename = uniqid('avatar_') . '.' . $uploadedFile->getClientOriginalExtension();
        $path = 'avatars/' . $filename;

        $image = Image::make($uploadedFile->getStream()->getContents())
            ->fit(200, 200, function ($constraint) { // Thay đổi kích thước thành hình vuông 200x200
                $constraint->upsize(); // Ngăn không phóng to ảnh nhỏ hơn
            })
            ->encode('jpg', 80); // Chuyển đổi sang JPG với chất lượng 80%

        Storage::disk('public')->put($path, (string) $image);

        $user = $this->auth->user();
        $user->avatar_path = $path;
        $user->save();

        return redirect()->back()->with('success', 'Avatar đã được cập nhật thành công!');
    }
}
