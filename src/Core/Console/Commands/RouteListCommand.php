<?php

namespace Core\Console\Commands;

use Closure;
use Core\Console\Contracts\BaseCommand;
use Core\Routing\Router;

class RouteListCommand extends BaseCommand
{
    /**
     * The router instance.
     * @var Router
     */
    private Router $router;

    /**
     * Create a new command instance.
     * The Router will be injected by the DI container.
     */
    public function __construct(Router $router)
    {
        parent::__construct();
        $this->router = $router;
    }

    /**
     * The name and signature of the console command.
     */
    public function signature(): string
    {
        return 'route:list';
    }

    /**
     * The console command description.
     */
    public function description(): string
    {
        return 'Display all registered routes.';
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // We assume the Router has a `getRoutes()` method that returns an array of Route objects.
        // This is a standard pattern required for both caching and listing routes.
        $routes = $this->router->getRoutes();

        if (empty($routes)) {
            $this->io->warning('Your application has no routes.');
            return self::SUCCESS;
        }

        $tableHeaders = ['Method', 'URI', 'Name', 'Action'];
        $tableRows = $this->prepareRoutesForTable($routes);

        $this->io->table($tableHeaders, $tableRows);

        return self::SUCCESS;
    }

    /**
     * Prepare the routes for display in a table.
     */
    protected function prepareRoutesForTable(array $routes): array
    {
        $tableRows = [];

        foreach ($routes as $route) {
            // Assuming the $route object has these getter methods.
            $methods = is_array($route->getMethod()) ? implode('|', $route->getMethod()) : $route->getMethod();

            $tableRows[] = [
                $methods,
                $route->getUri(),
                $route->getName() ?? '',
                $this->formatAction($route->getAction()),
            ];
        }

        return $tableRows;
    }

    /**
     * Format the route action for display.
     */
    protected function formatAction(mixed $action): string
    {
        if ($action instanceof Closure) {
            return 'Closure';
        }

        if (is_array($action) && isset($action[0], $action[1])) {
            $controller = is_object($action[0]) ? get_class($action[0]) : $action[0];
            return $controller . '@' . $action[1];
        }

        return is_string($action) ? $action : 'Unroutable';
    }
}
