<?php

namespace Core\Support;

use Illuminate\Support\HtmlString;

class Vite
{
    protected string $buildDirectory = 'build';
    protected ?array $manifest = null;

    /**
     * Get the path to the manifest file.
     */
    protected function manifestPath(): string
    {
        return public_path($this->buildDirectory . '/manifest.json');
    }

    /**
     * Get the Vite manifest file.
     */
    protected function getManifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $manifestPath = $this->manifestPath();

        if (!is_file($manifestPath)) {
            throw new \RuntimeException('Vite manifest not found. Please run "npm run build".');
        }

        return $this->manifest = json_decode(file_get_contents($manifestPath), true);
    }

    /**
     * Generate script and style tags for a Vite asset.
     *
     * @param string|string[] $entrypoints
     * @return \Illuminate\Support\HtmlString
     */
    public function __invoke(string|array $entrypoints): HtmlString
    {
        $entrypoints = (array) $entrypoints;
        $html = '';

        if ($this->isDev()) {
            // Development environment: Point to the Vite dev server
            $html .= '<script type="module" src="' . config('app.vite_dev_url') . '/@vite/client"></script>';
            foreach ($entrypoints as $entry) {
                $html .= '<script type="module" src="' . config('app.vite_dev_url') . '/' . ltrim($entry, '/') . '"></script>';
            }
        } else {
            // Production environment: Use the manifest file
            $manifest = $this->getManifest();

            foreach ($entrypoints as $entry) {
                if (!isset($manifest[$entry])) {
                    throw new \RuntimeException("Unable to locate Vite entrypoint: {$entry}");
                }

                $chunk = $manifest[$entry];

                // KIỂM TRA VÀ INLINE CRITICAL CSS
                if (!empty($chunk['criticalCss'])) {
                    foreach ($chunk['criticalCss'] as $file) {
                        $criticalCssContent = file_get_contents(public_path($this->buildDirectory . '/' . $file));
                        $html .= '<style>' . $criticalCssContent . '</style>';
                    }
                }

                // TẢI CSS CÒN LẠI BẤT ĐỒNG BỘ
                if (!empty($chunk['css'])) {
                    foreach ($chunk['css'] as $file) {
                        $html .= '<link rel="stylesheet" href="' . asset($this->buildDirectory . '/' . $file) . '" media="print" onload="this.media=\'all\'">';
                    }
                    $html .= '<noscript><link rel="stylesheet" href="' . asset($this->buildDirectory . '/' . $chunk['css'][0]) . '"></noscript>';
                }

                // Add the main script tag
                $html .= '<script type="module" src="' . asset($this->buildDirectory . '/' . $chunk['file']) . '"></script>';
            }
        }

        return new HtmlString($html);
    }

    /**
     * Check if the application is in development mode for Vite.
     */
    protected function isDev(): bool
    {
        // Check if the app is in local/development and the Vite dev server URL is set
        // Kiểm tra môi trường 'local' hoặc 'development' để linh hoạt hơn.
        $isDevelopmentEnv = in_array(config('app.env', 'production'), ['local', 'development'], true);

        return $isDevelopmentEnv && !empty(config('app.vite_dev_url'));
    }
}
