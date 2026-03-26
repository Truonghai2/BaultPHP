<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Http\Controllers\SpaPageController;
use Modules\Cms\Infrastructure\Models\Page;
use Modules\User\Infrastructure\Models\User;
use Illuminate\Container\Container;

try {
    echo "Booting application...\n";

    // Set a dummy user in container/auth if possible, or just proceed.
    // Assuming facade usually works if app is booted.
    
    $slug = 'home';
    echo "Searching for published page with slug: '$slug'...\n";
    $page = Page::where('slug', $slug)
            ->where('status', 'published')
            ->where('visible', true)
            ->first();

    if (!$page) {
        echo "❌ Page with slug '$slug' NOT FOUND or NOT PUBLISHED.\n";
        
        // Check if it exists but is not published
        $anyPage = Page::where('slug', $slug)->first();
        if ($anyPage) {
            echo "   Found page '$slug' but status is '{$anyPage->status}' and visible is " . ($anyPage->visible ? 'true' : 'false') . "\n";
        } else {
            echo "   Page '$slug' does not exist in database.\n";
            $allPages = Page::select('slug', 'status')->get();
            echo "   Available pages: " . $allPages->map(fn($p) => "{$p->slug} ({$p->status})")->implode(', ') . "\n";
        }
    } else {
        echo "✅ Found page: {$page->slug} (ID: {$page->id})\n";

        // Resolve Controller
        echo "Resolving SpaPageController...\n";
        // Mock request if controller depends on it for `request()->getParsedBody()` etc, though `show` usually uses args.
        // `show` method uses `auth()->user()`. If no user, it might return filtered content.
        
        $controller = $app->make(SpaPageController::class);

        echo "Calling show({$page->slug})...\n";
        // We need to ensure facade aliases exist if used inside controller
        
        $response = $controller->show($page->slug);

        echo "Response Status: " . $response->getStatusCode() . "\n";
        
        if ($response->getStatusCode() !== 200) {
            echo "❌ API Request Failed\n";
            echo "Body: " . $response->getBody() . "\n";
        } else {
            $data = json_decode($response->getBody(), true);
            echo "✅ API Request Successful\n";
            
            if (isset($data['regions'])) {
                echo "Regions found: " . count($data['regions']) . "\n";
                foreach ($data['regions'] as $regionName => $blocks) {
                    echo " - Region '$regionName': " . count($blocks) . " blocks\n";
                }
            } else {
                echo "⚠️ No regions found in response.\n";
            }
        }
    }

} catch (\Throwable $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
