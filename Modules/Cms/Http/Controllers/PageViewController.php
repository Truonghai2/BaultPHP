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
     * Tối ưu: cache full page cho guest (60s), gộp query lấy page (3 -> 1–2).
     */
    #[Route('/', method: 'GET', name: 'home')]
    public function home(Request $request): Response
    {
        $isGuest = !auth()->check();
        $query = $request->getQueryParams();
        $skipCache = isset($query['nocache']) && (string) $query['nocache'] !== '';

        if ($isGuest && !$skipCache) {
            $cached = cache(null)->get('page.home.guest');
            if ($cached !== null) {
                return response($cached, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
            }
        }

        $baseQuery = function ($query) {
            if (!auth()->check() || !auth()->user()->can('cms.pages.view')) {
                $query->where('status', 'published');
            }
            return $query->with(['blocks' => function ($q) {
                $q->where('visible', true)
                  ->orderBy('sort_order')
                  ->with('blockType');
            }]);
        };

        // Ưu tiên: slug home -> name Home -> first by id (tối đa 3 query; guest đã cache 60s)
        $page = $baseQuery(Page::where('slug', 'home'))->first();
        if (!$page) {
            $page = $baseQuery(Page::where('name', 'Home'))->first();
        }
        if (!$page) {
            $page = $baseQuery(Page::orderBy('id', 'asc'))->first();
        }

        if (!$page) {
            return response(view('welcome'));
        }

        $userRoles = null;
        $isDraft = ($page->status ?? 'published') === 'draft';

        if (auth()->check()) {
            $userRoles = auth()->user()->getRoles() ?? [];
        }

        $view = view('pages.show', [
            'page' => $page,
            'userRoles' => $userRoles,
            'isDraft' => $isDraft,
        ]);

        $html = (string) $view;

        if ($isGuest) {
            cache(null)->put('page.home.guest', $html, 60);
        }

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
