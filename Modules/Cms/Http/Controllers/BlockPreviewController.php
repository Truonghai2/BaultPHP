<?php

namespace Modules\Cms\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use Modules\Cms\Domain\Services\BlockRegistry;
use Modules\Cms\Domain\Services\BlockRenderer;
use Modules\Cms\Infrastructure\Models\BlockType;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Block Preview Controller
 *
 * Preview blocks before adding them to pages
 */
#[Route(prefix: '/admin/blocks', middleware: ['auth'], group: 'web')]
class BlockPreviewController extends Controller
{
    public function __construct(
        private readonly BlockRegistry $blockRegistry,
        private readonly BlockRenderer $blockRenderer,
    ) {
    }

    /**
     * Preview a block type with configuration
     * POST /admin/blocks/preview
     */
    #[Route('/preview', method: 'POST', name: 'admin.blocks.preview')]
    public function preview(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $data = $request->getParsedBody();

        if (empty($data['block_type_name'])) {
            return response()->json([
                'error' => 'block_type_name is required',
            ], 400);
        }

        try {
            $blockTypeName = $data['block_type_name'];
            $config = $data['config'] ?? [];
            $context = $data['context'] ?? [];

            // Get block from registry
            $block = $this->blockRegistry->getBlock($blockTypeName);

            if (!$block) {
                return response()->json([
                    'error' => 'Block type not found',
                ], 404);
            }

            // Render preview
            $html = $block->render($config, $context);

            // Wrap in preview container
            $previewHtml = $this->wrapPreview($html, $blockTypeName);

            return response()->json([
                'html' => $previewHtml,
                'block_type' => $blockTypeName,
                'config' => $config,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to preview block',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get block type schema for configuration
     * GET /admin/blocks/{name}/schema
     */
    #[Route('/{name}/schema', method: 'GET', name: 'admin.blocks.schema')]
    public function schema(string $name): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        try {
            $block = $this->blockRegistry->getBlock($name);

            if (!$block) {
                return response()->json([
                    'error' => 'Block type not found',
                ], 404);
            }

            $blockType = BlockType::where('name', $name)->first();

            if (!$blockType) {
                return response()->json([
                    'error' => 'Block type not found in database',
                ], 404);
            }

            return response()->json([
                'name' => $block->getName(),
                'title' => $block->getTitle(),
                'description' => $block->getDescription(),
                'category' => $block->getCategory(),
                'icon' => $block->getIcon(),
                'default_config' => $block->getDefaultConfig(),
                'configurable' => $block->isConfigurable(),
                'cacheable' => $block->isCacheable(),
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to get block schema',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Wrap preview HTML in container
     */
    private function wrapPreview(string $html, string $blockType): string
    {
        return sprintf(
            '<div class="block-preview" data-block-type="%s">
                <div class="block-preview-label">Preview: %s</div>
                <div class="block-preview-content">%s</div>
            </div>',
            htmlspecialchars($blockType),
            htmlspecialchars($blockType),
            $html,
        );
    }
}
