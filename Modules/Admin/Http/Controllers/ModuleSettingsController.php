<?php

namespace Modules\Admin\Http\Controllers;

use Core\Http\Controller;
use Core\Module\ModuleSettingsManager;
use Core\Routing\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ModuleSettingsController extends Controller
{
    public function __construct(
        private ModuleSettingsManager $settingsManager,
    ) {
    }

    /**
     * Show module settings page.
     */
    #[Route('/admin/modules/{name}/settings', method: 'GET', middleware: ['auth'], group: 'web')]
    public function index(string $name): ResponseInterface
    {
        return response(view('admin::modules.settings', [
            'moduleName' => $name,
        ]));
    }

    /**
     * API: Get module settings.
     */
    #[Route('/api/admin/modules/{name}/settings', method: 'GET', middleware: ['auth'])]
    public function get(string $name): array
    {
        try {
            $settings = $this->settingsManager->getAllWithMeta($name);
            $groups = $this->settingsManager->getGroups($name);

            // Load schema if exists
            $schemaPath = base_path("Modules/{$name}/settings.php");
            $schema = file_exists($schemaPath) ? require $schemaPath : [];

            return [
                'success' => true,
                'data' => [
                    'module_name' => $name,
                    'settings' => $settings,
                    'groups' => $groups,
                    'schema' => $schema,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * API: Update module setting.
     */
    #[Route('/api/admin/modules/{name}/settings', method: 'PUT', middleware: ['auth'])]
    public function update(string $name, ServerRequestInterface $request): array
    {
        try {
            $data = $request->getParsedBody();
            $key = $data['key'] ?? null;
            $value = $data['value'] ?? null;

            if (!$key) {
                return [
                    'success' => false,
                    'error' => 'Setting key is required',
                ];
            }

            $options = [
                'type' => $data['type'] ?? 'string',
                'description' => $data['description'] ?? null,
                'group' => $data['group'] ?? 'general',
                'encrypted' => $data['encrypted'] ?? false,
                'public' => $data['public'] ?? false,
                'order' => $data['order'] ?? 0,
            ];

            $this->settingsManager->set($name, $key, $value, $options);

            return [
                'success' => true,
                'message' => "Setting '{$key}' updated successfully",
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * API: Bulk update module settings.
     */
    #[Route('/api/admin/modules/{name}/settings/bulk', method: 'PUT', middleware: ['auth'])]
    public function bulkUpdate(string $name, ServerRequestInterface $request): array
    {
        try {
            $data = $request->getParsedBody();
            $settings = $data['settings'] ?? [];

            if (empty($settings)) {
                return [
                    'success' => false,
                    'error' => 'No settings provided',
                ];
            }

            foreach ($settings as $setting) {
                $key = $setting['key'] ?? null;
                $value = $setting['value'] ?? null;

                if (!$key) {
                    continue;
                }

                $options = [
                    'type' => $setting['type'] ?? 'string',
                    'description' => $setting['description'] ?? null,
                    'group' => $setting['group'] ?? 'general',
                    'encrypted' => $setting['encrypted'] ?? false,
                    'public' => $setting['public'] ?? false,
                    'order' => $setting['order'] ?? 0,
                ];

                $this->settingsManager->set($name, $key, $value, $options);
            }

            return [
                'success' => true,
                'message' => 'Settings updated successfully',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * API: Delete module setting.
     */
    #[Route('/api/admin/modules/{name}/settings/{key}', method: 'DELETE', middleware: ['auth'])]
    public function delete(string $name, string $key): array
    {
        try {
            $deleted = $this->settingsManager->delete($name, $key);

            if ($deleted) {
                return [
                    'success' => true,
                    'message' => "Setting '{$key}' deleted successfully",
                ];
            }

            return [
                'success' => false,
                'error' => 'Setting not found',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * API: Reset module settings to defaults.
     */
    #[Route('/api/admin/modules/{name}/settings/reset', method: 'POST', middleware: ['auth'])]
    public function reset(string $name): array
    {
        try {
            // Load schema
            $schemaPath = base_path("Modules/{$name}/settings.php");
            if (!file_exists($schemaPath)) {
                return [
                    'success' => false,
                    'error' => 'No settings schema found for this module',
                ];
            }

            $schema = require $schemaPath;

            // Delete all current settings
            $this->settingsManager->deleteAll($name);

            // Set defaults from schema
            foreach ($schema as $group => $settings) {
                foreach ($settings as $key => $config) {
                    if (isset($config['default'])) {
                        $options = [
                            'type' => $config['type'] ?? 'string',
                            'description' => $config['description'] ?? null,
                            'group' => $group,
                            'encrypted' => $config['encrypted'] ?? false,
                            'public' => $config['public'] ?? false,
                            'order' => $config['order'] ?? 0,
                        ];

                        $this->settingsManager->set($name, $key, $config['default'], $options);
                    }
                }
            }

            return [
                'success' => true,
                'message' => 'Settings reset to defaults successfully',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * API: Clear settings cache.
     */
    #[Route('/api/admin/modules/{name}/settings/cache/clear', method: 'POST', middleware: ['auth'])]
    public function clearCache(string $name): array
    {
        try {
            $this->settingsManager->clearCache($name);

            return [
                'success' => true,
                'message' => 'Settings cache cleared successfully',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
