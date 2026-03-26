<?php

declare(strict_types=1);

namespace Core\Testing;

use Core\Support\Facades\Log;

/**
 * Property-Based Testing
 *
 * Generate test cases automatically và verify invariants.
 * Inspired by QuickCheck (Haskell) và Eris (PHP).
 *
 * Features:
 * - Generate test cases automatically
 * - Find edge cases
 * - Verify invariants
 * - Shrink failing cases
 */
class PropertyTester
{
    protected array $generators = [];
    protected int $maxTests = 100;
    protected int $maxShrinks = 50;

    public function __construct(
        protected array $config = [],
    ) {
        $this->maxTests = $config['max_tests'] ?? 100;
        $this->maxShrinks = $config['max_shrinks'] ?? 50;
        $this->registerDefaultGenerators();
    }

    /**
     * Test a property
     *
     * @param callable $property Property to test (should return bool)
     * @param array $generators Generators for each parameter
     * @return array Test result
     */
    public function testProperty(callable $property, array $generators = []): array
    {
        $failingCases = [];
        $passedTests = 0;

        for ($i = 0; $i < $this->maxTests; $i++) {
            // Generate test case
            $testCase = $this->generateTestCase($generators);

            try {
                $result = $this->executeProperty($property, $testCase);

                if ($result === false) {
                    // Property failed, try to shrink
                    $shrunkCase = $this->shrink($testCase, $property, $generators);
                    $failingCases[] = [
                        'original' => $testCase,
                        'shrunk' => $shrunkCase,
                        'test_number' => $i + 1,
                    ];
                } else {
                    $passedTests++;
                }
            } catch (\Throwable $e) {
                // Exception during test execution
                $shrunkCase = $this->shrink($testCase, $property, $generators);
                $failingCases[] = [
                    'original' => $testCase,
                    'shrunk' => $shrunkCase,
                    'test_number' => $i + 1,
                    'exception' => $e->getMessage(),
                ];
            }
        }

        return [
            'passed' => $passedTests,
            'failed' => count($failingCases),
            'total' => $this->maxTests,
            'failing_cases' => $failingCases,
            'success' => empty($failingCases),
        ];
    }

    /**
     * Generate a test case
     */
    protected function generateTestCase(array $generators): array
    {
        $testCase = [];

        foreach ($generators as $index => $generator) {
            if (is_callable($generator)) {
                $testCase[] = $generator();
            } elseif (is_string($generator) && isset($this->generators[$generator])) {
                $testCase[] = $this->generators[$generator]();
            } else {
                // Default generator based on type hint
                $testCase[] = $this->generateDefault($generator);
            }
        }

        return $testCase;
    }

    /**
     * Execute property with test case
     */
    protected function executeProperty(callable $property, array $testCase): bool
    {
        $result = $property(...$testCase);
        return $result === true;
    }

    /**
     * Shrink failing test case to find minimal failing case
     */
    protected function shrink(array $testCase, callable $property, array $generators): array
    {
        $current = $testCase;
        $shrinks = 0;

        while ($shrinks < $this->maxShrinks) {
            $shrunk = $this->shrinkOnce($current, $generators);
            
            if ($shrunk === $current) {
                // No more shrinking possible
                break;
            }

            try {
                $result = $this->executeProperty($property, $shrunk);
                if ($result === false) {
                    // Still failing, continue shrinking
                    $current = $shrunk;
                } else {
                    // No longer failing, stop
                    break;
                }
            } catch (\Throwable $e) {
                // Still throwing exception, continue shrinking
                $current = $shrunk;
            }

            $shrinks++;
        }

        return $current;
    }

    /**
     * Shrink test case once
     */
    protected function shrinkOnce(array $testCase, array $generators): array
    {
        $shrunk = $testCase;

        foreach ($testCase as $index => $value) {
            $shrunkValue = $this->shrinkValue($value, $generators[$index] ?? null);
            
            if ($shrunkValue !== $value) {
                $shrunk[$index] = $shrunkValue;
                return $shrunk;
            }
        }

        return $shrunk;
    }

