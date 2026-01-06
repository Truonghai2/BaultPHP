<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use Modules\Cms\Infrastructure\Models\BlockType;
use Modules\Cms\Infrastructure\Models\PageTemplate;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Page Template Controller
 *
 * Manage page templates
 */
#[Route(prefix: '/admin/templates', middleware: ['auth'], group: 'web')]
class PageTemplateController extends Controller
{
    /**
     * List all templates
     * GET /admin/templates
     */
    #[Route('', method: 'GET', name: 'admin.templates.index')]
    public function index(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        $queryParams = $request->getQueryParams();
        $category = $queryParams['category'] ?? 'all';

        $query = PageTemplate::query();

        if ($category !== 'all') {
            $query->where('category', $category);
        }

        $templates = $query->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Get categories
        $categories = PageTemplate::select('category')
            ->distinct()
            ->pluck('category')
            ->toArray();

        return response(view('admin.templates.index', [
            'templates' => $templates,
            'categories' => $categories,
            'currentCategory' => $category,
        ]));
    }

    /**
     * Get templates as JSON (for page creation)
     * GET /admin/templates/api
     */
    #[Route('/api', method: 'GET', name: 'admin.templates.api')]
    public function apiList(Request $request): Response
    {
        $queryParams = $request->getQueryParams();
        $category = $queryParams['category'] ?? null;

        $query = PageTemplate::query()->where('is_active', true);

        if ($category) {
            $query->where('category', $category);
        }

        $templates = $query->orderBy('sort_order')->get();

        return response()->json(['templates' => $templates]);
    }

    /**
     * Get template details
     * GET /admin/templates/{id}
     */
    #[Route('/{id}', method: 'GET', name: 'admin.templates.show')]
    public function show(int $id): Response
    {
        $template = PageTemplate::find($id);

        if (!$template) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        // Resolve block type IDs from names
        $blocksConfig = $template->blocks_config;

        if ($blocksConfig) {
            foreach ($blocksConfig as &$blockConfig) {
                if (isset($blockConfig['block_type_name'])) {
                    $blockType = BlockType::where('name', $blockConfig['block_type_name'])->first();
                    if ($blockType) {
                        $blockConfig['block_type_id'] = $blockType->id;
                        $blockConfig['block_type_title'] = $blockType->title;
                    }
                }
            }
        }

        return response()->json([
            'template' => array_merge($template->toArray(), [
                'blocks_config' => $blocksConfig,
            ]),
        ]);
    }

    /**
     * Create new template
     * POST /admin/templates
     */
    #[Route('', method: 'POST', name: 'admin.templates.create')]
    public function create(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.templates.create')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->getParsedBody();

        try {
            $template = PageTemplate::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? 'general',
                'thumbnail' => $data['thumbnail'] ?? null,
                'blocks_config' => $data['blocks_config'] ?? [],
                'default_seo' => $data['default_seo'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'is_system' => false,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'template' => $template,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create template',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update template
     * PUT /admin/templates/{id}
     */
    #[Route('/{id}', method: 'PUT', name: 'admin.templates.update')]
    public function update(int $id, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $template = PageTemplate::find($id);

        if (!$template) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        // Cannot edit system templates
        if ($template->is_system && !config('app.debug')) {
            return response()->json(['error' => 'Cannot edit system templates'], 403);
        }

        $data = $request->getParsedBody();

        try {
            if (isset($data['name'])) {
                $template->name = $data['name'];
            }
            if (isset($data['description'])) {
                $template->description = $data['description'];
            }
            if (isset($data['category'])) {
                $template->category = $data['category'];
            }
            if (isset($data['thumbnail'])) {
                $template->thumbnail = $data['thumbnail'];
            }
            if (isset($data['blocks_config'])) {
                $template->blocks_config = $data['blocks_config'];
            }
            if (isset($data['default_seo'])) {
                $template->default_seo = $data['default_seo'];
            }
            if (isset($data['is_active'])) {
                $template->is_active = $data['is_active'];
            }
            if (isset($data['sort_order'])) {
                $template->sort_order = $data['sort_order'];
            }

            $template->save();

            return response()->json([
                'success' => true,
                'template' => $template,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update template',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete template
     * DELETE /admin/templates/{id}
     */
    #[Route('/{id}', method: 'DELETE', name: 'admin.templates.delete')]
    public function delete(int $id): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $template = PageTemplate::find($id);

        if (!$template) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        // Cannot delete system templates
        if ($template->is_system) {
            return response()->json(['error' => 'Cannot delete system templates'], 403);
        }

        try {
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete template',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
