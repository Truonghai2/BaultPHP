<?php

namespace Modules\GraphQL\Http\Controllers;

use Core\Routing\Attributes\Route;
use GraphQL\Server\StandardServer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TheCodingMachine\GraphQLite\Schema;
use TheCodingMachine\GraphQLite\Server\Psr15Psr7Bridge;

class GraphQLController
{
    private Psr15Psr7Bridge $bridge;

    /**
     * Inject Schema đã được đăng ký trong GraphQLServiceProvider.
     */
    public function __construct(Schema $schema)
    {
        // StandardServer của webonyx/graphql-php xử lý các request GraphQL.
        $server = new StandardServer(['schema' => $schema]);
        $this->bridge = new Psr15Psr7Bridge($server, new Psr17Factory());
    }

    #[Route('/graphql', method: 'GET')]
    #[Route('/graphql', method: 'POST')]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Vì $request đã là một đối tượng PSR-7, chúng ta có thể sử dụng nó trực tiếp.
        $psr7Response = $this->bridge->handle($request);

        return $psr7Response;
    }
}
