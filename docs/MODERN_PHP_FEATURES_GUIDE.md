# Modern PHP Features Guide

## Tổng quan

Hệ thống Modern PHP Features đã được triển khai với:

1. **PHP 8.3+ Features Support** - Readonly classes, Typed constants, Override attribute
2. **PHP Attributes Enhancement** - Rich metadata, code generation, runtime optimization

## 1. PHP 8.3+ Features Support

### Cấu hình

Thêm vào `.env`:
```env
PHP83_FEATURES_ENABLED=true
PHP83_READONLY_CLASSES=true
PHP83_TYPED_CONSTANTS=true
PHP83_OVERRIDE_ATTRIBUTE=true
PHP83_DYNAMIC_CONSTANTS=true
```

### Features

- ✅ **Readonly Classes** - Enhanced immutability
- ✅ **Typed Class Constants** - Better type safety
- ✅ **Override Attribute** - Explicit overrides
- ✅ **Dynamic Class Constants** - More flexibility
- ✅ **Readonly Properties** - Immutable properties

### Sử dụng

#### Check Readonly Class

```php
use Core\Attributes\PHP83Features;

$features = app(PHP83Features::class);

// Check if class is readonly
if ($features->isReadonlyClass(User::class)) {
    echo "User class is readonly\n";
}

// Check if property is readonly
if ($features->isReadonlyProperty(User::class, 'email')) {
    echo "Email property is readonly\n";
}

// Validate readonly class
$validation = $features->validateReadonlyClass(User::class);
if ($validation['valid']) {
    echo "Readonly class is valid\n";
} else {
    echo "Violations: " . implode(', ', $validation['violations']) . "\n";
}
```

#### Get Typed Constants

```php
// Get typed class constants
$typedConstants = $features->getTypedConstants(Config::class);

// Returns:
// [
//     'MAX_SIZE' => [
//         'value' => 1000,
//         'type' => 'int',
//     ],
//     'DEFAULT_NAME' => [
//         'value' => 'default',
//         'type' => 'string',
//     ],
// ]
```

#### Check Override Attribute

```php
// Check if method has Override attribute
if ($features->hasOverrideAttribute(ChildClass::class, 'parentMethod')) {
    echo "Method has Override attribute\n";
}
```

#### Get Class Features Summary

```php
$summary = $features->getClassFeatures(User::class);

// Returns:
// [
//     'readonly' => true,
//     'typed_constants' => [...],
//     'dynamic_constants' => [...],
//     'php_version' => '8.3.0',
//     'features_available' => [
//         'readonly_classes' => true,
//         'readonly_properties' => true,
//         'typed_constants' => true,
//         'override_attribute' => true,
//         'dynamic_constants' => true,
//     ],
// ]
```

### Example: Readonly Class

```php
readonly class User
{
    public function __construct(
        public string $name,
        public string $email,
    ) {
    }
}

$features = app(PHP83Features::class);
$isReadonly = $features->isReadonlyClass(User::class); // true
```

### Example: Typed Class Constants

```php
class Config
{
    public const int MAX_SIZE = 1000;
    public const string DEFAULT_NAME = 'default';
    public const array ALLOWED_TYPES = ['admin', 'user'];
}

$features = app(PHP83Features::class);
$typedConstants = $features->getTypedConstants(Config::class);
```

### Example: Override Attribute

```php
class ParentClass
{
    public function method(): void
    {
        // ...
    }
}

class ChildClass extends ParentClass
{
    #[\Override]
    public function method(): void
    {
        // Override parent method
    }
}

$features = app(PHP83Features::class);
$hasOverride = $features->hasOverrideAttribute(ChildClass::class, 'method'); // true
```

## 2. PHP Attributes Enhancement

### Cấu hình

Thêm vào `.env`:
```env
ATTRIBUTES_ENHANCEMENT_ENABLED=true
ATTRIBUTES_CACHE_ENABLED=true
ATTRIBUTES_CODE_GENERATION=true
ATTRIBUTES_RUNTIME_OPTIMIZATION=true
```

### Features

- ✅ **Rich metadata extraction** - Extract metadata from attributes
- ✅ **Code generation** - Generate code từ attributes
- ✅ **Runtime optimization** - Optimize attribute access
- ✅ **Caching** - Cache attribute reflection results

### Sử dụng

#### Get Attributes

```php
use Core\Attributes\AttributeEnhancer;

$enhancer = app(AttributeEnhancer::class);

// Get class attributes
$attributes = $enhancer->getClassAttributes(UserController::class);

// Get method attributes
$attributes = $enhancer->getMethodAttributes(UserController::class, 'index');

// Get property attributes
$attributes = $enhancer->getPropertyAttributes(User::class, 'email');

// Get specific attribute
$routeAttr = $enhancer->getFirstAttribute(
    UserController::class,
    \Core\Routing\Attributes\Route::class,
    'method',
    'index'
);

// Check if has attribute
if ($enhancer->hasAttribute(UserController::class, \Core\Routing\Attributes\Route::class, 'method', 'index')) {
    echo "Method has Route attribute\n";
}
```

#### Extract Metadata

