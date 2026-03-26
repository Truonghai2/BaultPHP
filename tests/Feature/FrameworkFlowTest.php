<?php

namespace Tests\Feature;

use Tests\TestCase;
use Core\Routing\Route;
use Core\Http\Response;

class FrameworkFlowTest extends TestCase
{
    public function test_basic_request_flow()
    {
        // 1. Define a Route properly
        $this->app->make('router')->get('/test-flow', function () {
            return 'Flow Success';
        });

        // 2. Dispatch Request
        $response = $this->get('/test-flow');

        // 3. Assert Response
        $response->assertStatus(200);
        $response->assertSee('Flow Success');
    }

    public function test_route_parameters()
    {
        $this->app->make('router')->get('/user/{id}', function ($id) {
            return "User {$id}";
        });

        $response = $this->get('/user/123');

        $response->assertStatus(200);
        $response->assertSee('User 123');
    }

    public function test_json_response()
    {
        $this->app->make('router')->get('/api/test', function () {
            return ['status' => 'ok', 'data' => [1, 2, 3]];
        });

        $response = $this->get('/api/test');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'data' => [1, 2, 3]
        ]);
    }

    public function test_404_not_found()
    {
        $response = $this->get('/non-existent-route');

        $response->assertStatus(404);
    }
    
    public function test_controller_dispatch()
    {
        // Define a route that uses a controller class
        // We use a full class name string
        $this->app->make('router')->get('/controller', [TestController::class, 'index']);
        
        $response = $this->get('/controller');
        
        $response->assertStatus(200);
        $response->assertSee('Controller Response');
    }
}

// Dummy Controller for testing
class TestController
{
    public function index()
    {
        return 'Controller Response';
    }
}
