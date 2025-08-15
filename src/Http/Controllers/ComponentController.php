<?php

namespace Http\Controllers;

use Core\Frontend\Attributes\CallableMethod;
use Core\Frontend\ChecksumService;
use Core\Validation\ValidationException;
use Http\ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as Request;

class ComponentController
{
    public function __invoke(Request $request, ResponseFactory $responseFactory, ChecksumService $checksumService): \Http\JsonResponse
    {
        $snapshot = json_decode($request->input('snapshot'), true);
        $updates = $request->input('updates');
        $calls = $request->input('calls');

        $componentClass = $snapshot['class'];

        if (!class_exists($componentClass)) {
            return $responseFactory->json(['error' => 'Component not found'], 404);
        }

        // BẢO MẬT QUAN TRỌNG: Ngăn chặn việc khởi tạo class tùy ý.
        // Chỉ cho phép khởi tạo các class kế thừa từ Component.
        // Trong một ứng dụng thực tế, bạn có thể muốn có một danh sách trắng (whitelist)
        // các component được phép khởi tạo, được định nghĩa trong một file config.
        if (!is_subclass_of($componentClass, \Core\Frontend\Component::class)) {
            return $responseFactory->json(['error' => 'Component is not allowed.'], 403);
        }

        // 1. QUAN TRỌNG: Xác thực checksum của snapshot từ client
        if (!$checksumService->verify($componentClass, $snapshot['data'], $snapshot['checksum'] ?? '')) {
            // Trả về lỗi 419 (CSRF Token Mismatch) hoặc lỗi tương tự để chỉ ra dữ liệu đã bị thay đổi
            return $responseFactory->json(['error' => 'The component snapshot has been tampered with.'], 419);
        }

        /** @var \Core\Frontend\Component $component */
        $component = app($componentClass);

        // 1. Hydrate component với state từ client
        $component->hydrateState($snapshot['data']);

        // 2. Thực thi action được gọi từ client
        try {
            if ($calls) {
                $method = $calls['method'];
                $params = $calls['params'];

                if (method_exists($component, $method)) {
                    // BẢO MẬT: Dùng Reflection để kiểm tra xem phương thức có được đánh dấu bằng Attribute #[CallableMethod] không.
                    $reflectionMethod = new \ReflectionMethod($component, $method);
                    $attributes = $reflectionMethod->getAttributes(CallableMethod::class);

                    if (empty($attributes)) {
                        return $responseFactory->json(['error' => 'The method is not callable.'], 403);
                    }
                    app()->call([$component, $method], $params);
                } else {
                    return $responseFactory->json(['error' => 'Method not found on component.'], 404);
                }
            }
        } catch (ValidationException $e) {
            // Khi validation thất bại, một ValidationException sẽ được throw.
            // Chúng ta bắt nó và trả về một response 422 với danh sách lỗi.
            // Frontend JS sẽ tự động nhận diện và hiển thị các lỗi này.
            return $responseFactory->json(['errors' => $e->errors()], 422);
        }

        // 3. Render lại component với state mới
        $html = $component->render();

        // Lấy các sự kiện đã được dispatch từ component
        $dispatches = $component->getDispatchQueue();

        // 4. Lấy state mới và tạo snapshot mới để gửi về client
        $newState = $component->getState();
        $newSnapshot = [
            'class' => $componentClass,
            'data' => $newState,
            // QUAN TRỌNG: Thêm checksum để bảo mật, chống tampering
            'checksum' => $checksumService->generate($componentClass, $newState),
        ];

        return $responseFactory->json([
            'snapshot' => json_encode($newSnapshot),
            'html' => $html,
            'dispatches' => $dispatches,
        ]);
    }
}
