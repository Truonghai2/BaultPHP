<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Modules\Cms\Infrastructure\Models\Page;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Sitemap Controller
 * 
 * Automatically generates sitemap.xml with all published pages
 */
#[Route(prefix: '', middleware: [], group: 'web')]
class SitemapController extends Controller
{
    /**
     * Generate sitemap.xml
     * GET /sitemap.xml
     */
    #[Route('/sitemap.xml', method: 'GET', name: 'sitemap')]
    public function index(Request $request): Response
    {
        $baseUrl = $request->getUri()->getScheme() . '://' . $request->getUri()->getHost();
        
        // Get all pages
        $pages = Page::all();
        
        // Generate XML
        $xml = $this->generateXml($pages, $baseUrl);
        
        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600', // Cache for 1 hour
        ]);
    }
    
    /**
     * Generate sitemap XML
     */
    private function generateXml($pages, string $baseUrl): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
        
        // Homepage (highest priority)
        $xml .= '  <url>' . PHP_EOL;
        $xml .= '    <loc>' . htmlspecialchars($baseUrl . '/') . '</loc>' . PHP_EOL;
        $xml .= '    <changefreq>daily</changefreq>' . PHP_EOL;
        $xml .= '    <priority>1.0</priority>' . PHP_EOL;
        $xml .= '  </url>' . PHP_EOL;
        
        // All pages
        foreach ($pages as $page) {
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($baseUrl . '/' . $page->slug) . '</loc>' . PHP_EOL;
            
            // Last modified date
            if ($page->updated_at) {
                $xml .= '    <lastmod>' . $page->updated_at->format('Y-m-d') . '</lastmod>' . PHP_EOL;
            }
            
            // Change frequency
            $xml .= '    <changefreq>weekly</changefreq>' . PHP_EOL;
            
            // Priority based on slug
            $priority = match ($page->slug) {
                'home' => '1.0',
                'about-us', 'about' => '0.9',
                'contact' => '0.8',
                default => '0.7'
            };
            $xml .= '    <priority>' . $priority . '</priority>' . PHP_EOL;
            
            $xml .= '  </url>' . PHP_EOL;
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }
    
    /**
     * Ping search engines about sitemap update
     * (Called automatically after page create/delete)
     */
    public function pingSitemapUpdate(): void
    {
        $baseUrl = config('app.url', 'http://localhost');
        $sitemapUrl = $baseUrl . '/sitemap.xml';
        
        // Ping Google
        try {
            @file_get_contents('http://www.google.com/ping?sitemap=' . urlencode($sitemapUrl));
        } catch (\Exception $e) {
            // Silent fail - not critical
        }
        
        // Ping Bing
        try {
            @file_get_contents('http://www.bing.com/ping?sitemap=' . urlencode($sitemapUrl));
        } catch (\Exception $e) {
            // Silent fail - not critical
        }
    }
}

