<?php

namespace Modules\Admin\Http\Controllers;

use Core\Exceptions\Module\DangerousCodeDetectedException;
use Core\Exceptions\Module\InvalidModuleFileException;
use Core\Exceptions\Module\InvalidModuleSignatureException;
use Core\Exceptions\Module\InvalidModuleStructureException;
use Core\Exceptions\Module\ModuleAlreadyExistsException;
use Core\Exceptions\Module\ModuleInstallationException;
use Core\Exceptions\Module\ModuleNotFoundException;
use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Services\ModuleInstallerService;
use Core\Support\Facades\Cache;
use Core\Services\ModuleService;
use Core\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Route(group: 'web')]
class ModuleController extends Controller
{
    private const MODULE_LIST_CACHE_KEY = 'modules.all_list';

    public function __construct(
        private ModuleService $moduleService,
        private ModuleInstallerService $installerService,
    ) {
    }

    /**
     * Hiển thị trang quản lý module.
     */
    #[Route('/admin/modules', method: 'GET')]
    public function showPage(): ResponseInterface
    {
        return response(view('admin::modules.index', []));
    }

    /**
     * API: Lấy danh sách tất cả các module.
     */
    #[Route('/api/admin/modules', method: 'GET')]
    public function index(): ResponseInterface
    {
        try {
            $modules = Cache::remember(self::MODULE_LIST_CACHE_KEY, 3600, function () {
                return $this->moduleService->getModules();
            });
            return response()->json($modules);
        } catch (\Throwable $e) {
            Log::error('Lỗi khi lấy danh sách module: ' . $e->getMessage());
            return response()->json(['error' => 'Không thể lấy danh sách module.'], 500);
        }
    }

    /**
     * API: Cài đặt một module mới từ file ZIP.
     */
    #[Route('/api/admin/modules', method: 'POST')]
    public function store(RequestInterface $request): ResponseInterface
    {
        $uploadedFile = $request->getUploadedFiles()['module_zip'] ?? null;

        if (!$uploadedFile || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return response()->json(['error' => 'Lỗi tải lên file hoặc không có file nào được chọn.'], 400);
        }

        $zipPath = $uploadedFile->getStream()->getMetadata('uri');

        try {
            $this->installerService->install($zipPath);
            Cache::forget(self::MODULE_LIST_CACHE_KEY); // Xóa cache sau khi cài đặt
            return response()->json(['message' => 'Cài đặt module thành công!'], 201);
        } catch (ModuleAlreadyExistsException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (InvalidModuleFileException | InvalidModuleStructureException | InvalidModuleSignatureException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (DangerousCodeDetectedException $e) {
            Log::warning('Phát hiện mã độc khi cài module', ['details' => $e->getMessage()]);
            return response()->json(['error' => 'Module chứa mã không an toàn và đã bị từ chối.'], 400);
        } catch (ModuleInstallationException $e) {
            Log::error('Lỗi cài đặt module không xác định: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Lỗi không xác định trong quá trình cài đặt: ' . $e->getMessage()], 500);
        } catch (\Throwable $e) {
            // Bắt các lỗi hệ thống khác không lường trước được
            Log::critical('Lỗi hệ thống nghiêm trọng khi cài module: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Đã có lỗi hệ thống nghiêm trọng xảy ra.'], 500);
        }
    }

    /**
     * API: Bật/tắt một module.
     */
    #[Route('/api/admin/modules/{name}', method: 'PUT')]
    public function update(string $name): ResponseInterface
    {
        try {
            $newStatus = $this->moduleService->toggleStatus($name);
            Cache::forget(self::MODULE_LIST_CACHE_KEY); // Xóa cache sau khi thay đổi trạng thái
            $message = "Module '{$name}' đã được " . ($newStatus ? 'bật' : 'tắt') . '.';
            return response()->json(['message' => $message, 'enabled' => $newStatus]);
        } catch (ModuleNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404); // Not Found
        } catch (\Throwable $e) {
            Log::error("Lỗi khi thay đổi trạng thái module '{$name}': " . $e->getMessage());
            return response()->json(['error' => "Không thể thay đổi trạng thái module '{$name}'."], 500);
        }
    }

    /**
     * API: Xóa một module.
     */
    #[Route('/api/admin/modules/{name}', method: 'DELETE')]
    public function destroy(string $name): ResponseInterface
    {
        try {
            $this->moduleService->deleteModule($name);
            Cache::forget(self::MODULE_LIST_CACHE_KEY); // Xóa cache sau khi xóa module
            return response()->json(null, 204);
        } catch (ModuleNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error("Lỗi khi xóa module '{$name}': " . $e->getMessage());
            return response()->json(['error' => "Không thể xóa module '{$name}'."], 500);
        }
    }

    /**
     * Hiển thị trang xác nhận cài đặt các module mới được phát hiện.
     */
    #[Route('/admin/modules/install/confirm', method: 'GET', middleware: ['auth', 'can:isSuperAdmin'])]
    public function showInstallConfirmPage(): ResponseInterface
    {
        $pendingNames = session()->get('pending_modules', []);

        if (empty($pendingNames)) {
            return redirect('/admin/modules');
        }

        $modulesToInstall = [];
        foreach ($pendingNames as $name) {
            $jsonPath = base_path("Modules/{$name}/module.json");
            if (file_exists($jsonPath) && ($meta = json_decode(file_get_contents($jsonPath), true))) {
                $modulesToInstall[] = [
                    'name' => $name,
                    'version' => $meta['version'] ?? '1.0.0',
                    'description' => $meta['description'] ?? 'Không có mô tả.',
                    'requirements' => $meta['require'] ?? [],
                ];
            }
        }

        return response(view('admin::modules.install-confirm', ['modules' => $modulesToInstall]));
    }
    
    /**
     * Xử lý việc cài đặt các module đã được người dùng xác nhận.
     */
    #[Route('/admin/modules/install', method: 'POST', name: 'admin.modules.install.process')]
    public function processInstall(RequestInterface $request): ResponseInterface
    {
        $modulesToInstall = $request->getParsedBody()['modules'] ?? []; 
        
        if (empty($modulesToInstall)) {
            return redirect('/admin/modules')->with('error', 'Không có module nào được chọn để cài đặt.');
        }

        $installed = [];
        $errors = [];

        foreach ($modulesToInstall as $moduleName) {
            try {
                $this->moduleService->registerModule($moduleName);
                Cache::forget(self::MODULE_LIST_CACHE_KEY); // Xóa cache
                $installed[] = $moduleName;
            } catch (\Throwable $e) {
                $errors[$moduleName] = $e->getMessage();
                Log::error("Lỗi khi đăng ký module '{$moduleName}': " . $e->getMessage());
            }
        }

        session()->forget('pending_modules');

        $flash = [];
        if (!empty($installed)) {
            $flash['success'] = 'Các module đã được đưa vào hàng đợi cài đặt: ' . implode(', ', $installed) . '. Quá trình cài đặt (bao gồm thư viện và database) sẽ tự động diễn ra trong nền. Vui lòng tải lại trang sau ít phút để xem kết quả.';
        }
        if (!empty($errors)) {
            $errorMessages = array_map(fn ($name, $msg) => "<li><strong>{$name}:</strong> " . htmlspecialchars($msg) . '</li>', array_keys($errors), $errors);
            $flash['error'] = 'Đã có lỗi xảy ra trong quá trình cài đặt:<ul>' . implode('', $errorMessages) . '</ul>';
        }

        return redirect('/admin/modules')->with($flash);
    }
}
