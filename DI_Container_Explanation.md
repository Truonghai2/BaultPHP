# Dependency Injection Container in BaultPHP

## Introduction: What is a DI Container?

The Dependency Injection (DI) Container (also known as an IoC Container - Inversion of Control) is one of the core and most powerful components of modern frameworks, including BaultPHP. Fundamentally, it is an object responsible for managing the instantiation and dependencies of other objects (classes) in your application.

Instead of manually creating an object and all the objects it needs inside its constructor:

```php
// Manual way (Without DI)
class MyController {
    private $service;
    public function __construct() {
        // Manual instantiation, very rigid and hard to change
        $config = ['key' => 'value'];
        $this->service = new MyService($config);
    }
}
```

You just need to "declare" what you need, and the DI Container will automatically provide it for you:

```php
// The DI way
class MyController {
    // Just declare the service you need in the constructor
    public function __construct(private MyService $service) {
        // The container will automatically inject an instance of MyService here
    }
}
```

This provides significant benefits:

- **Loose Coupling:** Classes don't need to know how to create their dependencies.
- **Easy Configuration:** Configuration for services is centralized in a single place.
- **Increased Testability:** It's easy to replace real services with mock objects when writing unit tests.

## How it Works in BaultPHP

In BaultPHP, the heart of the DI system is the `Core\Application` class. This class acts as a global "registry" where you can "teach" it how to create different services. This process consists of two main steps: **Binding** and **Resolving**.

### 1. Binding Services with a Service Provider

A **Service Provider** is the central place to register all your services with the DI Container. BaultPHP automatically loads all Service Providers defined in the application and its modules.

Registration is done inside the `register()` method of a Service Provider.

**Example: Binding `CentrifugoAPIService` in `AppServiceProvider.php`**

```php
// e:\temp\BaultPHP\src\Providers\AppServiceProvider.php

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ...

        // Register CentrifugoAPIService as a singleton.
        $this->app->singleton(CentrifugoAPIService::class, function () {
            $apiUrl = $_ENV['CENTRIFUGO_API_URL'] ?? 'http://127.0.0.1:8000';
            $apiKey = $_ENV['CENTRIFUGO_API_KEY'] ?? null;

            if (is_null($apiKey)) {
                throw new \InvalidArgumentException('...');
            }

            // Return a new instance of the service
            return new CentrifugoAPIService($apiUrl, $apiKey);
        });
    }
}
```

In the example above:

- `$this->app` is the instance of `Core\Application` (the DI Container).
- `singleton(CentrifugoAPIService::class, ...)` tells the container: "When someone requests a `CentrifugoAPIService`, execute this closure. But only execute it the first time; for subsequent requests, return the same instance that was already created".
- All the initialization and configuration logic (getting the URL, API key from `.env`) is encapsulated here.

### 2. Resolving Dependencies

This is the process where the container "provides" the registered objects. BaultPHP primarily uses automatic **Constructor Injection**.

**Example: Injecting `CentrifugoAPIService` into `NotificationController.php`**

```php
// e:\temp\BaultPHP\src\Http\Controllers\Admin\NotificationController.php

class NotificationController
{
    // BaultPHP will automatically "look" in here
    public function __construct(private CentrifugoAPIService $centrifugo)
    {
        // The container will automatically find and inject the instance of CentrifugoAPIService
        // that was registered in the AppServiceProvider into the $centrifugo variable.
    }

    public function sendToUser(...)
    {
        // Now you can use the service freely
        $this->centrifugo->publish(...);
    }
}
```

**The workflow is as follows:**

1.  A request comes to the `/api/admin/notifications/user/{id}` route.
2.  The BaultPHP Router determines that it needs to create an instance of `NotificationController` to handle the request.
3.  Before creating it, it uses Reflection to "read" the constructor of `NotificationController`.
4.  It sees that the constructor requires a parameter with the type-hint `CentrifugoAPIService`.
5.  It asks the DI Container (`$app`): "How do I create a `CentrifugoAPIService`?"
6.  The container replies: "Ah, I was taught how to create it in the `AppServiceProvider`. Here is its singleton instance."
7.  The container returns the instance of `CentrifugoAPIService`.
8.  The framework injects that instance into the constructor of `NotificationController` and completes the controller's instantiation.

## Conclusion

### `bind` vs `singleton`: The Key Difference

In BaultPHP, both the `bind` and `singleton` methods are used to "teach" the container how to create an object. The core difference lies in the **lifecycle** of the created object.

#### `bind` (Transient Binding)

When you use `bind`, you are telling the container: "Every time someone requests this service, create a brand new instance for them".

```php
// In a ServiceProvider
$this->app->bind(ReportGenerator::class, function() {
    return new ReportGenerator(new TemporaryFileStorage());
});

// Elsewhere in the application
$report1 = $app->make(ReportGenerator::class); // Create instance A
$report2 = $app->make(ReportGenerator::class); // Create instance B

// $report1 and $report2 are two completely different objects.
```

- **When to use `bind`?** When you need a "fresh state" object every time it's used. For example: a report generation class, a Data Transfer Object (DTO), or any class with internal state that you don't want to share between different parts of the application.

#### `singleton` (Shared Binding)

When you use `singleton`, you are telling the container: "Create an instance of this service the first time it is requested. Then, for all subsequent requests, return that exact same created instance".

```php
// In a ServiceProvider
$this->app->singleton(DatabaseConnection::class, function() {
    // Expensive database connection logic only runs once
    return new DatabaseConnection($_ENV['DB_DSN']);
});

// Elsewhere in the application
$connection1 = $app->make(DatabaseConnection::class); // Create instance A and save it
$connection2 = $app->make(DatabaseConnection::class); // Return the saved instance A

// $connection1 and $connection2 are the same object.
```

- **When to use `singleton`?** This is the most common case. Use it for services that are stateless or have a state that needs to be shared globally, and whose instantiation is resource-intensive. Examples: Database connections, external API clients (`CentrifugoAPIService`), cache management services, config management services. This helps save memory and processing time.

---

BaultPHP's Dependency Injection system, while simple, is an extremely effective tool. By centralizing instantiation and configuration in **Service Providers** and leveraging automatic **Constructor Injection**, it helps your source code become:

- **Clean and readable:** Controllers and other business logic classes focus only on their own tasks.
- **Flexible:** It's easy to change how a service is created without having to modify code in multiple places.
- **Easy to maintain and test.**

This is a foundational concept that helps in building large, complex, and highly maintainable applications in BaultPHP.
