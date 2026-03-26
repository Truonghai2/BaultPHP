<?php

namespace Modules\User\Http\Controllers;

use Core\Auth\AuthManager;
use Core\Contracts\View\Factory as ViewFactory;
use Core\Routing\Attributes\Route;
use Core\Security\FileValidator;
use Core\Support\Facades\Auth;
use Core\Support\Facades\Storage;
use Core\WebAssembly\WasmImageProcessor;
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
    public function __construct(
        ViewFactory $view,
        private AuthManager $auth,
        private WasmImageProcessor $wasmImageProcessor,
    )
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

        $outputPath = base_path('storage/app/public/' . $path);
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'avatar_');
        if ($tempPath === false) {
            throw new \RuntimeException('Failed to create temp file for avatar processing');
        }

        file_put_contents($tempPath, $uploadedFile->getStream()->getContents());

        try {
            // SECURITY: Validate file before processing
            $validation = FileValidator::validate($tempPath, 'image/jpeg', [
                'max_size' => 5242880, // 5MB
            ]);

            if (!$validation['valid']) {
                @unlink($tempPath);
                return redirect()->back()
                    ->with('error', 'Invalid image: ' . implode(', ', $validation['errors']));
            }

            // SECURITY: Sanitize image (strips malicious content)
            $sanitizedPath = sys_get_temp_dir() . '/sanitized_' . $filename;
            $sanitized = FileValidator::sanitizeImage($tempPath, $sanitizedPath, [
                'quality' => 80,
                'format' => 'jpeg',
            ]);

            if (!$sanitized) {
                @unlink($tempPath);
                return redirect()->back()->with('error', 'Failed to sanitize image');
            }

            // Use WASM processor on sanitized image
            $this->wasmImageProcessor->resize($sanitizedPath, 200, 200, [
                'output_path' => $outputPath,
                'quality' => 80,
                'format' => 'jpeg',
                'preserve_aspect' => true,
            ]);

            @unlink($tempPath);
            @unlink($sanitizedPath);
        } catch (\Exception $e) {
            @unlink($tempPath);
            if (isset($sanitizedPath) && file_exists($sanitizedPath)) {
                @unlink($sanitizedPath);
            }
            throw $e;
        }

        // Ensure file is accessible via storage disk
        if (!Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, file_get_contents($outputPath));
        }

        $user = $this->auth->user();
        $user->avatar_path = $path;
        $user->save();

        return redirect()->back()->with('success', 'Avatar đã được cập nhật thành công!');
    }
}
