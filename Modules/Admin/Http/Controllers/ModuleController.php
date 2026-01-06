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
use Core\Module\ModuleSynchronizer;
use Core\Routing\Attributes\Route;
use Core\Services\ModuleInstallerService;
use Core\Support\Facades\Cache;
use Core\Services\ModuleService;
use Core\Support\Facades\Log;
use Modules\Cms\Domain\Services\BlockSynchronizer;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Route(group: 'web')]
class ModuleController extends Controller
{
    private const MODULE_LIST_CACHE_KEY = 'modules.all_list';

    public function __construct(
        private ModuleService $moduleService,
        private ModuleInstallerService $installerService,
        private ModuleSynchronizer $moduleSynchronizer,
        private BlockSynchronizer $blockSynchronizer
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
            // Install module with full integration
            $result = $this->installerService->install($zipPath, runMigrations: true, installDependencies: true);
            
            Cache::forget(self::MODULE_LIST_CACHE_KEY); // Xóa cache sau khi cài đặt
            
            return response()->json([
                'message' => 'Cài đặt module thành công!',
                'module' => $result['module'] ?? null,
                'version' => $result['version'] ?? null,
                'status' => $result['status'] ?? 'installed',
            ], 201);
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
        $pendingModules = session()->get('pending_modules', []);

        if (empty($pendingModules)) {
            return redirect('/admin/modules');
        }

        // pending_modules đã chứa array of module objects từ middleware
        // Format lại cho view nếu cần
        $modulesToInstall = [];
        foreach ($pendingModules as $moduleInfo) {
            // $moduleInfo đã là array với 'name', 'version', 'description', v.v.
            if (is_array($moduleInfo) && isset($moduleInfo['name'])) {
                $modulesToInstall[] = [
                    'name' => $moduleInfo['name'],
                    'version' => $moduleInfo['version'] ?? '1.0.0',
                    'description' => $moduleInfo['description'] ?? 'Không có mô tả.',
                    'requirements' => $moduleInfo['requirements'] ?? [],
                ];
            }
        }

        return response(view('admin::modules.install-confirm', ['modules' => $modulesToInstall]));
    }
    