    /**
     * Shrink a single value
     */
    protected function shrinkValue(mixed $value, $generator = null): mixed
    {
        return match (true) {
            is_int($value) => $this->shrinkInt($value),
            is_float($value) => $this->shrinkFloat($value),
            is_string($value) => $this->shrinkString($value),
            is_array($value) => $this->shrinkArray($value),
            default => $value,
        };
    }

    /**
     * Shrink integer
     */
    protected function shrinkInt(int $value): int
    {
        if ($value === 0) {
            return 0;
        }

        if ($value > 0) {
            return (int) floor($value / 2);
        }

        return (int) ceil($value / 2);
    }

    /**
     * Shrink float
     */
    protected function shrinkFloat(float $value): float
    {
        if ($value === 0.0) {
            return 0.0;
        }

        return $value / 2.0;
    }

    /**
     * Shrink string
     */
    protected function shrinkString(string $value): string
    {
        if (empty($value)) {
            return $value;
        }

        // Try removing last character
        if (strlen($value) > 1) {
            return substr($value, 0, -1);
        }

        return '';
    }

    /**
     * Shrink array
     */
    protected function shrinkArray(array $value): array
    {
        if (empty($value)) {
            return $value;
        }

        // Try removing last element
        $shrunk = $value;
        array_pop($shrunk);
        
        return $shrunk;
    }

    /**
     * Generate default value
     */
    protected function generateDefault($hint): mixed
    {
        return match (true) {
            $hint === 'int' => $this->generators['int'](),
            $hint === 'float' => $this->generators['float'](),
            $hint === 'string' => $this->generators['string'](),
            $hint === 'array' => $this->generators['array'](),
            default => null,
        };
    }

    /**
     * Register default generators
     */
    protected function registerDefaultGenerators(): void
    {
        $this->generators['int'] = fn() => random_int(-1000, 1000);
        $this->generators['positive_int'] = fn() => random_int(1, 1000);
        $this->generators['negative_int'] = fn() => random_int(-1000, -1);
        $this->generators['float'] = fn() => (random_int(-1000, 1000) / 100.0);
        $this->generators['string'] = fn() => $this->generateRandomString();
        $this->generators['array'] = fn() => $this->generateRandomArray();
        $this->generators['email'] = fn() => $this->generateEmail();
        $this->generators['url'] = fn() => $this->generateUrl();
    }

    /**
     * Generate random string
     */
    protected function generateRandomString(int $length = null): string
    {
        $length = $length ?? random_int(1, 100);
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $string = '';
        
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $string;
    }

    /**
     * Generate random array
     */
    protected function generateRandomArray(): array
    {
        $length = random_int(0, 10);
        $array = [];
        
        for ($i = 0; $i < $length; $i++) {
            $array[] = $this->generators['int']();
        }
        
        return $array;
    }

    /**
     * Generate random email
     */
    protected function generateEmail(): string
    {
        $domains = ['example.com', 'test.com', 'example.org'];
        $username = $this->generateRandomString(random_int(5, 10));
        $domain = $domains[array_rand($domains)];
        
        return "{$username}@{$domain}";
    }

    /**
     * Generate random URL
     */
    protected function generateUrl(): string
    {
        $protocols = ['http', 'https'];
        $domains = ['example.com', 'test.com', 'example.org'];
        $paths = ['', '/path', '/path/to/resource', '/api/v1/users'];
        
        $protocol = $protocols[array_rand($protocols)];
        $domain = $domains[array_rand($domains)];
        $path = $paths[array_rand($paths)];
        
        return "{$protocol}://{$domain}{$path}";
    }

    /**
     * Register custom generator
     */
    public function registerGenerator(string $name, callable $generator): void
    {
        $this->generators[$name] = $generator;
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return [
            'max_tests' => $this->maxTests,
            'max_shrinks' => $this->maxShrinks,
            'generators' => array_keys($this->generators),
        ];
    }
}
