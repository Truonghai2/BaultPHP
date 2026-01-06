<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use Modules\Cms\Infrastructure\Models\Language;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Language Controller
 * 
 * Manage languages for multi-language support
 */
#[Route(prefix: '/admin/languages', middleware: ['auth'], group: 'web')]
class LanguageController extends Controller
{
    /**
     * List all languages
     * GET /admin/languages
     */
    #[Route('', method: 'GET', name: 'admin.languages.index')]
    public function index(): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        $languages = Language::orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response(view('admin.languages.index', [
            'languages' => $languages,
        ]));
    }

    /**
     * Get active languages (API)
     * GET /admin/languages/api
     */
    #[Route('/api', method: 'GET', name: 'admin.languages.api')]
    public function apiList(): Response
    {
        $languages = Language::active()->get();

        return response()->json(['languages' => $languages]);
    }

    /**
     * Create language
     * POST /admin/languages
     */
    #[Route('', method: 'POST', name: 'admin.languages.create')]
    public function create(Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!config('app.debug') && !$user->can('cms.languages.create')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->getParsedBody();

        try {
            // Check if code already exists
            if (Language::where('code', $data['code'])->exists()) {
                return response()->json(['error' => 'Language code already exists'], 409);
            }

            $language = Language::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'native_name' => $data['native_name'],
                'is_default' => $data['is_default'] ?? false,
                'is_active' => $data['is_active'] ?? true,
                'direction' => $data['direction'] ?? 'ltr',
            ]);

            // If this is set as default, unset others
            if ($language->is_default) {
                Language::where('id', '!=', $language->id)
                    ->update(['is_default' => false]);
            }

            return response()->json([
                'success' => true,
                'language' => $language,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create language',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update language
     * PUT /admin/languages/{id}
     */
    #[Route('/{id}', method: 'PUT', name: 'admin.languages.update')]
    public function update(int $id, Request $request): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $language = Language::find($id);

        if (!$language) {
            return response()->json(['error' => 'Language not found'], 404);
        }

        $data = $request->getParsedBody();

        try {
            if (isset($data['name'])) $language->name = $data['name'];
            if (isset($data['native_name'])) $language->native_name = $data['native_name'];
            if (isset($data['is_active'])) $language->is_active = $data['is_active'];
            if (isset($data['direction'])) $language->direction = $data['direction'];
            
            if (isset($data['is_default']) && $data['is_default']) {
                // Unset other defaults
                Language::where('id', '!=', $language->id)
                    ->update(['is_default' => false]);
                $language->is_default = true;
            }

            $language->save();

            return response()->json([
                'success' => true,
                'language' => $language,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update language',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete language
     * DELETE /admin/languages/{id}
     */
    #[Route('/{id}', method: 'DELETE', name: 'admin.languages.delete')]
    public function delete(int $id): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $language = Language::find($id);

        if (!$language) {
            return response()->json(['error' => 'Language not found'], 404);
        }

        // Cannot delete default language
        if ($language->is_default) {
            return response()->json(['error' => 'Cannot delete default language'], 403);
        }

        try {
            $language->delete();

            return response()->json([
                'success' => true,
                'message' => 'Language deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete language',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Switch language (Public endpoint)
     * POST /language/switch
     */
    #[Route('/switch', method: 'POST', name: 'language.switch', middleware: [])]
    public function switch(Request $request): Response
    {
        $data = $request->getParsedBody();
        $locale = $data['locale'] ?? $data['lang'] ?? null;

        if (!$locale) {
            return response()->json(['error' => 'Locale not provided'], 400);
        }

        // Validate locale
        $language = Language::findByCode($locale);

        if (!$language || !$language->is_active) {
            return response()->json(['error' => 'Invalid locale'], 400);
        }

        // Store in session
        session()->put('locale', $locale);

        // Set app locale
        app()->setLocale($locale);

        return response()->json([
            'success' => true,
            'locale' => $locale,
            'language' => $language,
        ]);
    }
}

