<?php

namespace Http\Controllers\Admin;

use Core\Module\ModuleManager;
use Core\Routing\Attributes\Route;
use Core\Support\Facades\Log;
use Psr\Http\Message\ServerRequestInterface as Request;
use Spiral\Goridge\RPC\RPC;

// Add a Route attribute to the class to define a common prefix for all routes within.
// Apply the 'can' middleware with the 'admin.modules.manage' permission.
#[Route(prefix: '/api/admin/modules', middleware: ['can:admin.modules.manage'])]
class ModuleController
{
    public function __construct(private ModuleManager $moduleManager, private ?RPC $rpc)
    {
    }

    /**
     * Get a list of all modules.
     */
    #[Route('', method: 'GET')]
    public function index(Request $request): \Http\JsonResponse
    {
        $modules = $this->moduleManager->getAllModules();

        $syncResult = $request->getAttribute('sync_result');

        return response()->json([
            'modules' => $modules,
            'sync_result' => $syncResult,
        ]);
    }

    /**
     * Enable a module.
     */
    #[Route('/{moduleName}/enable', method: 'POST')]
    public function enable(string $moduleName): \Http\JsonResponse
    {
        return $this->setModuleStatus($moduleName, 'enable');
    }

    /**
     * Disable a module.
     */
    #[Route('/{moduleName}/disable', method: 'POST')]
    public function disable(string $moduleName): \Http\JsonResponse
    {
        return $this->setModuleStatus($moduleName, 'disable');
    }

    /**
     * Helper method to handle enabling or disabling a module.
     * This reduces code duplication between the enable() and disable() methods.
     *
     * @param string $moduleName The name of the module to act upon.
     * @param string $action The action to perform ('enable' or 'disable').
     * @return \Http\JsonResponse
     */
    private function setModuleStatus(string $moduleName, string $action): \Http\JsonResponse
    {
        $pastTenseAction = $action . 'd'; // "enabled" or "disabled"

        try {
            $this->moduleManager->{$action}($moduleName);
            $this->rpc?->call('resetter.reset', [
                env('RPC_SECRET_TOKEN'),
                ['http', 'centrifuge'], // Reset web and websocket workers
                "Module '{$moduleName}' was {$pastTenseAction}.",
            ]);
            return response()->json(['message' => "Module '{$moduleName}' {$pastTenseAction} successfully."]);
        } catch (\Exception $e) {
            Log::error("Failed to {$action} module '{$moduleName}': " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
