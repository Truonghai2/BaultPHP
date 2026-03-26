<?php

namespace Tests\Unit\Container;

use Core\Application;
use Core\Support\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Application::class)]
class ApplicationContainerTest extends TestCase
{
    protected function tearDown(): void
    {
        Context::clear();
        parent::tearDown();
    }

    public function test_make_with_parameters_overrides_constructor_values(): void
    {
        $app = $this->app;

        $instance = $app->make(TestService::class, [
            'name' => 'custom',
            'count' => 3,
        ]);

        $this->assertSame('custom', $instance->name);
        $this->assertSame(3, $instance->count);
    }

    public function test_scoped_returns_same_instance_per_context(): void
    {
        $app = $this->app;
        $app->scoped(ScopedService::class);

        $first = $app->make(ScopedService::class);
        $second = $app->make(ScopedService::class);

        $this->assertSame($first, $second);
    }

    public function test_bind_if_does_not_override_existing_binding(): void
    {
        $app = $this->app;
        $app->bind(InterfaceService::class, PrimaryService::class);
        $app->bindIf(InterfaceService::class, SecondaryService::class);

        $instance = $app->make(InterfaceService::class);

        $this->assertInstanceOf(PrimaryService::class, $instance);
    }

    public function test_rebinding_callback_is_triggered(): void
    {
        $app = $this->app;
        $called = false;

        $app->bind(InterfaceService::class, PrimaryService::class);
        $app->rebinding(InterfaceService::class, function ($container, $instance) use (&$called) {
            $called = true;
            $this->assertInstanceOf(SecondaryService::class, $instance);
        });

        $app->rebind(InterfaceService::class, SecondaryService::class);

        $this->assertTrue($called);
    }
}

class TestService
{
    public function __construct(
        public string $name,
        public int $count = 1
    ) {}
}

class ScopedService
{
}

interface InterfaceService
{
}

class PrimaryService implements InterfaceService
{
}

class SecondaryService implements InterfaceService
{
}
