# Container Service Upgrades - Summary

## Overview

This upgrade improves the service container with better lifecycle control,
parameter overrides, scoped bindings, and rebinding hooks. These changes make
the container safer for long-running workers and more flexible for testing.

## What's New

### 1. Parameter Overrides in `make()`

You can now pass constructor overrides by name:

```php
$service = app()->make(ReportService::class, [
    'name' => 'custom',
    'count' => 3,
]);
```

### 2. Scoped Bindings (per request/coroutine)

```php
app()->scoped(ScopedService::class);

$a = app()->make(ScopedService::class);
$b = app()->make(ScopedService::class);

// Same instance within the same request/coroutine
```

### 3. Conditional Bindings

```php
app()->bindIf(CacheStore::class, FileStore::class);
app()->singletonIf(Logger::class, LogManager::class);
```

### 4. Rebinding Hooks

```php
app()->rebinding(Logger::class, function ($app, $logger) {
    // React to updated binding
});

app()->rebind(Logger::class, NewLogger::class);
```

### 5. Container Reset Helpers

```php
app()->forgetInstances([
    CacheStore::class,
    Logger::class,
]);

app()->flush(); // Clear all resolved instances
```

## Tests Added

- `tests/Unit/Container/ApplicationContainerTest.php`
  - Parameter overrides
  - Scoped bindings
  - bindIf behavior
  - rebinding callbacks

## Notes

- Scoped bindings use `Core\Support\Context` when available.
- `flush()` only clears resolved instances, not bindings.
- Rebinding callbacks are fired when instances are replaced or re-bound.
