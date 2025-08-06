<?php

namespace Modules\GraphQL\Providers;

use Core\Support\ServiceProvider;
use GraphQL\Error\DebugFlag;
use Psr\Container\ContainerInterface;
use TheCodingMachine\GraphQLite\Schema;
use TheCodingMachine\GraphQLite\SchemaFactory;

class GraphQLServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Đăng ký SchemaFactory, class chịu trách nhiệm xây dựng schema
        $this->app->singleton(SchemaFactory::class, function (ContainerInterface $container) {
            $factory = new SchemaFactory(
                // Dùng cache để tăng tốc trong môi trường production
                $container->get('cache'),
                $container,
            );

            // Lấy cấu hình từ file config/graphqlite.php
            $config = $this->app->make('config')->get('graphqlite');

            // "Dạy" cho factory biết nơi tìm các Query, Mutation, và Type
            foreach ($config['namespaces']['controllers'] ?? [] as $namespace) {
                $factory->addControllerNamespace($namespace);
            }
            foreach ($config['namespaces']['types'] ?? [] as $namespace) {
                $factory->addTypeNamespace($namespace);
            }

            // Bật chế độ debug nếu APP_DEBUG=true
            if ($this->app->make('config')->get('app.debug')) {
                $factory->setDebug(DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE);
            }

            return $factory;
        });

        // Đăng ký Schema như một singleton.
        // SchemaFactory sẽ được inject tự động và chỉ chạy một lần.
        $this->app->singleton(Schema::class, function (ContainerInterface $container) {
            $factory = $container->get(SchemaFactory::class);
            return $factory->createSchema();
        });
    }
}