```php
// Extract all metadata
$metadata = $enhancer->extractMetadata(UserController::class);

// Extract metadata for specific method
$metadata = $enhancer->extractMetadata(UserController::class, 'index');

// Returns:
// [
//     'class' => [
//         'Core\Routing\Attributes\Controller' => [
//             'prefix' => '/api/users',
//             'middleware' => ['auth'],
//         ],
//     ],
//     'methods' => [
//         'index' => [
//             'Core\Routing\Attributes\Route' => [
//                 'uri' => '',
//                 'method' => 'GET',
//             ],
//         ],
//     ],
// ]
```

#### Generate Code

```php
use Core\Attributes\AttributeCodeGenerator;

$generator = app(AttributeCodeGenerator::class);

// Generate routes
$routesCode = $generator->generateRoutes(UserController::class);

// Generate middleware config
$middlewareCode = $generator->generateMiddlewareConfig(UserController::class);

// Generate validation rules
$validationCode = $generator->generateValidationRules(UserController::class);

// Generate API documentation
$apiDoc = $generator->generateApiDoc(UserController::class);

// Generate OpenAPI spec
$openApiSpec = $generator->generateOpenApiSpec(UserController::class);
```

## Examples

### Example 1: Using Attributes trong Controller

```php
use Core\Routing\Attributes\Route;
use Core\Routing\Attributes\Controller;

#[Controller(prefix: '/api/users', middleware: ['auth'])]
class UserController
{
    #[Route(uri: '', method: 'GET', name: 'users.index')]
    public function index()
    {
        return User::all();
    }

    #[Route(uri: '/{id}', method: 'GET', name: 'users.show')]
    public function show($id)
    {
        return User::find($id);
    }
}

// Extract metadata
$enhancer = app(AttributeEnhancer::class);
$metadata = $enhancer->extractMetadata(UserController::class);

// Generate routes
$generator = app(AttributeCodeGenerator::class);
$routesCode = $generator->generateRoutes(UserController::class);
```

### Example 2: Readonly DTO

```php
readonly class UserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public int $age,
    ) {
    }
}

$features = app(PHP83Features::class);
$isReadonly = $features->isReadonlyClass(UserDTO::class); // true
$validation = $features->validateReadonlyClass(UserDTO::class);
```

### Example 3: Typed Constants

```php
class ApiConfig
{
    public const int MAX_RETRIES = 3;
    public const string API_VERSION = 'v1';
    public const array ALLOWED_METHODS = ['GET', 'POST'];
    public const float TIMEOUT = 30.0;
}

$features = app(PHP83Features::class);
$typedConstants = $features->getTypedConstants(ApiConfig::class);
```

### Example 4: Code Generation từ Attributes

```php
use Core\Attributes\AttributeCodeGenerator;

class ApiController
{
    #[Route('/users', 'GET', middleware: ['auth'])]
    public function list() { }

    #[Route('/users/{id}', 'GET', middleware: ['auth'])]
    public function show($id) { }
}

$generator = app(AttributeCodeGenerator::class);

// Generate route registration code
$routesCode = $generator->generateRoutes(ApiController::class);
// Output:
// Route::GET('/users', [ApiController::class, 'list'])->middleware(['auth']);
// Route::GET('/users/{id}', [ApiController::class, 'show'])->middleware(['auth']);

// Generate OpenAPI spec
$spec = $generator->generateOpenApiSpec(ApiController::class);
```

## Best Practices

### PHP 8.3+ Features

1. **Readonly Classes**: Use cho DTOs và value objects
2. **Typed Constants**: Use typed constants cho better type safety
3. **Override Attribute**: Always use #[\Override] khi overriding methods
4. **Dynamic Constants**: Use khi cần computed constants

### Attributes Enhancement

1. **Cache Attributes**: Enable caching để improve performance
2. **Code Generation**: Use code generation để reduce boilerplate
3. **Metadata Extraction**: Extract metadata for documentation
4. **Runtime Optimization**: Enable runtime optimization

## Troubleshooting

### PHP 8.3+ Features

**Features not available:**
- Check PHP version (8.3+ required)
- Verify feature flags in config
- Check PHP extensions

**Readonly class violations:**
- Ensure all properties are readonly
- Check for static properties
- Review class hierarchy

### Attributes Enhancement

**Attributes not found:**
- Check attribute namespace
- Verify attribute is applied
- Clear attribute cache

**Code generation fails:**
- Check attribute structure
- Verify reflection access
- Review generated code

## Performance Tips

1. **Attribute Caching**: Enable caching for better performance
2. **Lazy Loading**: Load attributes on-demand
3. **Code Generation**: Generate code at build time
4. **Reflection Optimization**: Cache reflection results

## Kết luận

Modern PHP Features cung cấp:

- ✅ **PHP 8.3+ features** support (readonly, typed constants, override)
- ✅ **Attributes enhancement** với rich metadata
- ✅ **Code generation** từ attributes
- ✅ **Runtime optimization** cho attribute access
- ✅ **Easy integration** với existing codebase

Enable các features để leverage modern PHP capabilities và improve code quality.
