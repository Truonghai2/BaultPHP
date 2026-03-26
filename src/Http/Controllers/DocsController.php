<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Modules\Cms\Infrastructure\Models\Page;
use Psr\Http\Message\ResponseInterface;

/**
 * Serves documentation (Markdown files from docs/) with a readable layout.
 */
#[Route(group: 'web')]
class DocsController extends Controller
{
    private string $docsPath;

    public function __construct()
    {
        $this->docsPath = base_path('docs');
    }

    /**
     * List all docs and optionally show one. GET /docs or GET /docs?slug=...
     */
    #[Route('/docs', method: 'GET')]
    public function index(): ResponseInterface
    {
        $files = $this->collectDocFiles();
        $slug = request()->getQueryParams()['slug'] ?? null;
        $current = null;
        $content = '';

        if ($slug !== null && $slug !== '' && $this->slugIsSafe($slug)) {
            $path = $this->slugToPath($slug);
            if ($path !== null && is_file($path)) {
                $current = $slug;
                $content = file_get_contents($path);
            }
        }
        if ($current === null && $files !== []) {
            $first = $files[0];
            $current = $first['slug'];
            $path = $this->slugToPath($current);
            if ($path !== null && is_file($path)) {
                $content = file_get_contents($path);
            }
        }

        $docsPage = $this->getDocsPage();
        $userRoles = auth()->check() ? (auth()->user()->getRoles() ?? []) : null;

        return response(view('docs.index', [
            'files' => $files,
            'current' => $current,
            'content' => $content,
            'docsPage' => $docsPage,
            'userRoles' => $userRoles,
        ]));
    }

    /**
     * Show one doc by slug. GET /docs/{slug}
     */
    #[Route('/docs/{slug}', method: 'GET')]
    public function show(string $slug): ResponseInterface
    {
        if (!$this->slugIsSafe($slug)) {
            return response()->redirect('/docs');
        }
        $path = $this->slugToPath($slug);
        if ($path === null || !is_file($path)) {
            return response()->redirect('/docs');
        }
        $files = $this->collectDocFiles();
        $content = file_get_contents($path);
        $docsPage = $this->getDocsPage();
        $userRoles = auth()->check() ? (auth()->user()->getRoles() ?? []) : null;

        return response(view('docs.index', [
            'files' => $files,
            'current' => $slug,
            'content' => $content,
            'docsPage' => $docsPage,
            'userRoles' => $userRoles,
        ]));
    }

    /**
     * @return list<array{slug: string, title: string}>
     */
    private function collectDocFiles(): array
    {
        $list = [];
        if (!is_dir($this->docsPath)) {
            return $list;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->docsPath, \RecursiveDirectoryIterator::SKIP_DOTS | \RecursiveDirectoryIterator::FOLLOW_SYMLINKS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $f) {
            if (!$f->isFile()) {
                continue;
            }
            $name = $f->getFilename();
            if (!str_ends_with(strtolower($name), '.md')) {
                continue;
            }
            $slug = $this->pathToSlug($f->getPathname());
            $title = $this->titleFromFile($f->getPathname(), $name);
            $list[] = ['slug' => $slug, 'title' => $title];
        }
        usort($list, fn ($a, $b) => strcasecmp($a['title'], $b['title']));
        return $list;
    }

    private function titleFromFile(string $path, string $filename): string
    {
        $head = @file_get_contents($path, false, null, 0, 1024);
        if ($head !== false && preg_match('/^#\s+(.+)$/m', $head, $m)) {
            return trim($m[1]);
        }
        return str_replace('.md', '', $filename);
    }

    private function pathToSlug(string $path): string
    {
        $base = str_replace('\\', '/', $this->docsPath);
        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, $base . '/')) {
            $rel = substr($path, strlen($base) + 1);
            return pathinfo($rel, PATHINFO_DIRNAME) === '.'
                ? pathinfo($rel, PATHINFO_FILENAME)
                : str_replace('/', '__', pathinfo($rel, PATHINFO_DIRNAME)) . '__' . pathinfo($rel, PATHINFO_FILENAME);
        }
        return pathinfo($path, PATHINFO_FILENAME);
    }

    private function slugToPath(?string $slug): ?string
    {
        if ($slug === null || $slug === '') {
            return null;
        }
        if (str_contains($slug, '__')) {
            $rel = str_replace('__', '/', $slug) . '.md';
            $path = $this->docsPath . '/' . $rel;
            return is_file($path) ? $path : null;
        }
        $path = $this->docsPath . '/' . $slug . '.md';
        return is_file($path) ? $path : null;
    }

    private function slugIsSafe(string $slug): bool
    {
        return preg_match('/^[a-zA-Z0-9_.-]+$/', $slug) === 1 && !str_contains($slug, '..');
    }

    /**
     * Load CMS Page with slug 'docs' for block regions (hero, content, sidebar).
     */
    private function getDocsPage(): ?Page
    {
        $query = Page::where('slug', 'docs');
        if (!auth()->check() || !auth()->user()->can('cms.pages.view')) {
            $query->where('status', 'published');
        }
        return $query->first();
    }
}
