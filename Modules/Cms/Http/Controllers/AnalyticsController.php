<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Controllers;

use Carbon\Carbon;
use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use Core\ORM\DB;
use Modules\Cms\Infrastructure\Models\Page;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Analytics Controller
 * 
 * View site analytics and page views statistics
 */
#[Route(prefix: '/admin/analytics', middleware: ['auth'], group: 'web')]
class AnalyticsController extends Controller
{
    /**
     * Analytics dashboard
     * GET /admin/analytics
     */
    #[Route('', method: 'GET', name: 'admin.analytics.index')]
    public function index(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        if (!config('app.debug') && !$user->can('cms.analytics.view')) {
            return response('Forbidden', 403);
        }

        $queryParams = $request->getQueryParams();
        $period = $queryParams['period'] ?? '30'; // days

        $stats = $this->getStatistics((int)$period);

        return response(view('cms.admin.analytics.index', [
            'stats' => $stats,
            'period' => $period,
        ]));
    }

    /**
     * Get analytics data (API)
     * GET /admin/analytics/api
     */
    #[Route('/api', method: 'GET', name: 'admin.analytics.api')]
    public function apiStats(Request $request): Response
    {
        $queryParams = $request->getQueryParams();
        $period = (int)($queryParams['period'] ?? 30);

        $stats = $this->getStatistics($period);

        return response()->json(['stats' => $stats]);
    }

    /**
     * Page-specific analytics
     * GET /admin/analytics/page/{id}
     */
    #[Route('/page/{id}', method: 'GET', name: 'admin.analytics.page')]
    public function pageStats(int $id): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $page = Page::find($id);

        if (!$page) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        // Get page views (if table exists)
        try {
            $totalViews = DB::table('page_views')
                ->where('page_id', $id)
                ->count();

            $viewsLast30Days = DB::table('page_views')
                ->where('page_id', $id)
                ->where('viewed_at', '>=', Carbon::now()->subDays(30))
                ->count();

            $viewsByDay = DB::table('page_views')
                ->where('page_id', $id)
                ->where('viewed_at', '>=', Carbon::now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->limit(30)
                ->get();

            return response()->json([
                'page' => [
                    'id' => $page->id,
                    'name' => $page->name,
                    'slug' => $page->slug,
                ],
                'stats' => [
                    'total_views' => $totalViews,
                    'views_last_30_days' => $viewsLast30Days,
                    'views_by_day' => $viewsByDay,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'page' => [
                    'id' => $page->id,
                    'name' => $page->name,
                    'slug' => $page->slug,
                ],
                'stats' => [
                    'total_views' => 0,
                    'views_last_30_days' => 0,
                    'views_by_day' => [],
                ],
                'note' => 'Analytics table not yet created',
            ]);
        }
    }

    /**
     * Track page view (Public endpoint)
     * POST /analytics/track
     */
    #[Route('/track', method: 'POST', name: 'analytics.track', middleware: [])]
    public function track(Request $request): Response
    {
        $data = $request->getParsedBody();
        $pageId = $data['page_id'] ?? null;

        if (!$pageId) {
            return response()->json(['error' => 'Page ID required'], 400);
        }

        try {
            DB::table('page_views')->insert([
                'page_id' => $pageId,
                'visitor_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'session_id' => session()->getId() ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'referer' => $_SERVER['HTTP_REFERER'] ?? null,
                'viewed_at' => Carbon::now(),
            ]);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json(['success' => false], 200);
        }
    }

    /**
     * Get statistics
     */
    private function getStatistics(int $days = 30): array
    {
        try {
            $totalPages = Page::count();
            $publishedPages = Page::where('status', 'published')->count();
            $draftPages = Page::where('status', 'draft')->count();

            $totalViews = DB::table('page_views')
                ->count();

            $viewsInPeriod = DB::table('page_views')
                ->where('viewed_at', '>=', Carbon::now()->subDays($days))
                ->count();

            $topPages = DB::table('page_views')
                ->where('viewed_at', '>=', Carbon::now()->subDays($days))
                ->groupBy('page_id')
                ->orderBy('views', 'desc')
                ->limit(10)
                ->get();

            $viewsByDay = DB::table('page_views')
                ->where('viewed_at', '>=', Carbon::now()->subDays($days))
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->limit(30)
                ->get();

            return [
                'total_pages' => $totalPages,
                'published_pages' => $publishedPages,
                'draft_pages' => $draftPages,
                'total_views' => $totalViews,
                'views_in_period' => $viewsInPeriod,
                'top_pages' => $topPages,
                'views_by_day' => $viewsByDay,
                'period_days' => $days,
            ];

        } catch (\Exception $e) {
            return [
                'total_pages' => Page::count(),
                'published_pages' => Page::where('status', 'published')->count(),
                'draft_pages' => Page::where('status', 'draft')->count(),
                'total_views' => 0,
                'views_in_period' => 0,
                'top_pages' => [],
                'views_by_day' => [],
                'period_days' => $days,
                'note' => 'Analytics tables not yet created',
            ];
        }
    }
}

