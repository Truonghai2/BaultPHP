<?php

namespace Modules\Cms\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Modules\Cms\Domain\Services\PageBlockRenderer;
use Modules\Cms\Infrastructure\Models\Page;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Page View Controller
 *
 * Public-facing controller for displaying pages with their blocks
 */
#[Route(prefix: '', middleware: [], group: 'web')]
class PageViewController extends Controller
{
    public function __construct(
        private readonly PageBlockRenderer $pageBlockRenderer,
    ) {
    }

    /**
     * Display a page by slug
     * GET /{slug}
     */
    #[Route('/{slug}', method: 'GET', name: 'page.show')]
    public function show(string $slug): Response
    {
        $query = Page::where('slug', $slug);

        if (!auth()->check() || !auth()->user()->can('cms.pages.view')) {
            $query->where('status', 'published');
        }

        $page = $query->with(['blocks' => function ($query) {
            $query->where('visible', true)
                  ->orderBy('sort_order')
                  ->with('blockType');
        }])
            ->first();

        if (!$page) {
            return response('Page not found', 404);
        }

        $userRoles = null;
        $isDraft = ($page->status ?? 'published') === 'draft';

        if (auth()->check()) {
            $userRoles = auth()->user()->getRoles() ?? [];
        }

        return response(view('pages.show', [
            'page' => $page,
            'userRoles' => $userRoles,
            'isDraft' => $isDraft,
        ]));
    }

    /**
     * Homepage
     * GET /
     */
    #[Route('/', method: 'GET', name: 'home')]
    public function home(Request $request): Response
    {
        $queryBuilder = function ($query) {
            if (!auth()->check() || !auth()->user()->can('cms.pages.view')) {
                $query->where('status', 'published');
            }
            return $query->with(['blocks' => function ($q) {
                $q->where('visible', true)
                  ->orderBy('sort_order')
                  ->with('blockType');
            }]);
        };

        $page = $queryBuilder(Page::where('slug', 'home'))->first();

        if (!$page) {
            $page = $queryBuilder(Page::where('name', 'Home'))->first();
        }

        if (!$page) {
            $page = $queryBuilder(Page::orderBy('id', 'asc'))->first();
        }

        if (!$page) {
            return response(view('welcome'));
        }

        $userRoles = null;
        $isDraft = ($page->status ?? 'published') === 'draft';

        if (auth()->check()) {
            $userRoles = auth()->user()->getRoles() ?? [];
        }

        return response(view('pages.show', [
            'page' => $page,
            'userRoles' => $userRoles,
            'isDraft' => $isDraft,
        ]));
    }
}
