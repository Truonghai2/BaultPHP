<?php

namespace Modules\Cms\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use Modules\Cms\Domain\Services\PageBlockRenderer;
use Modules\Cms\Infrastructure\Models\BlockType;
use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Page Management Controller
 *
 * Admin API for managing pages and their blocks
 */
#[Route(prefix: '/admin/pages', middleware: ['auth'], group: 'web')]
class PageManagementController extends Controller
{
    public function __construct(
        private readonly PageBlockRenderer $pageBlockRenderer,
    ) {
    }

    /**
     * Show pages management UI
     * GET /admin/pages
     */
    #[Route('', method: 'GET', name: 'admin.pages.index')]
    public function index(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        if (!config('app.debug') && !$user->can('cms.pages.view')) {
            return response('Forbidden', 403);
        }

        return response(view('admin.pages.index'));
    }

    /**
     * List all pages (API)
     * GET /admin/pages/api
     */
    #[Route('/api', method: 'GET', name: 'admin.pages.api')]
    public function listPages(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.pages.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $pages = Page::orderBy('created_at', 'desc')->get();

        $pagesData = $pages->map(function ($page) {
            $blockCount = PageBlock::where('page_id', $page->id)->count();

            return [
                'id' => $page->id,
                'name' => $page->name,
                'slug' => $page->slug,
                'user_id' => $page->user_id,
                'block_count' => $blockCount,
                'created_at' => $page->created_at,
                'updated_at' => $page->updated_at,
            ];
        });

        return response()->json([
            'pages' => $pagesData,
            'total' => $pages->count(),
        ]);
    }

