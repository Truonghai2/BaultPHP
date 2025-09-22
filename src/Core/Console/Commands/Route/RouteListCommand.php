<?php

namespace Core\Console\Commands\Route;

use App\Providers\RouteServiceProvider;
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

    private RouteServiceProvider $routeServiceProvider;

    /**
     * Create a new command instance.
     * The Router will be injected by the DI container.
     */
    public function __construct(Router $router, RouteServiceProvider $routeServiceProvider)
    {
        parent::__construct();
        $this->router = $router;
        $this->routeServiceProvider = $routeServiceProvider;
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
        // To ensure the list is always up-to-date and not from a stale cache,
        // we explicitly re-map all routes using the logic from the RouteServiceProvider.
        $this->routeServiceProvider->loadRoutesForCaching($this->router);

        $routes = $this->router->listRoutes();

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
            $tableRows[] = [
                strtoupper($route['method']),
                $route['uri'],
                $route['name'] ?? '',
                $this->formatAction($route['handler'] ?? null),
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
