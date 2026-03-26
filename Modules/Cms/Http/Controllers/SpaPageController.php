<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Controllers;

use Core\Http\Controller;
use Core\Http\Response;
use Modules\Cms\Domain\Services\PageBlockRenderer;
use Modules\Cms\Infrastructure\Models\Page;
use Core\Routing\Attributes\Route;
use Modules\User\Infrastructure\Models\User;

/**
 * SPA Page Controller
 * 
 * Provides JSON API endpoints for SPA navigation
 * Returns pages với rendered blocks
 */
/**
 * SPA Page Controller
 * 
 * Provides JSON API endpoints for SPA navigation
 * Returns pages với rendered blocks
 */
#[Route(prefix: 'api/pages', middleware: ['api'])]
class SpaPageController extends Controller
{
    public function __construct(
        private readonly PageBlockRenderer $blockRenderer
    ) {
    }

    /**
     * List all pages
     * 
     * GET /api/pages
     */
    /**
     * List all pages
     * 
     * GET /api/pages
     */
    #[Route('/', method: 'GET')]
    public function index(): Response
    {
        $pages = Page::where('status', 'published')
            ->where('visible', true)
            ->orderBy('sort_order')
            ->get(['id', 'title', 'slug', 'excerpt', 'status']);

        return response()->json([
            'pages' => $pages->toArray()
        ]);
    }

