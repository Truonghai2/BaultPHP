<?php

declare(strict_types=1);

namespace Core\ORM;

use InvalidArgumentException;

/**
 * Represents a raw SQL expression with security validation.
 * 
 * WARNING: RawExpression bypasses prepared statements.
 * Only use with trusted input or whitelisted values.
 */
class RawExpression
{
    private string $value;
    
    // Whitelist của column names cho common operations
    private static array $allowedColumns = [
        'id', 'created_at', 'updated_at', 'deleted_at',
        'count', 'sum', 'avg', 'min', 'max',
        'name', 'email', 'title', 'status',
    ];
    
    // Patterns nguy hiểm
    private static array $dangerousPatterns = [
        '/\b(DROP|DELETE|INSERT|UPDATE|ALTER|CREATE|TRUNCATE|REPLACE|GRANT|REVOKE)\b/i',
        '/UNION.*SELECT/i',
        '/--/',
        '/#/',
        '/\/\*.*\*\//s',
        '/\bOR\b\s+\d+\s*=\s*\d+/i', // OR 1=1
        '/\bAND\b\s+\d+\s*=\s*\d+/i', // AND 1=1
        '/\bEXEC(UTE)?\b/i',
        '/\bSCRIPT\b/i',
        '/\b(INTO\s+OUTFILE|LOAD_FILE)\b/i', // File system access
    ];
    
    public function __construct(string $value)
    {
        $this->validateExpression($value);
        $this->value = $value;
    }
    
    private function validateExpression(string $value): void
    {
        // Check for SQL injection patterns
        foreach (self::$dangerousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                throw new InvalidArgumentException(
                    sprintf('Potential SQL injection detected in RawExpression: %s', substr($value, 0, 50))
                );
            }
        }
        
        // Check for suspicious characters
        if (str_contains($value, ';')) {
            throw new InvalidArgumentException(
                'RawExpression cannot contain multiple statements (; characters are forbidden)'
            );
        }

        if (preg_match('/[\'"\\\\]/', $value)) {
            // Allow quotes in specific contexts (e.g., string values in functions)
            // but still validate overall expression
            $suspiciousCount = preg_match_all('/[\'"\\\\]/', $value);
            if ($suspiciousCount > 10) { // Increased threshold for legitimate use cases but still bounded
                throw new InvalidArgumentException(
                    'RawExpression contains too many suspicious characters'
                );
            }
        }
        
        // Warn if using non-whitelisted columns (in development)
        try {
            if (function_exists('config') && config('app.debug', false)) {
                $hasWhitelisted = false;
                foreach (self::$allowedColumns as $col) {
                    if (stripos($value, $col) !== false) {
                        $hasWhitelisted = true;
                        break;
                    }
                }
                
                if (!$hasWhitelisted && !empty(self::$allowedColumns)) {
                    if (function_exists('logger')) {
                        logger()->warning('RawExpression using non-whitelisted column', [
                            'expression' => substr($value, 0, 100),
                            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently fail in testing/CLI environments
        }
    }
    
    public function getValue(): string
    {
        return $this->value;
    }
    
    public function __toString(): string
    {
        return $this->value;
    }
    
    /**
     * Add custom allowed columns for specific tables
     */
    public static function addAllowedColumns(array $columns): void
    {
        self::$allowedColumns = array_merge(self::$allowedColumns, $columns);
    }
    
    /**
     * Get list of allowed columns
     */
    public static function getAllowedColumns(): array
    {
        return self::$allowedColumns;
    }
    
    /**
     * Create a safe raw expression for arithmetic operations
     */
    public static function increment(string $column, int|float $amount = 1): self
    {
        // Validate column name
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new InvalidArgumentException('Invalid column name for increment');
        }
        
        return new self("`{$column}` + " . (float)$amount);
    }
    
    /**
     * Create a safe raw expression for decrement
     */
    public static function decrement(string $column, int|float $amount = 1): self
    {
        // Validate column name
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new InvalidArgumentException('Invalid column name for decrement');
        }
        
        return new self("`{$column}` - " . (float)$amount);
    }
    
    /**
     * Create a safe COUNT(*) expression
     */
    public static function count(string $column = '*'): self
    {
        if ($column !== '*' && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new InvalidArgumentException('Invalid column name for count');
        }
        
        return new self($column === '*' ? 'COUNT(*)' : "COUNT(`{$column}`)");
    }
    
    /**
     * Create a safe SUM expression
     */
    public static function sum(string $column): self
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new InvalidArgumentException('Invalid column name for sum');
        }
        
        return new self("SUM(`{$column}`)");
    }
}
