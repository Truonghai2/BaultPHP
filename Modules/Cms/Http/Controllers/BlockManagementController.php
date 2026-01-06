<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use Modules\Cms\Domain\Services\BlockRegistry;
use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\Cms\Infrastructure\Models\BlockRegion;
use Modules\Cms\Infrastructure\Models\BlockType;
use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;
use Modules\User\Infrastructure\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Block Management Controller
 *
 * API endpoints for block management
 */
#[Route(prefix: '/admin/blocks', middleware: ['auth'], group: 'web')]
class BlockManagementController extends Controller
{
    public function __construct(private readonly BlockRegistry $blockRegistry)
    {
    }

    /**
     * List all available block types
     * GET /admin/blocks/types
     */
    #[Route('/types', method: 'GET', name: 'admin.blocks.types')]
    public function listTypes(Request $request): Response
    {
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Check permission only if not in development mode
        if (!config('app.debug') && !$user->can('cms.blocks.manage')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'You do not have permission to manage blocks.',
            ], 403);
        }

        // Get block types from database and convert to array
        $blockTypes = $this->blockRegistry->getAvailableBlockTypes();
        $blockTypesArray = $blockTypes->map(function ($blockType) {
            return [
                'id' => $blockType->id,
                'name' => $blockType->name,
                'title' => $blockType->title,
                'description' => $blockType->description,
                'category' => $blockType->category,
                'icon' => $blockType->icon,
                'configurable' => $blockType->configurable,
                'default_config' => $blockType->default_config,
                'is_active' => $blockType->is_active,
                'created_at' => $blockType->created_at,
                'updated_at' => $blockType->updated_at,
            ];
        })->all();

        // Get categories with blocks converted to arrays
        $categoriesRaw = $this->blockRegistry->getCategories();
        $categories = [];

        foreach ($categoriesRaw as $category => $blocks) {
            $categories[$category] = array_map(function ($block) {
                return [
                    'name' => $block->getName(),
                    'title' => $block->getTitle(),
                    'description' => $block->getDescription(),
                    'category' => $block->getCategory(),
                    'icon' => $block->getIcon(),
                    'configurable' => $block->isConfigurable(),
                    'default_config' => $block->getDefaultConfig(),
                ];
            }, $blocks);
        }

        return response()->json([
            'block_types' => $blockTypesArray,
            'categories' => $categories,
        ]);
    }

    /**
     * List all regions
     * GET /admin/blocks/regions
     */
    #[Route('/regions', method: 'GET', name: 'admin.blocks.regions')]
    public function listRegions(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.blocks.manage')) {
            return response()->json(['error' => 'Forbidden', 'message' => 'You do not have permission to manage blocks.'], 403);
        }

        $regions = BlockRegion::where('is_active', true)->get()->all();
        $regionsData = array_map(fn ($region) => $region->toArray(), $regions);

        return response()->json(['regions' => $regionsData]);
    }

    /**
     * List blocks in a region
     * GET /admin/blocks/regions/{regionName}/blocks
     */
    #[Route('/regions/{regionName}/blocks', method: 'GET', name: 'admin.blocks.regions.list')]
    public function listBlocksInRegion(string $regionName, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.blocks.manage')) {
            return response()->json(['error' => 'Forbidden', 'message' => 'You do not have permission to manage blocks.'], 403);
        }

        $params = $request->getQueryParams();
        $contextType = $params['context_type'] ?? 'global';
        $contextId = isset($params['context_id']) ? (int) $params['context_id'] : null;

        $blocks = [];
        if ($contextType === 'page' && $contextId) {
            $page = Page::find($contextId);
            if ($page) {
                $blocks = $page->blocksInRegion($regionName)->all();
            }
        } else { // 'global' or other contexts
            $regionModel = BlockRegion::where('name', $regionName)->first();
            if ($regionModel) {
                $blocks = BlockInstance::where('region_id', $regionModel->id)
                    ->where('context_type', $contextType)
                    ->orderBy('weight')
                    ->get()->all();
            }
        }

        $blocksData = array_map(function ($block) {
            if ($block instanceof PageBlock) {
                $data = $block->toArray();
                $data['block_type_name'] = $block->blockType->name;
                return $data;
            }
            if ($block instanceof BlockInstance) {
                $data = $block->toArray();
                $data['block_type_name'] = $block->blockType->name;
                return $data;
            }
        }, $blocks);
        return response()->json(['blocks' => $blocksData]);
    }

    /**
     * Create a new block instance
     * POST /admin/blocks
     */
    #[Route('', method: 'POST', name: 'admin.blocks.create')]
    public function create(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.blocks.manage')) {
            return response()->json(['error' => 'Forbidden', 'message' => 'You do not have permission to manage blocks.'], 403);
        }

        $data = $request->getParsedBody();

        // Validation
        if (empty($data['block_type_name']) || empty($data['region'])) {
            return response()->json([
                'error' => 'block_type_name and region are required',
            ], 400);
        }

        try {
            $blockType = BlockType::where('name', $data['block_type_name'])->firstOrFail();
            $contextType = $data['context_type'] ?? 'global';
            $contextId = $data['context_id'] ?? null;

            $instance = null;

            if ($contextType === 'page' && $contextId) {
                $page = Page::findOrFail($contextId);
                $maxOrder = $page->blocks()->where('region', $data['region'])->max('sort_order') ?? -1;

                $instance = PageBlock::create([
                    'page_id' => $page->id,
                    'block_type_id' => $blockType->id,
                    'region' => $data['region'],
                    'content' => $data['content'] ?? null,
                    'sort_order' => $maxOrder + 1,
                    'visible' => $data['visible'] ?? true,
                    'created_by' => $user->id,
                ]);
            } else {
                $regionModel = BlockRegion::where('name', $data['region'])->firstOrFail();
                $maxWeight = BlockInstance::where('region_id', $regionModel->id)
                    ->where('context_type', $contextType)
                    ->max('weight') ?? -1;

                $instance = BlockInstance::create([
                    'block_type_id' => $blockType->id,
                    'region_id' => $regionModel->id,
                    'context_type' => $contextType,
                    'context_id' => $contextId,
                    'title' => $data['title'] ?? $blockType->title,
                    'config' => $data['config'] ?? $blockType->default_config,
                    'content' => $data['content'] ?? null,
                    'weight' => $maxWeight + 1,
                    'visible' => $data['visible'] ?? true,
                    'created_by' => $user->id,
                ]);
            }

            return response()->json([
                'message' => 'Block created successfully',
                'block' => $instance->toArray(),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to create block',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update block instance
     * PUT /admin/blocks/{id}
     */
    #[Route('/{id}', method: 'PUT', name: 'admin.blocks.update')]
    public function update(int $id, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.blocks.manage')) {
            return response()->json(['error' => 'Forbidden', 'message' => 'You do not have permission to manage blocks.'], 403);
        }

        $data = $request->getParsedBody();
        $contextType = $data['context_type'] ?? 'global';

        try {
            $instance = null;
            if ($contextType === 'page') {
                $instance = PageBlock::findOrFail($id);
            } else {
                $instance = BlockInstance::findOrFail($id);
            }

            $updateData = [];
            if (isset($data['content'])) {
                $updateData['content'] = $data['content'];
            }
            if (isset($data['visible'])) {
                $updateData['visible'] = $data['visible'];
            }

            // PageBlock doesn't have title/config, BlockInstance does
            if ($instance instanceof BlockInstance) {
                if (isset($data['title'])) {
                    $updateData['title'] = $data['title'];
                }
                if (isset($data['config'])) {
                    $updateData['config'] = $data['config'];
                }
            }

            if (!empty($updateData)) {
                $instance->update($updateData);
            }

            return response()->json([
                'message' => 'Block updated successfully',
                'block' => $instance->toArray(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to update block',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete block instance
     * DELETE /admin/blocks/{id}
     */
    #[Route('/{id}', method: 'DELETE', name: 'admin.blocks.delete')]
    public function delete(int $id, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.blocks.manage')) {
            return response()->json(['error' => 'Forbidden', 'message' => 'You do not have permission to manage blocks.'], 403);
        }

        $contextType = $request->getQueryParams()['context_type'] ?? 'global';

        try {
            if ($contextType === 'page') {
                PageBlock::findOrFail($id)->delete();
            } else {
                BlockInstance::findOrFail($id)->delete();
            }

            return response()->json([
                'message' => 'Block deleted successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to delete block',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Move block up
     * POST /admin/blocks/{id}/move-up
     */
    #[Route('/{id}/move-up', method: 'POST', name: 'admin.blocks.move-up')]
    public function moveUp(int $id, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.blocks.manage')) {
            return response()->json(['error' => 'Forbidden', 'message' => 'You do not have permission to manage blocks.'], 403);
        }

        $contextType = $request->getParsedBody()['context_type'] ?? 'global';

        try {
            if ($contextType === 'page') {
                $block = PageBlock::findOrFail($id);
                // Simple swap logic
                $prevBlock = PageBlock::where('page_id', $block->page_id)->where('region', $block->region)->where('sort_order', '<', $block->sort_order)->orderBy('sort_order', 'desc')->first();
                if ($prevBlock) {
                    $currentOrder = $block->sort_order;
                    $block->sort_order = $prevBlock->sort_order;
                    $prevBlock->sort_order = $currentOrder;
                    $block->save();
                    $prevBlock->save();
                }
            } else {
                BlockInstance::findOrFail($id)->moveUp();
            }

            return response()->json([
                'message' => 'Block moved up successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to move block',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Move block down
     * POST /admin/blocks/{id}/move-down
     */
    #[Route('/{id}/move-down', method: 'POST', name: 'admin.blocks.move-down')]
    public function moveDown(int $id, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.blocks.manage')) {
            return response()->json(['error' => 'Forbidden', 'message' => 'You do not have permission to manage blocks.'], 403);
        }

        $contextType = $request->getParsedBody()['context_type'] ?? 'global';

        try {
            if ($contextType === 'page') {
                $block = PageBlock::findOrFail($id);
                $nextBlock = PageBlock::where('page_id', $block->page_id)->where('region', $block->region)->where('sort_order', '>', $block->sort_order)->orderBy('sort_order', 'asc')->first();
                if ($nextBlock) {
                    $currentOrder = $block->sort_order;
                    $block->sort_order = $nextBlock->sort_order;
                    $nextBlock->sort_order = $currentOrder;
                    $block->save();
                    $nextBlock->save();
                }
            } else {
                BlockInstance::findOrFail($id)->moveDown();
            }

            return response()->json([
                'message' => 'Block moved down successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to move block',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle block visibility
     * POST /admin/blocks/{id}/toggle-visibility
     */
    #[Route('/{id}/toggle-visibility', method: 'POST', name: 'admin.blocks.toggle-visibility')]
    public function toggleVisibility(int $id, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.blocks.manage')) {
            return response()->json(['error' => 'Forbidden', 'message' => 'You do not have permission to manage blocks.'], 403);
        }

        $contextType = $request->getParsedBody()['context_type'] ?? 'global';

        try {
            if ($contextType === 'page') {
                $instance = PageBlock::findOrFail($id);
            } else {
                $instance = BlockInstance::findOrFail($id);
            }
            $instance->toggleVisibility();

            return response()->json([
                'message' => 'Block visibility toggled',
                'visible' => $instance->visible,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to toggle visibility',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Duplicate block
     * POST /admin/blocks/{id}/duplicate
     */
    #[Route('/{id}/duplicate', method: 'POST', name: 'admin.blocks.duplicate')]
    public function duplicate(int $id, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.blocks.manage')) {
            return response()->json(['error' => 'Forbidden', 'message' => 'You do not have permission to manage blocks.'], 403);
        }

        $contextType = $request->getParsedBody()['context_type'] ?? 'global';

        try {
            if ($contextType === 'page') {
                $instance = PageBlock::findOrFail($id);
                $newInstance = $instance->replicate();
                $newInstance->sort_order++;
                $newInstance->save();
            } else {
                $instance = BlockInstance::findOrFail($id);
                $newInstance = $instance->replicate();
                $newInstance->weight++;
                $newInstance->save();
            }

            return response()->json([
                'message' => 'Block duplicated successfully',
                'block' => $newInstance->toArray(),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to duplicate block',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reorder blocks in a region
     * POST /admin/blocks/reorder
     */
    #[Route('/reorder', method: 'POST', name: 'admin.blocks.reorder')]
    public function reorder(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.blocks.manage')) {
            return response()->json(['error' => 'Forbidden', 'message' => 'You do not have permission to manage blocks.'], 403);
        }

        $data = $request->getParsedBody();

        if (empty($data['block_ids']) || !is_array($data['block_ids']) || empty($data['context_type'])) {
            return response()->json([
                'error' => 'block_ids array and context_type are required',
            ], 400);
        }

        try {
            $contextType = $data['context_type'];
            foreach ($data['block_ids'] as $order => $blockId) {
                if ($contextType === 'page') {
                    PageBlock::where('id', $blockId)->update(['sort_order' => $order]);
                } else {
                    BlockInstance::where('id', $blockId)->update(['weight' => $order]);
                }
            }

            // This is a more robust way to reorder
            // $this->blockManager->reorderBlocks($data['block_ids']);

            return response()->json([
                'message' => 'Blocks reordered successfully',
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to reorder blocks',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
