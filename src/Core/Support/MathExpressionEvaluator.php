<?php

namespace Core\Support;

/**
 * Safe mathematical expression evaluator.
 *
 * Supports: numbers, variables, + - * / % ^, parentheses.
 * Disallows functions, property access, or any PHP syntax.
 */
class MathExpressionEvaluator
{
    private const MAX_LENGTH = 1024;

    private const OPERATORS = [
        '^' => ['precedence' => 4, 'assoc' => 'right'],
        '*' => ['precedence' => 3, 'assoc' => 'left'],
        '/' => ['precedence' => 3, 'assoc' => 'left'],
        '%' => ['precedence' => 3, 'assoc' => 'left'],
        '+' => ['precedence' => 2, 'assoc' => 'left'],
        '-' => ['precedence' => 2, 'assoc' => 'left'],
    ];

    /**
     * Evaluate expression safely.
     */
    public function evaluate(string $expression, array $variables = []): float|int
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new \InvalidArgumentException('Expression cannot be empty');
        }
        if (strlen($expression) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException('Expression too long');
        }

        $tokens = $this->tokenize($expression);
        $rpn = $this->toRpn($tokens);
        $result = $this->evaluateRpn($rpn, $variables);

        return (is_float($result) && floor($result) === $result) ? (int) $result : $result;
    }

    /**
     * Tokenize expression into numbers, identifiers, operators, parentheses.
     */
    private function tokenize(string $expression): array
    {
        $pattern = '/\s*([0-9]*\.[0-9]+|[0-9]+|[A-Za-z_][A-Za-z0-9_]*|[\+\-\*\/\^\%\(\)])/';
        preg_match_all($pattern, $expression, $matches, PREG_OFFSET_CAPTURE);

        $tokens = [];
        $pos = 0;
        foreach ($matches[1] as [$token, $offset]) {
            if ($offset !== $pos) {
                throw new \InvalidArgumentException('Invalid token in expression');
            }
            $tokens[] = $token;
            $pos = $offset + strlen($token);
        }

        if ($pos !== strlen($expression)) {
            throw new \InvalidArgumentException('Invalid token in expression');
        }

        return $this->normalizeUnaryMinus($tokens);
    }

    /**
     * Normalize unary minus by inserting 0 before it.
     */
    private function normalizeUnaryMinus(array $tokens): array
    {
        $result = [];
        $prev = null;

        foreach ($tokens as $token) {
            if ($token === '-' && ($prev === null || $this->isOperator($prev) || $prev === '(')) {
                $result[] = '0';
            }
            $result[] = $token;
            $prev = $token;
        }

        return $result;
    }

    /**
     * Convert tokens to Reverse Polish Notation using shunting-yard.
     */
    private function toRpn(array $tokens): array
    {
        $output = [];
        $stack = [];

        foreach ($tokens as $token) {
            if ($this->isNumber($token) || $this->isIdentifier($token)) {
                $output[] = $token;
                continue;
            }

            if ($token === '(') {
                $stack[] = $token;
                continue;
            }

            if ($token === ')') {
                while (!empty($stack) && end($stack) !== '(') {
                    $output[] = array_pop($stack);
                }
                if (empty($stack)) {
                    throw new \InvalidArgumentException('Mismatched parentheses');
                }
                array_pop($stack);
                continue;
            }

            if ($this->isOperator($token)) {
                while (!empty($stack) && $this->isOperator(end($stack))) {
                    $top = end($stack);
                    $curr = self::OPERATORS[$token];
                    $topOp = self::OPERATORS[$top];

                    $shouldPop = $curr['assoc'] === 'left'
                        ? $curr['precedence'] <= $topOp['precedence']
                        : $curr['precedence'] < $topOp['precedence'];

                    if (!$shouldPop) {
                        break;
                    }

                    $output[] = array_pop($stack);
                }

                $stack[] = $token;
                continue;
            }

            throw new \InvalidArgumentException('Unsupported token in expression');
        }

        while (!empty($stack)) {
            $op = array_pop($stack);
            if ($op === '(' || $op === ')') {
                throw new \InvalidArgumentException('Mismatched parentheses');
            }
            $output[] = $op;
        }

        return $output;
    }

    /**
     * Evaluate RPN expression.
     */
    private function evaluateRpn(array $tokens, array $variables): float
    {
        $stack = [];

        foreach ($tokens as $token) {
            if ($this->isNumber($token)) {
                $stack[] = (float) $token;
                continue;
            }

            if ($this->isIdentifier($token)) {
                if (!array_key_exists($token, $variables)) {
                    throw new \InvalidArgumentException("Unknown variable: {$token}");
                }
                if (!is_numeric($variables[$token])) {
                    throw new \InvalidArgumentException("Variable {$token} is not numeric");
                }
                $stack[] = (float) $variables[$token];
                continue;
            }

            if ($this->isOperator($token)) {
                if (count($stack) < 2) {
                    throw new \InvalidArgumentException('Invalid expression');
                }
                $b = array_pop($stack);
                $a = array_pop($stack);
                $stack[] = $this->applyOperator($token, $a, $b);
                continue;
            }

            throw new \InvalidArgumentException('Invalid token in expression');
        }

        if (count($stack) !== 1) {
            throw new \InvalidArgumentException('Invalid expression');
        }

        return (float) $stack[0];
    }

    private function applyOperator(string $op, float $a, float $b): float
    {
        return match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
            '/' => $b == 0.0 ? throw new \InvalidArgumentException('Division by zero') : $a / $b,
            '%' => $b == 0.0 ? throw new \InvalidArgumentException('Division by zero') : fmod($a, $b),
            '^' => $a ** $b,
            default => throw new \InvalidArgumentException('Unknown operator'),
        };
    }

    private function isOperator(string $token): bool
    {
        return isset(self::OPERATORS[$token]);
    }

    private function isNumber(string $token): bool
    {
        return is_numeric($token);
    }

    private function isIdentifier(string $token): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $token) === 1;
    }
}