    /**
     * Get page với tất cả blocks đã rendered
     * 
     * GET /api/pages/{slug}
     */
    /**
     * Get page với tất cả blocks đã rendered
     * 
     * GET /api/pages/{slug}
     */
    #[Route('/{slug}', method: 'GET')]
    public function show(string $slug): Response
    {
        $page = Page::where('slug', $slug)
            ->where('status', 'published')
            ->where('visible', true)
            ->first();

        if (!$page) {
            return response()->json([
                'error' => 'Page not found'
            ], 404);
        }

        // Get current user for visibility checks
        $user = auth()->user();

        // Get all regions used by this page
        $regions = $page->getRegions();

        // Render blocks for each region
        $renderedRegions = [];
        foreach ($regions as $region) {
            $blocks = $page->blocksInRegion($region, $user);
            $renderedBlocks = [];

            foreach ($blocks as $block) {
                $renderedBlocks[] = [
                    'id' => $block->id,
                    'block_type' => $block->blockType?->name ?? 'unknown',
                    'block_type_id' => $block->block_type_id,
                    'region' => $block->region,
                    'sort_order' => $block->sort_order,
                    'content' => $block->content,
                    'rendered_content' => $block->renderOptimized($user),
                    'css_classes' => $block->blockType?->css_classes ?? []
                ];
            }

            $renderedRegions[$region] = $renderedBlocks;
        }

        return response()->json([
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'excerpt' => $page->excerpt,
                'content' => $page->content,
                'status' => $page->status,
                'template' => $page->template
            ],
            'regions' => $renderedRegions,
            'meta' => [
                'title' => $page->meta_title ?? $page->title,
                'description' => $page->meta_description ?? $page->excerpt,
                'keywords' => $page->meta_keywords
            ]
        ]);
    }

    /**
     * Get blocks for a specific region
     * 
     * GET /api/pages/{pageId}/blocks/{region}
     */
    /**
     * Get blocks for a specific region
     * 
     * GET /api/pages/{pageId}/blocks/{region}
     */
    #[Route('/{pageId}/blocks/{region}', method: 'GET')]
    public function regionBlocks(int $pageId, string $region): Response
    {
        $page = Page::find($pageId);

        if (!$page) {
            return response()->json([
                'error' => 'Page not found'
            ], 404);
        }

        $user = auth()->user();
        $blocks = $page->blocksInRegion($region, $user);

        $renderedBlocks = [];
        foreach ($blocks as $block) {
            $renderedBlocks[] = [
                'id' => $block->id,
                'block_type' => $block->blockType?->name ?? 'unknown',
                'block_type_id' => $block->block_type_id,
                'region' => $block->region,
                'sort_order' => $block->sort_order,
                'content' => $block->content,
                'rendered_content' => $block->renderOptimized($user),
                'css_classes' => $block->blockType?->css_classes ?? []
            ];
        }

        return response()->json([
            'blocks' => $renderedBlocks
        ]);
    }

    /**
     * Add block to page
     * 
     * POST /api/pages/{pageId}/blocks
     */
    /**
     * Add block to page
     * 
     * POST /api/pages/{pageId}/blocks
     */
    #[Route('/{pageId}/blocks', method: 'POST')]
    public function addBlock(int $pageId): Response
    {
        $user = auth()->user();

        if (!$user || !$user->can('cms.blocks.manage')) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        $page = Page::find($pageId);

        if (!$page) {
            return response()->json([
                'error' => 'Page not found'
            ], 404);
        }

        $data = request()->getParsedBody();

        if (!isset($data['block_type_id']) || !isset($data['region'])) {
            return response()->json([
                'error' => 'block_type_id and region are required'
            ], 400);
        }

        try {
            $blockType = \Modules\Cms\Infrastructure\Models\BlockType::find($data['block_type_id']);
            
            if (!$blockType) {
                return response()->json([
                    'error' => 'Block type not found'
                ], 404);
            }

            // Get max sort_order for this region
            $maxSortOrder = \Modules\Cms\Infrastructure\Models\PageBlock::where('page_id', $page->id)
                ->where('region', $data['region'])
                ->max('sort_order') ?? -1;

            // Create page block
            $pageBlock = new \Modules\Cms\Infrastructure\Models\PageBlock();
            $pageBlock->page_id = $page->id;
            $pageBlock->block_type_id = $blockType->id;
            $pageBlock->region = $data['region'];
            $pageBlock->content = $data['content'] ?? null;
            $pageBlock->sort_order = $maxSortOrder + 1;
            $pageBlock->visible = $data['visible'] ?? true;
            $pageBlock->created_by = $user->id;
            $pageBlock->save();

            return response()->json([
                'success' => true,
                'block' => [
                    'id' => $pageBlock->id,
                    'block_type_id' => $pageBlock->block_type_id,
                    'region' => $pageBlock->region,
                    'sort_order' => $pageBlock->sort_order
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to add block: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update block
     * 
     * PUT /api/pages/blocks/{blockId}
     */
    /**
     * Update block
     * 
     * PUT /api/pages/blocks/{blockId}
     */
    #[Route('/blocks/{blockId}', method: 'PUT')]
    public function updateBlock(int $blockId): Response
    {
        $user = auth()->user();

        if (!$user || !$user->can('cms.blocks.manage')) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        $block = \Modules\Cms\Infrastructure\Models\PageBlock::find($blockId);

        if (!$block) {
            return response()->json([
                'error' => 'Block not found'
            ], 404);
        }

        $data = request()->getParsedBody();

        try {
            if (isset($data['content'])) {
                $block->content = $data['content'];
            }

            if (isset($data['visible'])) {
                $block->visible = (bool)$data['visible'];
            }

            if (isset($data['sort_order'])) {
                $block->sort_order = (int)$data['sort_order'];
            }

            $block->save();

            return response()->json([
                'success' => true,
                'block' => [
                    'id' => $block->id,
                    'content' => $block->content,
                    'visible' => $block->visible,
                    'sort_order' => $block->sort_order
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to update block: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete block
     * 
     * DELETE /api/pages/blocks/{blockId}
     */
    /**
     * Delete block
     * 
     * DELETE /api/pages/blocks/{blockId}
     */
    #[Route('/blocks/{blockId}', method: 'DELETE')]
    public function deleteBlock(int $blockId): Response
    {
        $user = auth()->user();

        if (!$user || !$user->can('cms.blocks.manage')) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        $block = \Modules\Cms\Infrastructure\Models\PageBlock::find($blockId);

        if (!$block) {
            return response()->json([
                'error' => 'Block not found'
            ], 404);
        }

        try {
            $block->delete();

            return response()->json([
                'success' => true
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to delete block: ' . $e->getMessage()
            ], 500);
        }
    }
}
