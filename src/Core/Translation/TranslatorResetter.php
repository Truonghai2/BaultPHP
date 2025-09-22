<?php

namespace Core\Translation;

use Core\Application;
use Core\Contracts\StatefulService;
use Illuminate\Translation\Translator;

/**
 * Resets the translator's locale to the default application locale after each request.
 * This prevents locale state from leaking between requests in a long-running application.
 */
class TranslatorResetter implements StatefulService
{
    public function __construct(private Application $app)
    {
    }

    /**
     * Resets the locale of the translator service.
     */
    public function resetState(): void
    {
        if ($this->app->bound('translator')) {
            /** @var Translator $translator */
            $translator = $this->app->make('translator');
            $defaultLocale = $this->app->make('config')->get('app.locale', 'en');

            $translator->setLocale($defaultLocale);
        }
    }
}
