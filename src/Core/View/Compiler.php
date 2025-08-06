<?php

namespace Core\View;

/**
 * Class Compiler
 *
 * Compiles template syntax into plain PHP.
 */
class Compiler
{
    /**
     * @var array Holds the content of verbatim blocks.
     */
    protected array $verbatimBlocks = [];

    /**
     * @var array Holds the custom directive handlers.
     */
    protected array $customDirectives = [];

    /**
     * @var string The content of the original template being compiled.
     */
    private string $originalContent = '';

    /**
     * Compile the given template content into PHP.
     */
    public function compile(string $value, string $path): string
    {
        $this->originalContent = $value;

        // First, we store and remove all verbatim blocks so they are not
        // processed by other compilers.
        $value = $this->storeVerbatimBlocks($value);
        
        $value = $this->compileComments($value);
        $value = $this->compileEchos($value);
        $value = $this->compileCustomDirectives($value);
        $value = $this->compileStatements($value); // Thêm bước mới
        $value = $this->compileControlStructures($value);

        // Finally, we restore the verbatim blocks to their original content.
        $value = $this->restoreVerbatimBlocks($value);

        return "<?php /* Source: {$path} */ ?>\n" . $value;
    }

    /**
     * Register a custom directive handler.
     *
     * @param string $name The name of the directive (e.g., 'datetime').
     * @param callable $handler The callback that will compile the directive.
     */
    public function directive(string $name, callable $handler): void
    {
        $this->customDirectives[$name] = $handler;
    }

    /**
     * Register a custom conditional directive (an "if" block).
     * This will automatically create a pair of directives: @name and @endname.
     *
     * @param string $name The name of the directive.
     * @param callable $handler A callback that returns the PHP condition.
     */
    public function if(string $name, callable $handler): void
    {
        // Register the opening directive, e.g., @role('admin')
        $this->directive($name, function ($expression) use ($handler) {
            $condition = call_user_func($handler, $expression);
            return "<?php if ({$condition}): ?>";
        });

        // Register the closing directive, e.g., @endrole
        $this->directive('end' . $name, function () {
            return "<?php endif; ?>";
        });
    }

    /**
     * Store the content of verbatim blocks and replace them with placeholders.
     */
    protected function storeVerbatimBlocks(string $value): string
    {
        $this->verbatimBlocks = []; // Reset for each compilation

        return preg_replace_callback('/@verbatim(.*?)@endverbatim/s', function ($matches) {
            $placeholder = '@__verbatim__' . count($this->verbatimBlocks) . '__@';
            $this->verbatimBlocks[$placeholder] = $matches[1];
            return $placeholder;
        }, $value);
    }

    /**
     * Compile comments like {{-- This is a comment --}}.
     */
    protected function compileComments(string $value): string
    {
        return preg_replace('/\{\{--(.+?)(--\}\})?\s*\}\}/s', '', $value);
    }

    /**
     * Compile echos, like {{ $variable }} and {!! $variable !!}.
     */
    protected function compileEchos(string $value): string
    {
        // We use preg_replace_callback to get the offset and calculate the line number.
        $callback = function ($matches) {
            $line = $this->getLineNumberFromOffset($matches[0][1]);
            $whitespace = empty($matches[1][0]) ? '' : $matches[1][0];
            $type = $matches[2][0];
            $content = $matches[3][0];

            $php = match ($type) {
                '{!!' => "echo {$content};",
                '{{' => "echo esc({$content});",
            };
            return "{$whitespace}<?php /* line {$line} */ {$php} ?>";
        };

        $value = preg_replace_callback('/(\s*)(\{!!|\{\{)\s*(.+?)\s*(--\}\}|!!\}|\}\})/s', $callback, $value, -1, $count, PREG_OFFSET_CAPTURE);

        return $value;
    }

    /**
     * Compile custom directives.
     */
    protected function compileCustomDirectives(string $value): string
    { return $value; } // This is now handled by compileStatements

    /**
     * Compile Blade statements that start with "@".
     * This is a more robust method that handles nested parentheses.
     */
    protected function compileStatements(string $value): string
    {
        // The regex is designed to find directives and the start of their expressions.
        // It doesn't try to match the entire expression, which is the key.
        $value = preg_replace_callback(
            '/\B@(@?\w+)([ \t]*)(\( ( [\S\s]*? ) \))?/x',
            function ($match) {
                return $this->compileStatement($match);
            },
            $value
        );

        // Compile @json($variable)
        $value = preg_replace('/@json\(\s*(.+?)\s*\)/s', '<?php echo json_encode($1, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>', $value);
        return $value;
    }

    /**
     * Compile a single Blade @ statement.
     */
    protected function compileStatement(array $match): string
    {
        $line = $this->getLineNumberFromOffset($match[0][1]);
        $prefix = "<?php /* line {$line} */ ";
        // $match[0] is the full matched string, e.g., "@if (isset($user))"
        // $match[1] is the directive name, e.g., "if"
        // $match[2] is the whitespace after the directive
        // $match[3] is the expression with parentheses, e.g., "(isset($user))"
        // $match[4] is the expression without parentheses, e.g., "isset($user)"

        $directive = $match[1];
        $expression = $match[3] ?? null;

        // Create the method name to call, e.g., "compileIf", "compileForeach"
        $method = 'compile' . ucfirst($directive);

        if (method_exists($this, $method)) {
            // If a dedicated compiler method exists, call it.
            // We pass the expression *with* parentheses.
            return $prefix . substr($this->{$method}($expression), 5); // remove <?php
        }

        // If no specific method, check for custom directives
        if (isset($this->customDirectives[$directive])) {
            $expressionValue = isset($match[4]) ? trim($match[4]) : null;
            return $prefix . substr(call_user_func($this->customDirectives[$directive], $expressionValue), 5);
        }

        // If it's a directive we don't have a special method for, return it as is.
        // It will be handled by compileControlStructures for simple cases like @else, @endif.
        return $match[0][0];
    }

