# Advanced Testing Guide

## Tổng quan

Hệ thống Advanced Testing đã được triển khai với:

1. **Property-Based Testing** - Generate test cases automatically và verify invariants
2. **Mutation Testing** - Verify test quality với Infection PHP

## 1. Property-Based Testing

### Cấu hình

Thêm vào `.env`:
```env
PROPERTY_TESTING_ENABLED=true
PROPERTY_TESTING_MAX_TESTS=100
PROPERTY_TESTING_MAX_SHRINKS=50
```

### Features

- ✅ **Generate test cases automatically** - No need to write test cases manually
- ✅ **Find edge cases** - Automatically discover edge cases
- ✅ **Verify invariants** - Test properties that should always hold
- ✅ **Shrink failing cases** - Find minimal failing test case

### Sử dụng

#### Basic Property Test

```php
use Core\Testing\PropertyTester;

$tester = app(PropertyTester::class);

// Test a property: addition is commutative
$result = $tester->testProperty(
    function (int $a, int $b) {
        return ($a + $b) === ($b + $a);
    },
    ['int', 'int'] // Generators for parameters
);

if ($result['success']) {
    echo "Property holds for {$result['passed']} test cases\n";
} else {
    echo "Property failed!\n";
    foreach ($result['failing_cases'] as $case) {
        echo "Failing case: " . json_encode($case['shrunk']) . "\n";
    }
}
```

#### Custom Generators

```php
// Register custom generator
$tester->registerGenerator('positive_int', fn() => random_int(1, 1000));
$tester->registerGenerator('email', fn() => 'test@example.com');

// Use custom generators
$result = $tester->testProperty(
    function (int $id, string $email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    },
    ['positive_int', 'email']
);
```

#### Built-in Generators

- `int` - Random integer (-1000 to 1000)
- `positive_int` - Positive integer (1 to 1000)
- `negative_int` - Negative integer (-1000 to -1)
- `float` - Random float
- `string` - Random string
- `array` - Random array
- `email` - Random email address
- `url` - Random URL

#### Example: Testing Array Reverse

```php
$result = $tester->testProperty(
    function (array $arr) {
        $reversed = array_reverse($arr);
        $doubleReversed = array_reverse($reversed);
        return $arr === $doubleReversed;
    },
    ['array']
);
```

#### Example: Testing String Length

```php
$result = $tester->testProperty(
    function (string $str) {
        return strlen($str) >= 0;
    },
    ['string']
);
```

### Shrinking

Khi property fails, tester tự động shrink test case để tìm minimal failing case:

```php
$result = $tester->testProperty(
    function (int $x) {
        return $x < 100; // Will fail for x >= 100
    },
    ['int']
);

// Result will contain shrunk case (e.g., 100 instead of 567)
```

## 2. Mutation Testing

### Cấu hình

Thêm vào `.env`:
```env
MUTATION_TESTING_ENABLED=true
MUTATION_TESTING_THREADS=4
MUTATION_TESTING_ONLY_COVERED=true
MUTATION_TESTING_FRAMEWORK=phpunit
MUTATION_TESTING_TIMEOUT=600
MUTATION_TESTING_MIN_MSI=70
```

### Installation

```bash
composer require --dev infection/infection
```

### Features

- ✅ **Auto-generate mutants** - Automatically create code mutations
- ✅ **Verify test quality** - Check if tests catch mutations
- ✅ **Improve test coverage** - Identify gaps in test coverage
- ✅ **Mutation score reporting** - Get MSI (Mutation Score Indicator)

### Sử dụng

#### Basic Mutation Testing

```php
use Core\Testing\MutationTester;

$tester = app(MutationTester::class);

if (!$tester->isAvailable()) {
    echo "Infection PHP not installed. Install with: composer require --dev infection/infection\n";
    exit(1);
}

// Run mutation testing with default options
$result = $tester->runDefault();

if ($result['success']) {
    echo "Mutation Score Indicator (MSI): {$result['msi']}%\n";
    echo "Mutants generated: {$result['mutants_generated']}\n";
    echo "Mutants killed: {$result['mutants_killed']}\n";
    echo "Mutants escaped: {$result['mutants_escaped']}\n";
} else {
    echo "Mutation testing failed\n";
    echo $result['output'];
}
```

#### Custom Options

```php
$result = $tester->run([
    'threads' => 8,
    'only_covered' => true,
    'filter' => 'src/Core/',
    'test_framework' => 'phpunit',
    'show_mutations' => true,
    'verbose' => 2,
]);
```

#### Check Availability

