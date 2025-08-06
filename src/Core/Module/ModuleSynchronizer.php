<?php

namespace Core\Module;

use Core\Support\Facades\Log;
use Spiral\Goridge\RPC\RPC;

class ModuleSynchronizer
{
    /**
     * @param RPC|null $rpc The RPC client, nullable for environments without RoadRunner.
     */
    public function __construct(private ?RPC $rpc = null)
    {
    }
    /**
     * Scans the filesystem for modules and synchronizes them with the database.
     *
     * @return array An array containing 'added' and 'removed' module names.
     */
    public function sync(): array
    {
        Log::info('Running module synchronization...');

        $filesystemModules = $this->getFilesystemModules();
        $databaseModules = Module::all()->keyBy('name');

        $filesystemModuleNames = array_keys($filesystemModules);
        $databaseModuleNames = $databaseModules->keys()->all();

        $newlyAdded = [];
        $staleRemoved = [];

        // Modules to add to DB
        $newModules = array_diff($filesystemModuleNames, $databaseModuleNames);
        if (!empty($newModules)) {
            Log::info('New modules found, registering...', ['modules' => $newModules]);
            foreach ($newModules as $moduleName) {
                $meta = $filesystemModules[$moduleName];
                Module::create([
                    'name' => $meta['name'],
                    'version' => $meta['version'] ?? '1.0.0',
                    'description' => $meta['description'] ?? '',
                    'enabled' => false, // Always add as disabled by default
                ]);
                $newlyAdded[] = $moduleName;

                // Broadcast the new module detection via RPC
                $this->broadcastNewModule($meta);
            }
        }

        // Modules to remove from DB (stale records)
        $staleModules = array_diff($databaseModuleNames, $filesystemModuleNames);
        if (!empty($staleModules)) {
            Log::info('Stale module records found, removing...', ['modules' => $staleModules]);
            Module::whereIn('name', $staleModules)->delete();
            $staleRemoved = $staleModules;
        }
        Log::info('Module synchronization complete.');

        return [
            'added' => $newlyAdded,
            'removed' => $staleRemoved,
        ];
    }

    private function getFilesystemModules(): array
    {
        $modules = [];
        $modulesPath = base_path('Modules');
        if (!is_dir($modulesPath)) {
            return [];
        }
        $moduleDirs = glob($modulesPath . '/*', GLOB_ONLYDIR);

        foreach ($moduleDirs as $dir) {
            $metaFile = $dir . '/module.json';
            if (file_exists($metaFile)) {
                $meta = json_decode(file_get_contents($metaFile), true);
                if (isset($meta['name'])) {
                    $modules[$meta['name']] = $meta;
                }
            }
        }
        return $modules;
    }

    /**
     * Broadcasts a notification about a newly detected module via RPC to the WebSocket worker.
     *
     * @param array $moduleMeta The metadata of the new module.
     */
    private function broadcastNewModule(array $moduleMeta): void
    {
        if (!$this->rpc) {
            return;
        }

        try {
            $payload = json_encode([
                'event' => 'new_module_detected',
                'data' => $moduleMeta,
            ]);
            $this->rpc->call('informer.broadcast', $payload);
        } catch (\Throwable $e) {
            Log::error('Failed to broadcast new module notification via RPC', ['error' => $e->getMessage()]);
        }
    }
}
