<?php

namespace Core\Logging;

use Core\Application;

class RequestProcessor
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function __invoke(array $record): array
    {
        if ($this->app->bound('request_id')) {
            $record['extra']['request_id'] = $this->app->make('request_id');
        }

        return $record;
    }
}
