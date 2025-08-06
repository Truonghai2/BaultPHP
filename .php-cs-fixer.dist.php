<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/Modules',
        __DIR__ . '/database',
    ])
    ->exclude('vendor')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'concat_space' => [
            'spacing' => 'one',
        ],
        'single_quote' => true,
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'],
        ],
        'no_extra_blank_lines' => [
            'tokens' => ['extra', 'throw', 'use', 'return'],
        ],
        'object_operator_without_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
    ])
    ->setFinder($finder);
