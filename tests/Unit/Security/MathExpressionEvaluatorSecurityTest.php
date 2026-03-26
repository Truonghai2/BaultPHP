<?php

namespace Tests\Unit\Security;

use Tests\TestCase;
use Core\Support\MathExpressionEvaluator;

/**
 * Security Tests for MathExpressionEvaluator.
 * 
 * Ensures the parser prevents code injection attacks.
 */
class MathExpressionEvaluatorSecurityTest extends TestCase
{
    private MathExpressionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new MathExpressionEvaluator();
    }

    /**
     * Test: Prevents PHP code injection via semicolon.
     */
    public function test_prevents_php_code_injection_with_semicolon()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expression');

        // Attempt to inject PHP code
        $this->evaluator->evaluate('1 + 1; system("ls")');
    }

    /**
     * Test: Prevents function calls.
     */
    public function test_prevents_function_calls()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expression');

        $this->evaluator->evaluate('system("whoami")');
    }

    /**
     * Test: Prevents backtick execution.
     */
    public function test_prevents_backtick_execution()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expression');

        $this->evaluator->evaluate('`ls -la`');
    }

    /**
     * Test: Prevents dollar sign (PHP variables).
     */
    public function test_prevents_dollar_sign_variables()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expression');

        $this->evaluator->evaluate('$_GET["cmd"]');
    }

    /**
     * Test: Prevents curly braces (code blocks).
     */
    public function test_prevents_curly_braces()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expression');

        $this->evaluator->evaluate('{echo "hack";}');
    }

    /**
     * Test: Prevents eval keyword.
     */
    public function test_prevents_eval_keyword()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expression');

        $this->evaluator->evaluate('eval("1+1")');
    }

    /**
     * Test: Prevents null byte injection.
     */
    public function test_prevents_null_byte_injection()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expression');

        $this->evaluator->evaluate("1 + 1\x00; system('rm -rf')");
    }

    /**
     * Test: Prevents SQL-like comments.
     */
    public function test_prevents_sql_comments()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expression');

        $this->evaluator->evaluate('1 + 1 -- drop table users');
    }

    /**
     * Test: Prevents multi-line expressions.
     */
    public function test_prevents_multiline_expressions()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expression');

        $this->evaluator->evaluate("1 + 1\n2 + 2");
    }

    /**
     * Test: Prevents include/require statements.
     */
    public function test_prevents_include_statements()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expression');

        $this->evaluator->evaluate('include("shell.php")');
    }

    /**
     * Test: Variables are properly sanitized.
     */
    public function test_variables_are_sanitized()
    {
        // Variables should only accept numeric values
        $result = $this->evaluator->evaluate('x + y', ['x' => 10, 'y' => 20]);
        $this->assertEquals(30, $result);

        // Non-numeric variables should be rejected
        $this->expectException(\InvalidArgumentException::class);
        $this->evaluator->evaluate('x + y', ['x' => 'system("ls")', 'y' => 20]);
    }

    /**
     * Test: Only allows safe mathematical operations.
     */
    public function test_only_allows_safe_operations()
    {
        // Valid operations
        $this->assertEquals(5, $this->evaluator->evaluate('2 + 3'));
        $this->assertEquals(6, $this->evaluator->evaluate('2 * 3'));
        $this->assertEquals(8, $this->evaluator->evaluate('2 ^ 3'));
        $this->assertEquals(7, $this->evaluator->evaluate('(2 + 3) * 1.4'));

        // Invalid operations should fail
        $this->expectException(\InvalidArgumentException::class);
        $this->evaluator->evaluate('2 & 3'); // Bitwise not allowed
    }

    /**
     * Test: Prevents excessively long expressions (DoS).
     */
    public function test_prevents_excessive_expression_length()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expression too long');

        // Generate a very long expression
        $longExpression = str_repeat('1 + ', 10000) . '1';
        $this->evaluator->evaluate($longExpression);
    }

    /**
     * Test: Prevents deeply nested expressions (DoS).
     */
    public function test_prevents_deep_nesting()
    {
        $this->expectException(\InvalidArgumentException::class);

        // Generate deeply nested parentheses
        $deepExpression = str_repeat('(', 1000) . '1' . str_repeat(')', 1000);
        $this->evaluator->evaluate($deepExpression);
    }

    /**
     * Test: Rejects invalid characters.
     */
    public function test_rejects_invalid_characters()
    {
        $invalidExpressions = [
            '1 + @',
            '1 + #',
            '1 + &',
            '1 + |',
            '1 + <',
            '1 + >',
            '1 + [',
            '1 + ]',
        ];

        foreach ($invalidExpressions as $expr) {
            try {
                $this->evaluator->evaluate($expr);
                $this->fail("Expected exception for: {$expr}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Invalid expression', $e->getMessage());
            }
        }
    }

    /**
     * Test: Allows safe expressions.
     */
    public function test_allows_safe_expressions()
    {
        $safeExpressions = [
            ['2 + 2', [], 4],
            ['10 - 5', [], 5],
            ['3 * 4', [], 12],
            ['15 / 3', [], 5],
            ['10 % 3', [], 1],
            ['2 ^ 3', [], 8],
            ['(2 + 3) * 4', [], 20],
            ['x + y * z', ['x' => 10, 'y' => 2, 'z' => 5], 20],
            ['2.5 + 3.7', [], 6.2],
            ['-5 + 10', [], 5],
        ];

        foreach ($safeExpressions as [$expr, $vars, $expected]) {
            $result = $this->evaluator->evaluate($expr, $vars);
            $this->assertEquals($expected, $result, "Failed for expression: {$expr}");
        }
    }
}
