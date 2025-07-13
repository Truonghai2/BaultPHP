<?php

namespace Http\Controllers\Admin;

use Http\Request;
use Spiral\Goridge\RPC\RPC;
use Core\Module\ModuleManager;
use Core\Routing\Attributes\Route;
use Http\Response;

// Thêm attribute Route cho class để định nghĩa prefix chung cho tất cả các route bên trong.
// Áp dụng middleware 'can' với tham số là quyền 'admin.modules.manage'.
#[Route('/api/admin/modules', middleware: 'can:admin.modules.manage')]
class ModuleController
{
    public function __construct(private ModuleManager $moduleManager, private ?RPC $rpc)
    {
    }

    /**
     * Lấy danh sách tất cả các module.
     */
    #[Route('', method: 'GET')]
    public function index(Request $request): Response
    {
        $modules = $this->moduleManager->getAllModules();

        // Lấy kết quả đồng bộ từ middleware (nếu có)
        $syncResult = $request->attributes->get('sync_result');

        return (new Response())->json([
            'modules' => $modules,
            'sync_result' => $syncResult,
        ]);
    }

    /**
     * Kích hoạt một module.
     */
    #[Route('/{moduleName}/enable', method: 'POST')]
    public function enable(string $moduleName): Response
    {
        try {
            $this->moduleManager->enable($moduleName);
            // Gửi lệnh RPC đến reset_worker để yêu cầu reload tất cả workers.
            $this->rpc?->call('resetter.reset', "Module '{$moduleName}' was enabled.");
            return (new Response())->json(['message' => "Module '{$moduleName}' enabled successfully."]);
        } catch (\Exception $e) {
            return (new Response())->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Vô hiệu hóa một module.
     */
    #[Route('/{moduleName}/disable', method: 'POST')]
    public function disable(string $moduleName): Response
    {
        try {
            $this->moduleManager->disable($moduleName);
            // Gửi lệnh RPC đến reset_worker để yêu cầu reload tất cả workers.
            $this->rpc?->call('resetter.reset', "Module '{$moduleName}' was disabled.");
            return (new Response())->json(['message' => "Module '{$moduleName}' disabled successfully."]);
        } catch (\Exception $e) {
            return (new Response())->json(['message' => $e->getMessage()], 500);
        }
    }
}