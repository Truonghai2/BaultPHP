<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Middleware;

use Modules\Cms\Infrastructure\Models\Language;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Language Middleware
 *
 * Set application locale based on URL parameter, session, or browser preference
 */
class LanguageMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $locale = $this->determineLocale($request);

        // Set application locale
        app()->setLocale($locale);

        // Store in session for persistence
        if (session()->isStarted()) {
            session()->put('locale', $locale);
        }

        // Add locale to request attributes for easy access
        $request = $request->withAttribute('locale', $locale);

        return $handler->handle($request);
    }

    /**
     * Determine the locale to use
     */
    private function determineLocale(ServerRequestInterface $request): string
    {
        // 1. Check URL parameter (?lang=vi)
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['lang']) && $this->isValidLocale($queryParams['lang'])) {
            return $queryParams['lang'];
        }

        // 2. Check session
        if (session()->isStarted() && session()->has('locale')) {
            $sessionLocale = session()->get('locale');
            if ($this->isValidLocale($sessionLocale)) {
                return $sessionLocale;
            }
        }

        // 3. Check browser Accept-Language header
        $acceptLanguage = $request->getHeaderLine('Accept-Language');
        if ($acceptLanguage) {
            $browserLocale = $this->parseAcceptLanguage($acceptLanguage);
            if ($browserLocale && $this->isValidLocale($browserLocale)) {
                return $browserLocale;
            }
        }

        // 4. Use default locale
        $defaultLang = Language::getDefault();
        return $defaultLang ? $defaultLang->code : config('app.locale', 'en');
    }

    /**
     * Check if locale is valid and active
     */
    private function isValidLocale(string $locale): bool
    {
        static $validLocales = null;

        if ($validLocales === null) {
            $validLocales = Language::active()->pluck('code')->toArray();
        }

        return in_array($locale, $validLocales);
    }

    /**
     * Parse Accept-Language header
     */
    private function parseAcceptLanguage(string $acceptLanguage): ?string
    {
        // Parse: "en-US,en;q=0.9,vi;q=0.8"
        $languages = explode(',', $acceptLanguage);

        foreach ($languages as $language) {
            // Extract language code (ignore quality factor)
            $parts = explode(';', $language);
            $code = trim($parts[0]);

            // Extract primary language code (en-US → en)
            if (str_contains($code, '-')) {
                $code = explode('-', $code)[0];
            }

            if ($this->isValidLocale($code)) {
                return $code;
            }
        }

        return null;
    }
}