    /**
     * Xử lý việc cài đặt các module đã được người dùng xác nhận.
     * Cài tất cả module được chọn và enable nếu user chọn option.
     */
    #[Route('/admin/modules/install', method: 'POST', name: 'admin.modules.install.process')]
    public function processInstall(RequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $modulesToInstall = $body['modules'] ?? []; 
        $enableAfterInstall = isset($body['enable_modules']) && $body['enable_modules'] === 'on';
        
        if (empty($modulesToInstall)) {
            return redirect('/admin/modules')->with('error', 'Không có module nào được chọn để cài đặt.');
        }

        Log::info('Bắt đầu cài đặt modules', [
            'modules' => $modulesToInstall,
            'enable_after_install' => $enableAfterInstall,
        ]);

        try {
            // Bước 1: Đồng bộ modules từ filesystem vào database
            Log::info('Đồng bộ modules với database...');
            $syncResult = $this->moduleSynchronizer->sync();
            Log::info('Đồng bộ hoàn tất', $syncResult);
            
            $installed = [];
            $errors = [];
            $enabledModules = [];
            $skipped = [];

            // Bước 2: Cài đặt TẤT CẢ modules được chọn
            foreach ($modulesToInstall as $moduleName) {
                try {
                    Log::info("Xử lý module: {$moduleName}");
                    
                    // Kiểm tra module tồn tại trên filesystem
                    $modulePath = base_path("Modules/{$moduleName}");
                    if (!is_dir($modulePath)) {
                        $errors[$moduleName] = "Module không tồn tại trên filesystem";
                        Log::warning("Module '{$moduleName}' không tồn tại");
                        continue;
                    }
                    
                    // Trigger dependencies job (registerModule tự động skip nếu đã tồn tại)
                    $this->moduleService->registerModule($moduleName);
                    $installed[] = $moduleName;
                    
                    // Bước 3: Enable module nếu user chọn
                    if ($enableAfterInstall) {
                        try {
                            $this->moduleService->enableModule($moduleName);
                            $enabledModules[] = $moduleName;
                            Log::info("Module '{$moduleName}' đã được kích hoạt");
                        } catch (\Throwable $e) {
                            Log::warning("Không thể enable module '{$moduleName}': " . $e->getMessage());
                            // Không throw - module đã được cài, chỉ không enable được
                        }
                    }
                    
                } catch (\Throwable $e) {
                    $errors[$moduleName] = $e->getMessage();
                    Log::error("Lỗi khi cài đặt module '{$moduleName}'", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            // Clear cache
            Cache::forget(self::MODULE_LIST_CACHE_KEY);
            
            // Clear session và middleware cache
            session()->remove('pending_modules');
            session()->remove('pending_modules_count');
            session()->remove('pending_modules_notified');
            \App\Http\Middleware\CheckForPendingModulesMiddleware::clearCache();

            // Prepare flash messages
            $flash = [];
            
            // Thông báo về module được cập nhật version
            if (!empty($syncResult['updated'])) {
                $updatedCount = count($syncResult['updated']);
                $flash['info'] = "Đã cập nhật version cho {$updatedCount} module: " . implode(', ', $syncResult['updated']) . '.';
            }
            
            if (!empty($installed)) {
                $successMsg = 'Đã cài đặt thành công ' . count($installed) . ' module: ' . implode(', ', $installed) . '.';
                
                if ($enableAfterInstall) {
                    if (!empty($enabledModules)) {
                        $successMsg .= ' Đã kích hoạt: ' . implode(', ', $enabledModules) . '.';
                    }
                    
                    $notEnabled = array_diff($installed, $enabledModules);
                    if (!empty($notEnabled)) {
                        $successMsg .= ' Module chưa kích hoạt: ' . implode(', ', $notEnabled) . ' (có thể enable sau).';
                    }
                } else {
                    $successMsg .= ' Tất cả module ở trạng thái disabled. Bạn có thể enable trong quản lý module.';
                }
                
                $successMsg .= ' Dependencies và migrations sẽ tự động cài đặt trong nền.';
                $flash['success'] = $successMsg;
            }
            
            if (!empty($errors)) {
                $errorCount = count($errors);
                $errorList = array_map(
                    fn ($name, $msg) => "<li><strong>{$name}:</strong> " . htmlspecialchars($msg) . '</li>', 
                    array_keys($errors), 
                    $errors
                );
                $flash['error'] = "Có {$errorCount} module gặp lỗi:<ul>" . implode('', $errorList) . '</ul>';
            }
            
            if (empty($installed) && empty($errors)) {
                $flash['warning'] = 'Không có module nào được cài đặt.';
            }

            // Bước cuối: Đồng bộ block types sau khi cài đặt/cập nhật module
            Log::info('Đồng bộ block types sau khi cài đặt module...');
            $this->blockSynchronizer->sync();

            return redirect('/admin/modules')->with($flash);
            
        } catch (\Throwable $e) {
            Log::error('Lỗi nghiêm trọng trong quá trình cài đặt modules', [
                'error' => $e->getMessage(),
                'modules' => $modulesToInstall,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect('/admin/modules')->with('error', 'Lỗi nghiêm trọng: ' . $e->getMessage());
        }
    }

    /**
     * Cho phép user skip việc cài module tạm thời (30 phút)
     */
    #[Route('/admin/modules/skip-install', method: 'POST', name: 'admin.modules.skip')]
    public function skipInstall(): ResponseInterface
    {
        $user = auth()->user();
        
        if ($user && $user->isSuperAdmin()) {
            // Set cache skip 30 phút
            $skipKey = 'modules:user_skip:' . $user->id;
            cache()->put($skipKey, true, 1800); // 30 phút
            
            session()->remove('pending_modules_notified');
        }

        return redirect()->back()->with('info', 'Đã tạm thời bỏ qua thông báo cài module. Bạn có thể cài đặt sau trong phần quản lý module.');
    }
}