```php
if ($tester->isAvailable()) {
    echo "Infection binary: {$tester->getBinaryPath()}\n";
} else {
    echo "Infection PHP not found\n";
}
```

### Mutation Score Indicator (MSI)

MSI measures test quality:
- **High MSI (> 80%)**: Tests are effective
- **Medium MSI (50-80%)**: Tests need improvement
- **Low MSI (< 50%)**: Tests are insufficient

### Interpreting Results

```php
$result = $tester->runDefault();

$msi = $result['msi'] ?? 0;
$minMsi = config('testing.mutation_testing.min_msi', 70);

if ($msi < $minMsi) {
    echo "MSI {$msi}% is below minimum {$minMsi}%\n";
    echo "Consider improving test coverage\n";
}
```

## Examples

### Example 1: Property-Based Testing cho Validation

```php
use Core\Testing\PropertyTester;

$tester = app(PropertyTester::class);

// Test: Email validation
$result = $tester->testProperty(
    function (string $email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    },
    ['email']
);

// Test: URL validation
$result = $tester->testProperty(
    function (string $url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    },
    ['url']
);
```

### Example 2: Property-Based Testing cho Math Functions

```php
// Test: Addition is associative
$result = $tester->testProperty(
    function (int $a, int $b, int $c) {
        return (($a + $b) + $c) === ($a + ($b + $c));
    },
    ['int', 'int', 'int']
);

// Test: Multiplication distributes over addition
$result = $tester->testProperty(
    function (int $a, int $b, int $c) {
        return ($a * ($b + $c)) === (($a * $b) + ($a * $c));
    },
    ['int', 'int', 'int']
);
```

### Example 3: Mutation Testing trong CI/CD

```php
use Core\Testing\MutationTester;

// In CI/CD pipeline
$tester = app(MutationTester::class);

if (!$tester->isAvailable()) {
    echo "Skipping mutation testing (Infection not installed)\n";
    exit(0);
}

$result = $tester->run([
    'threads' => 4,
    'only_covered' => true,
]);

$msi = $result['msi'] ?? 0;
$minMsi = 70;

if ($msi < $minMsi) {
    echo "MSI {$msi}% is below minimum {$minMsi}%\n";
    exit(1); // Fail CI
}
```

### Example 4: Custom Generator cho Domain-Specific Types

```php
$tester = app(PropertyTester::class);

// Register generator for User ID
$tester->registerGenerator('user_id', function () {
    return random_int(1, 1000000);
});

// Register generator for Price
$tester->registerGenerator('price', function () {
    return round(random_int(100, 100000) / 100, 2);
});

// Test: Price is always positive
$result = $tester->testProperty(
    function (float $price) {
        return $price > 0;
    },
    ['price']
);
```

## Best Practices

### Property-Based Testing

1. **Focus on Invariants**: Test properties that should always hold
2. **Use Appropriate Generators**: Choose generators that match your domain
3. **Shrink Failing Cases**: Use shrunk cases to understand failures
4. **Combine with Unit Tests**: Use property tests to complement unit tests

### Mutation Testing

1. **Run Regularly**: Run mutation testing in CI/CD
2. **Set MSI Threshold**: Enforce minimum MSI in CI
3. **Focus on Covered Code**: Use `only_covered` option
4. **Parallel Execution**: Use multiple threads for faster execution
5. **Review Escaped Mutants**: Improve tests for escaped mutants

## Troubleshooting

### Property-Based Testing

**Too many failing cases:**
- Review property logic
- Check generator ranges
- Verify property is correct

**Tests take too long:**
- Reduce `max_tests`
- Optimize property function
- Use faster generators

### Mutation Testing

**Infection not found:**
- Install: `composer require --dev infection/infection`
- Check PATH
- Verify binary exists

**Low MSI:**
- Improve test coverage
- Add more assertions
- Test edge cases

**Timeout:**
- Increase timeout
- Reduce threads
- Use `only_covered` option

## Performance Tips

1. **Property Testing**: Adjust `max_tests` based on needs
2. **Mutation Testing**: Use parallel execution (threads)
3. **CI/CD**: Run mutation testing on schedule, not every commit
4. **Caching**: Use Infection's caching features
5. **Filtering**: Test specific directories/files

## Kết luận

Advanced Testing cung cấp:

- ✅ **Property-based testing** để automatically generate test cases
- ✅ **Mutation testing** để verify test quality
- ✅ **Edge case discovery** với property testing
- ✅ **Test quality metrics** với mutation score
- ✅ **Easy integration** với existing test suite

Enable các features để improve test quality và coverage.
