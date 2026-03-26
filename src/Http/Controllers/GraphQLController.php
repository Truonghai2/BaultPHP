<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\ResponseFactory;
use Core\Routing\Attributes\Route;
use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TheCodingMachine\GraphQLite\Context\Context;

/**
 * GraphQL Controller
 *
 * Handles GraphQL queries, mutations, and subscriptions.
 */
class GraphQLController
{
    public function __construct(
        private readonly Schema $schema,
        private readonly ResponseFactory $responseFactory,
    ) {
    }

    /**
     * Handle GraphQL POST requests
     */
    #[Route('/graphql', method: 'POST')]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $body = $this->parseRequestBody($request);
            $query = $body['query'] ?? null;
            $variables = $body['variables'] ?? [];
            $operationName = $body['operationName'] ?? null;

            if (!$query) {
                return $this->responseFactory->json([
                    'errors' => [
                        ['message' => 'GraphQL query is required'],
                    ],
                ], 400);
            }

            // Create context from request
            $context = $this->createContext($request);

            // Execute GraphQL query
            $result = GraphQL::executeQuery(
                $this->schema,
                $query,
                null,
                $context,
                $variables,
                $operationName
            );

            // Get debug flag based on app debug setting
            $debugFlag = config('app.debug', false) 
                ? DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE
                : DebugFlag::NONE;

            $output = $result->toArray($debugFlag);

            $statusCode = !empty($output['errors']) ? 400 : 200;

            return $this->responseFactory->json($output, $statusCode);

        } catch (\Throwable $e) {
            return $this->responseFactory->json([
                'errors' => [
                    [
                        'message' => $e->getMessage(),
                        'extensions' => config('app.debug', false) ? [
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                        ] : [],
                    ],
                ],
            ], 500);
        }
    }

    /**
     * Handle GraphQL GET requests (for GraphiQL/Playground)
     */
    #[Route('/graphql', method: 'GET')]
    public function playground(): ResponseInterface
    {
        // Return GraphiQL HTML interface
        $html = $this->getGraphiQLHtml();

        return $this->responseFactory->make($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    /**
     * Parse request body
     */
    protected function parseRequestBody(ServerRequestInterface $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');
        $body = (string) $request->getBody();

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON in request body');
            }
            return $decoded;
        }

        // Handle form-data or URL-encoded
        if (str_contains($contentType, 'application/x-www-form-urlencoded') || 
            str_contains($contentType, 'multipart/form-data')) {
            parse_str($body, $parsed);
            if (isset($parsed['query'])) {
                return [
                    'query' => $parsed['query'],
                    'variables' => isset($parsed['variables']) 
                        ? json_decode($parsed['variables'], true) ?? []
                        : [],
                    'operationName' => $parsed['operationName'] ?? null,
                ];
            }
        }

        return [];
    }

    /**
     * Create GraphQL context from request
     */
    protected function createContext(ServerRequestInterface $request): Context
    {
        $context = new Context();

        // Add request to context
        $context->setRequest($request);

        // Add user if authenticated
        // You can extend this to add authentication info
        // $user = $request->getAttribute('user');
        // if ($user) {
        //     $context->setUser($user);
        // }

        return $context;
    }

    /**
     * Get GraphiQL HTML interface
     */
    protected function getGraphiQLHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>GraphiQL</title>
    <link rel="stylesheet" href="https://unpkg.com/graphiql@3/graphiql.min.css" />
    <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/graphiql@3/graphiql.min.js"></script>
</head>
<body style="margin: 0;">
    <div id="graphiql" style="height: 100vh;"></div>
    <script>
        const fetcher = GraphiQL.createFetcher({
            url: '/graphql',
        });
        ReactDOM.render(
            React.createElement(GraphiQL, { fetcher }),
            document.getElementById('graphiql')
        );
    </script>
</body>
</html>
HTML;
    }
}
