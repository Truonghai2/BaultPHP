<?php

declare(strict_types=1);

namespace Core\WebAssembly;

use Core\Application;
use Core\Support\MathExpressionEvaluator;

/**
 * WASM Calculator
 *
 * High-performance calculator for complex mathematical operations.
 * Uses WebAssembly for 10-100x performance improvement.
 */
class WasmCalculator
{
    protected WasmExecutor $executor;

    public function __construct(
        Application $app,
        ?WasmExecutor $executor = null,
    ) {
        $this->executor = $executor ?? $app->make(WasmExecutor::class);
    }

    /**
     * Calculate complex mathematical expression
     *
     * @param string $expression Mathematical expression
     * @param array $variables Variables for expression
     * @return float|int
     */
    public function calculate(string $expression, array $variables = []): float|int
    {
        if (!$this->executor->isAvailable()) {
            return $this->fallbackCalculate($expression, $variables);
        }

        $inputs = [
            'expression' => $expression,
            'variables' => $variables,
        ];

        $result = $this->executor->execute('calculator.wasm', $inputs, [
            'function' => 'calculate',
            'output_format' => 'float',
        ]);

        return $result;
    }

    /**
     * Fast Fourier Transform (FFT)
     *
     * @param array $data Input data array
     * @return array FFT result
     */
    public function fft(array $data): array
    {
        if (!$this->executor->isAvailable()) {
            return $this->fallbackFFT($data);
        }

        $inputs = [
            'data' => $data,
        ];

        $result = $this->executor->execute('fft.wasm', $inputs, [
            'function' => 'fft',
            'output_format' => 'json',
        ]);

        return $result;
    }

    /**
     * Matrix multiplication
     *
     * @param array $matrixA First matrix
     * @param array $matrixB Second matrix
     * @return array Result matrix
     */
    public function matrixMultiply(array $matrixA, array $matrixB): array
    {
        if (!$this->executor->isAvailable()) {
            return $this->fallbackMatrixMultiply($matrixA, $matrixB);
        }

        $inputs = [
            'matrix_a' => $matrixA,
            'matrix_b' => $matrixB,
        ];

        $result = $this->executor->execute('matrix.wasm', $inputs, [
            'function' => 'multiply',
            'output_format' => 'json',
        ]);

        return $result;
    }

    /**
     * Statistical calculations
     *
     * @param array $data Data array
     * @param string $operation Operation (mean, median, stddev, variance)
     * @return float
     */
    public function statistics(array $data, string $operation = 'mean'): float
    {
        if (!$this->executor->isAvailable()) {
            return $this->fallbackStatistics($data, $operation);
        }

        $inputs = [
            'data' => $data,
            'operation' => $operation,
        ];

        $result = $this->executor->execute('statistics.wasm', $inputs, [
            'function' => 'calculate',
            'output_format' => 'float',
        ]);

        return $result;
    }

    /**
     * Fallback to PHP implementation
     */
    protected function fallbackCalculate(string $expression, array $variables): float|int
    {
        $evaluator = new MathExpressionEvaluator();
        return $evaluator->evaluate($expression, $variables);
    }

    protected function fallbackFFT(array $data): array
    {
        // Simple FFT implementation in PHP (slow)
        $n = count($data);
        if ($n <= 1) {
            return $data;
        }

        // Split even and odd
        $even = [];
        $odd = [];
        for ($i = 0; $i < $n; $i++) {
            if ($i % 2 === 0) {
                $even[] = $data[$i];
            } else {
                $odd[] = $data[$i];
            }
        }

        $evenFFT = $this->fallbackFFT($even);
        $oddFFT = $this->fallbackFFT($odd);

        $result = [];
        for ($k = 0; $k < $n / 2; $k++) {
            $t = exp(-2 * M_PI * $k / $n) * $oddFFT[$k];
            $result[$k] = $evenFFT[$k] + $t;
            $result[$k + $n / 2] = $evenFFT[$k] - $t;
        }

        return $result;
    }

    protected function fallbackMatrixMultiply(array $matrixA, array $matrixB): array
    {
        $rowsA = count($matrixA);
        $colsA = count($matrixA[0]);
        $colsB = count($matrixB[0]);

        $result = [];
        for ($i = 0; $i < $rowsA; $i++) {
            for ($j = 0; $j < $colsB; $j++) {
                $result[$i][$j] = 0;
                for ($k = 0; $k < $colsA; $k++) {
                    $result[$i][$j] += $matrixA[$i][$k] * $matrixB[$k][$j];
                }
            }
        }

        return $result;
    }

    protected function fallbackStatistics(array $data, string $operation): float
    {
        return match ($operation) {
            'mean' => array_sum($data) / count($data),
            'median' => $this->calculateMedian($data),
            'stddev' => $this->calculateStdDev($data),
            'variance' => $this->calculateVariance($data),
            default => throw new \InvalidArgumentException("Unknown operation: {$operation}"),
        };
    }

    protected function calculateMedian(array $data): float
    {
        sort($data);
        $count = count($data);
        $middle = floor(($count - 1) / 2);
        
        if ($count % 2 === 0) {
            return ($data[$middle] + $data[$middle + 1]) / 2;
        }
        
        return $data[$middle];
    }

    protected function calculateStdDev(array $data): float
    {
        $mean = array_sum($data) / count($data);
        $variance = 0;
        
        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        return sqrt($variance / count($data));
    }

    protected function calculateVariance(array $data): float
    {
        $mean = array_sum($data) / count($data);
        $variance = 0;
        
        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        return $variance / count($data);
    }
}