    /**
     * DEBUG: Check page blocks
     * GET /admin/pages/{id}/debug
     */
    #[Route('/{id}/debug', method: 'GET', name: 'admin.pages.debug')]
    public function debug(int $id): Response
    {
        if (!config('app.debug')) {
            return response()->json(['error' => 'Debug mode disabled'], 403);
        }

        $page = Page::find($id);
        if (!$page) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        $pageBlocks = PageBlock::where('page_id', $page->id)->get();
        $blockInstances = \Modules\Cms\Infrastructure\Models\BlockInstance::where('context_type', 'page')
            ->where('context_id', $page->id)
            ->get();

        $regions = \Modules\Cms\Infrastructure\Models\BlockRegion::all();

        return response()->json([
            'page' => [
                'id' => $page->id,
                'name' => $page->name,
                'slug' => $page->slug,
            ],
            'page_blocks' => $pageBlocks->map(fn ($b) => [
                'id' => $b->id,
                'block_type_id' => $b->block_type_id,
                'region' => $b->region,
                'visible' => $b->visible,
                'sort_order' => $b->sort_order,
            ]),
            'block_instances' => $blockInstances->map(fn ($i) => [
                'id' => $i->id,
                'title' => $i->title,
                'region_id' => $i->region_id,
                'visible' => $i->visible,
                'weight' => $i->weight,
            ]),
            'regions' => $regions->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
            ]),
        ]);
    }

    /**
     * Show page block editor UI
     * GET /admin/pages/{id}/editor
     */
    #[Route('/{id}/editor', method: 'GET', name: 'admin.pages.editor')]
    public function editor(int $id, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        $page = Page::find($id);

        if (!$page) {
            return response('Page not found', 404);
        }

        if (!config('app.debug') && !$user->can('cms.pages.update')) {
            return response('Forbidden', 403);
        }

        return response(view('admin.pages.editor', ['page' => $page]));
    }

    /**
     * Get single page with blocks
     * GET /admin/pages/{id}
     */
    #[Route('/{id}', method: 'GET', name: 'admin.pages.show')]
    public function show(int $id): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.pages.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $page = Page::find($id);

        if (!$page) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        // Get all regions for this page
        $regions = $page->getRegions();

        // Get blocks for each region using new PageBlock structure
        $blocksData = [];
        foreach ($regions as $regionName) {
            $blocks = $page->blocksInRegion($regionName);

            $blocksData[$regionName] = $blocks->map(function ($block) {
                return [
                    'id' => $block->id,
                    'title' => $block->getTitle(),
                    'block_type' => [
                        'id' => $block->blockType->id,
                        'name' => $block->blockType->name,
                        'title' => $block->blockType->title,
                        'icon' => $block->blockType->icon,
                    ],
                    'region' => $block->region,
                    'config' => $block->getConfig(),
                    'sort_order' => $block->sort_order,
                    'visible' => $block->visible,
                ];
            });
        }

        return response()->json([
            'page' => [
                'id' => $page->id,
                'name' => $page->name,
                'slug' => $page->slug,
                'user_id' => $page->user_id,
                'created_at' => $page->created_at,
                'updated_at' => $page->updated_at,
            ],
            'regions' => array_map(fn ($name) => $name, $regions),
            'blocks' => $blocksData,
        ]);
    }

    /**
     * Create new page
     * POST /admin/pages
     */
    #[Route('', method: 'POST', name: 'admin.pages.create')]
    public function create(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.pages.create')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->getParsedBody();

        // Validation
        if (empty($data['name']) || empty($data['slug'])) {
            return response()->json([
                'error' => 'name and slug are required',
            ], 400);
        }

        // Check if slug already exists
        if (Page::where('slug', $data['slug'])->exists()) {
            return response()->json([
                'error' => 'Slug already exists',
            ], 422);
        }

        try {
            $page = new Page();
            $page->name = $data['name'];
            $page->slug = $data['slug'];
            $page->user_id = $user->id;
            $page->save();

            // Auto-create regions for this page
            $this->createPageRegions($page);

            // Apply template if provided
            if (!empty($data['template'])) {
                $this->applyTemplate($page, $data['template'], $user->id);
            }

            $this->clearRouteCache();

            return response()->json([
                'message' => 'Page created successfully',
                'page' => [
                    'id' => $page->id,
                    'name' => $page->name,
                    'slug' => $page->slug,
                    'user_id' => $page->user_id,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create page',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available page templates
     * GET /admin/pages/templates
     */
    #[Route('/templates', method: 'GET', name: 'admin.pages.templates')]
    public function templates(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $templates = \Database\Seeders\PageTemplateSeeder::getTemplates();

        return response()->json([
            'templates' => array_map(function ($key, $template) {
                return [
                    'key' => $key,
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'regions' => $template['regions'],
                    'block_count' => array_sum(array_map('count', $template['blocks'])),
                ];
            }, array_keys($templates), $templates),
        ]);
    }

    /**
     * Update page
     * PUT /admin/pages/{id}
     */
    #[Route('/{id}', method: 'PUT', name: 'admin.pages.update')]
    public function update(int $id, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.pages.update')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $page = Page::find($id);

        if (!$page) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        $data = $request->getParsedBody();

        try {
            if (isset($data['name'])) {
                $page->name = $data['name'];
            }

            if (isset($data['slug'])) {
                // Check if new slug already exists (except current page)
                if (Page::where('slug', $data['slug'])->where('id', '!=', $id)->exists()) {
                    return response()->json([
                        'error' => 'Slug already exists',
                    ], 422);
                }
                $page->slug = $data['slug'];
            }

            $page->save();

            return response()->json([
                'message' => 'Page updated successfully',
                'page' => [
                    'id' => $page->id,
                    'name' => $page->name,
                    'slug' => $page->slug,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update page',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete page
     * DELETE /admin/pages/{id}
     */
    #[Route('/{id}', method: 'DELETE', name: 'admin.pages.delete')]
    public function delete(int $id): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.pages.delete')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $page = Page::find($id);

        if (!$page) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        try {
            // Delete all blocks associated with this page (using new PageBlock structure)
            PageBlock::where('page_id', $page->id)->delete();

            $page->delete();

            // Clear route cache after page deletion
            $this->clearRouteCache();

            return response()->json([
                'message' => 'Page deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete page',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign block to page
     * POST /admin/pages/{pageId}/blocks
     */
    #[Route('/{pageId}/blocks', method: 'POST', name: 'admin.pages.blocks.assign')]
    public function assignBlock(int $pageId, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.pages.update')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $page = Page::find($pageId);

        if (!$page) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        $data = $request->getParsedBody();

        if (!isset($data['block_type_id']) || !isset($data['region']) || $data['region'] === '') {
            return response()->json([
                'error' => 'block_type_id and region are required',
            ], 400);
        }

        try {
            $blockType = BlockType::find($data['block_type_id']);
            if (!$blockType) {
                return response()->json(['error' => 'Block type not found'], 404);
            }

            // Get max sort_order in region for this page
            $maxSortOrder = PageBlock::where('page_id', $page->id)
                ->where('region', $data['region'])
                ->max('sort_order') ?? -1;

            // Create page block (new structure)
            $pageBlock = new PageBlock();
            $pageBlock->page_id = $page->id;
            $pageBlock->block_type_id = $blockType->id;
            $pageBlock->region = $data['region'];
            $pageBlock->content = $data['content'] ?? null;
            $pageBlock->sort_order = $maxSortOrder + 1;
            $pageBlock->visible = $data['visible'] ?? true;
            $pageBlock->created_by = $user->id;
            $pageBlock->save();

            return response()->json([
                'message' => 'Block assigned successfully',
                'block' => [
                    'id' => $pageBlock->id,
                    'title' => $pageBlock->getTitle(),
                    'region' => $pageBlock->region,
                    'sort_order' => $pageBlock->sort_order,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to assign block',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove block from page
     * DELETE /admin/pages/{pageId}/blocks/{blockId}
     */
    #[Route('/{pageId}/blocks/{blockId}', method: 'DELETE', name: 'admin.pages.blocks.remove')]
    public function removeBlock(int $pageId, int $blockId): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.pages.update')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $page = Page::find($pageId);

        if (!$page) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        $block = PageBlock::where('id', $blockId)
            ->where('page_id', $pageId)
            ->first();

        if (!$block) {
            return response()->json(['error' => 'Block not found'], 404);
        }

        try {
            $block->delete();

            return response()->json([
                'message' => 'Block removed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to remove block',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reorder blocks in a region
     * POST /admin/pages/{pageId}/blocks/reorder
     */
    #[Route('/{pageId}/blocks/reorder', method: 'POST', name: 'admin.pages.blocks.reorder')]
    public function reorderBlocks(int $pageId, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.pages.update')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $page = Page::find($pageId);

        if (!$page) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        $data = $request->getParsedBody();

        if (empty($data['blocks']) || !is_array($data['blocks'])) {
            return response()->json([
                'error' => 'blocks array is required',
            ], 400);
        }

        try {
            foreach ($data['blocks'] as $index => $blockId) {
                PageBlock::where('id', $blockId)
                    ->where('page_id', $pageId)
                    ->update(['sort_order' => $index]);
            }

            return response()->json([
                'message' => 'Blocks reordered successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to reorder blocks',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create default regions for a page
     */
    private function createPageRegions(Page $page): void
    {
        $regions = [
            [
                'name' => "page-{$page->slug}-hero",
                'title' => "{$page->name} Hero",
                'description' => "Hero section for {$page->name}",
                'max_blocks' => 1,
            ],
            [
                'name' => "page-{$page->slug}-content",
                'title' => "{$page->name} Content",
                'description' => "Main content for {$page->name}",
                'max_blocks' => 10,
            ],
            [
                'name' => "page-{$page->slug}-sidebar",
                'title' => "{$page->name} Sidebar",
                'description' => "Sidebar for {$page->name}",
                'max_blocks' => 5,
            ],
        ];

        foreach ($regions as $regionData) {
            BlockRegion::firstOrCreate(
                ['name' => $regionData['name']],
                array_merge($regionData, ['is_active' => true]),
            );
        }
    }

    /**
     * Apply template to page
     */
    private function applyTemplate(Page $page, string $templateKey, int $userId): void
    {
        $template = \Database\Seeders\PageTemplateSeeder::getTemplate($templateKey);

        if (!$template) {
            return;
        }

        foreach ($template['blocks'] as $regionKey => $blocks) {
            $regionName = "page-{$page->slug}-{$regionKey}";
            $region = BlockRegion::where('name', $regionName)->first();

            if (!$region) {
                continue;
            }

            $weight = 0;
            foreach ($blocks as $blockData) {
                $blockType = BlockType::where('name', $blockData['type'])->first();

                if (!$blockType) {
                    continue;
                }

                $instance = new BlockInstance();
                $instance->block_type_id = $blockType->id;
                $instance->region_id = $region->id;
                $instance->context_type = 'page';
                $instance->context_id = $page->id;
                $instance->title = $blockType->title;
                $instance->config = $blockData['config'];
                $instance->weight = $weight++;
                $instance->visible = true;
                $instance->visibility_mode = 'show';
                $instance->created_by = $userId;
                $instance->save();
            }
        }
    }

    /**
     * Clear route cache to ensure dynamic routes are updated
     *
     * This is called after page create/delete to ensure the /{slug} route
     * can immediately resolve the new/removed pages.
     */
    private function clearRouteCache(): void
    {
        $cachedPath = base_path('bootstrap/cache/routes.php');
        if (file_exists($cachedPath)) {
            @unlink($cachedPath);
        }

        // Also clear view cache for good measure
        $viewCachePath = base_path('storage/framework/views');
        if (is_dir($viewCachePath)) {
            $files = glob($viewCachePath . '/*.php');
            if ($files) {
                array_map('unlink', $files);
            }
        }
    }
}