    /**
     * Strip the parentheses from the given expression.
     */
    public function stripParentheses(?string $expression): string
    {
        if ($expression === null) {
            return '';
        }

        if (str_starts_with($expression, '(') && str_ends_with($expression, ')')) {
            return substr($expression, 1, -1);
        }

        return $expression;
    }

    /**
     * Compile control structures like @if, @foreach, etc.
     */
    protected function compileControlStructures(string $value): string
    {
        // Most directives with expressions are now handled by compileStatements.
        // We only need to handle simple, parameter-less directives here.

        $value = preg_replace('/@else/', '<?php else: ?>', $value);
        $value = preg_replace('/@endif/', '<?php endif; ?>', $value);

        $value = preg_replace('/@empty/', '<?php endforeach; if ($__empty): ?>', $value);
        $value = preg_replace('/@endforelse/', '<?php endif; ?>', $value);

        $value = preg_replace('/@endforeach/', '<?php endforeach; ?>', $value);

        $value = preg_replace('/@endfor/', '<?php endfor; ?>', $value);

        $value = preg_replace('/@endwhile/', '<?php endwhile; ?>', $value);

        $value = preg_replace('/@endisset/', '<?php endif; ?>', $value);
        $value = preg_replace('/@endempty/', '<?php endif; ?>', $value);

        $value = preg_replace('/@break/', '<?php break; ?>', $value);
        $value = preg_replace('/@default/', '<?php default: ?>', $value);
        $value = preg_replace('/@endswitch/', '<?php endswitch; ?>', $value);

        // @php ... @endphp
        $value = preg_replace('/@php/', '<?php ', $value);
        $value = preg_replace('/@endphp/', ' ?>', $value);

        // @csrf => render input hidden
        $value = preg_replace('/@csrf/', '<?php echo \Core\Security\CSRF::tokenInput(); ?>', $value);

        $value = preg_replace('/@endsection/', '<?php $this->endSection(); ?>', $value);

        // Directives with expressions are now handled by compile<DirectiveName> methods
        // to ensure correct parsing of complex expressions.
        // The simple replacements for closing tags can remain here.

        // Optionals: @auth, @guest
        $value = preg_replace('/@auth/', '<?php if(auth()->check()): ?>', $value);
        $value = preg_replace('/@endauth/', '<?php endif; ?>', $value);
        $value = preg_replace('/@guest/', '<?php if(!auth()->check()): ?>', $value);
        $value = preg_replace('/@endguest/', '<?php endif; ?>', $value);

        return $value;
    }

    /**
     * Compile the @if statements into valid PHP.
     */
    protected function compileIf(?string $expression): string
    {
        return '<?php if' . $expression . ': ?>';
    }

    /**
     * Compile the @elseif statements into valid PHP.
     */
    protected function compileElseif(?string $expression): string
    {
        return '<?php elseif' . $expression . ': ?>';
    }

    /**
     * Compile the @foreach statements into valid PHP.
     */
    protected function compileForeach(?string $expression): string
    {
        return '<?php foreach' . $expression . ': ?>';
    }

    /**
     * Compile the @forelse statements into valid PHP.
     */
    protected function compileForelse(?string $expression): string
    {
        return '<?php $__empty = true; foreach' . $expression . ': $__empty = false; ?>';
    }

    /**
     * Compile the @for statements into valid PHP.
     */
    protected function compileFor(?string $expression): string
    {
        return '<?php for' . $expression . ': ?>';
    }

    /**
     * Compile the @while statements into valid PHP.
     */
    protected function compileWhile(?string $expression): string
    {
        return '<?php while' . $expression . ': ?>';
    }

    /**
     * Compile the @include statements into valid PHP.
     */
    protected function compileInclude(?string $expression): string
    {
        return '<?php echo $this->make' . $expression . '; ?>';
    }

    /**
     * Compile the @yield statements into valid PHP.
     */
    protected function compileYield(?string $expression): string
    {
        return '<?php echo $this->yieldSection' . $expression . '; ?>';
    }

    /**
     * Compile the @section statements into valid PHP.
     */
    protected function compileSection(?string $expression): string
    {
        return '<?php $this->startSection' . $expression . '; ?>';
    }

    /**
     * Compile the @extends statements into valid PHP.
     */
    protected function compileExtends(?string $expression): string
    {
        return '<?php $this->extend' . $expression . '; ?>';
    }

    /**
     * Restore the verbatim blocks.
     */
    protected function restoreVerbatimBlocks(string $value): string
    {
        return str_replace(array_keys($this->verbatimBlocks), array_values($this->verbatimBlocks), $value);
    }
}
