<?php

namespace Core\View;

use Core\FileSystem\Filesystem;

class Compiler
{
    protected array $sectionStack = [];
    public const PARENT_PLACEHOLDER = '@__PARENT__@';
    protected array $componentStack = [];
    protected array $componentAliases = [];
    protected array $customDirectives = [];

    public function __construct(
        protected Filesystem $files,
        protected string $cachePath,
    ) {
        if (!$this->files->isDirectory($this->cachePath)) {
            $this->files->makeDirectory($this->cachePath, 0755, true);
        }
    }

    public function getCompiledPath(string $path): string
    {
        return $this->cachePath . '/' . sha1($path) . '.php';
    }

    public function isExpired(string $path): bool
    {
        $compiled = $this->getCompiledPath($path);
        if (!$this->files->exists($compiled)) {
            return true;
        }
        return $this->files->lastModified($path) >= $this->files->lastModified($compiled);
    }

    public function compile(string $path): void
    {
        $value = $this->files->get($path);

        $value = $this->compileComments($value);
        $value = $this->compileVerbatim($value);

        $value = $this->compileIncludes($value);
        $value = $this->compileProps($value);
        $value = $this->compileSlots($value);
        $value = $this->compileStacks($value);
        $value = $this->compileComponents($value);

        $value = $this->compileExtends($value);

        $value = $this->compileSections($value);
        $value = $this->compileYield($value);
        $value = $this->compileShow($value);
        $value = $this->compileParent($value);

        $value = $this->compileRawPhp($value);
        $value = $this->compilePhp($value);
        $value = $this->compileControlStructures($value);
        $value = $this->compileExtraDirectives($value);

        $value = $this->compileEchos($value);
        $value = $this->compileFormsAndHelpers($value);

        $compiledPath = $this->getCompiledPath($path);
        $this->files->put($compiledPath, $value);
    }

    /**
     * Register a custom Blade directive.
     *
     * @param string $name
     * @param callable $handler
     */
    public function directive(string $name, callable $handler): void
    {
        $this->customDirectives[$name] = $handler;
    }

    /**
     * Compile Blade comments.
     */
    protected function compileComments(string $value): string
    {
        // Chuẩn Blade: {{-- comment --}}
        return preg_replace('/\{\{--.*?--\}\}/s', '', $value);
    }

    /**
     * Compile Blade echo statements.
     */
    protected function compileEchos(string $value): string
    {
        // Triple braces {{{ }}} → escape but not convert HTML entities
        $value = preg_replace('/\{\{\{\s*(.*?)\s*\}\}\}/s', '<?php echo e($1, false); ?>', $value);

        // Double braces {{ }} → escape HTML entities
        $value = preg_replace('/\{\{\s*(.*?)\s*\}\}/s', '<?php echo e($1); ?>', $value);

        // Raw echo {!! !!}
        $value = preg_replace('/\{!!\s*(.*?)\s*!!\}/s', '<?php echo $1; ?>', $value);

        return $value;
    }

    /**
     * Compile Blade PHP statements.
     */
    protected function compilePhp(string $value): string
    {
        // @php ... @endphp
        $value = preg_replace_callback('/@php(.*?)@endphp/s', function ($matches) {
            return '<?php' . ($matches[1] ?? '') . '?>';
        }, $value);

        // @php($var = 1)
        return preg_replace('/@php\s*\((.*?)\)/', '<?php $1; ?>', $value);
    }

    /**
     * Compile Blade control structures.
     *
     * @param string $value
     * @return string
     */
    protected function compileControlStructures(string $value): string
    {
        $patterns = [
            '/@if\s*\((.*)\)/' => '<?php if ($1): ?>',
            '/@elseif\s*\((.*)\)/' => '<?php elseif ($1): ?>',
            '/@else/' => '<?php else: ?>',
            '/@endif/' => '<?php endif; ?>',

            '/@foreach\s*\((.*)\)/' => '<?php foreach($1): ?>',
            '/@endforeach/' => '<?php endforeach; ?>',

            '/@forelse\s*\((.*)\)/' => '<?php $__empty = true; foreach ($1): $__empty = false; ?>',
            '/@empty/' => '<?php endforeach; if ($__empty): ?>',
            '/@endforelse/' => '<?php endif; ?>',

            '/@for\s*\((.*)\)/' => '<?php for ($1): ?>',
            '/@endfor/' => '<?php endfor; ?>',

            '/@while\s*\((.*)\)/' => '<?php while ($1): ?>',
            '/@endwhile/' => '<?php endwhile; ?>',

            '/@isset\s*\((.*)\)/' => '<?php if (isset($1)): ?>',
            '/@endisset/' => '<?php endif; ?>',

            '/@empty\s*\((.*)\)/' => '<?php if (empty($1)): ?>',
            '/@endempty/' => '<?php endif; ?>',

            '/@error\s*\((.*)\)/' => '<?php if (isset($errors) && $errors->has($1)): $message = $errors->first($1); ?>',
            '/@enderror/' => '<?php endif; ?>',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $value);
    }

    /**
     * Compile Blade raw PHP statements.
     *
     * @param string $value
     * @return string
     */
    protected function compileRawPhp(string $value): string
    {
        $value = preg_replace('/@json\((.*)\)/s', '<?php echo json_encode($1, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>', $value);
        return $value;
    }

    /**
     * Compile Blade verbatim statements.
     *
     * @param string $value
     * @return string
     */
    protected function compileVerbatim(string $value): string
    {
        return preg_replace_callback('/@verbatim(.*?)@endverbatim/s', function ($matches) {
            return $matches[1];
        }, $value);
    }

    /**
     * Compile Blade include statements.
     *
     * @param string $value
     * @return string
     */
    protected function compileIncludes(string $value): string
    {
        $value = preg_replace('/@include\s*\((.*)\)/', '<?php echo $__env->make($1)->render(); ?>', $value);

        // includeIf
        $value = preg_replace('/@includeIf\s*\((.*)\)/', '<?php if(view()->exists($1)) echo $__env->make($1)->render(); ?>', $value);

        // includeWhen
        $value = preg_replace('/@includeWhen\s*\((.*),(.*)\)/', '<?php if($1) echo $__env->make($2)->render(); ?>', $value);

        // includeUnless
        $value = preg_replace('/@includeUnless\s*\((.*),(.*)\)/', '<?php if(!$1) echo $__env->make($2)->render(); ?>', $value);

        return $value;
    }

    /**
     * Compile Blade component statements.
     *
     * @param string $value
     * @return string
     */
    protected function compileComponents(string $value): string
    {
        $value = preg_replace_callback('/<x-([a-zA-Z0-9\.-]+::)?([a-zA-Z0-9\.-]+)([^>]*)>/', function ($matches) {
            $module = $matches[1]; 
            $component = $matches[2]; 
            $attributes = $matches[3];
            $viewName = $module ? str_replace('::', '::components.', $module) . $component : 'components.' . $component;
            return "<?php \$__env->startComponent('{$viewName}', {$this->compileAttributes($attributes)}); ?>";
        }, $value);

        $value = preg_replace('/<\/x-([a-zA-Z0-9\.-]+::)?[a-zA-Z0-9\.-]+>/', '<?php echo $__env->renderComponent(); ?>', $value);

        return $value;
    }

    /**
     * Compile Blade slot statements.
     *
     * @param string $value
     * @return string
     */
    protected function compileSlots(string $value): string
    {
        $value = preg_replace('/@slot\s*\((.*?)\)/', '<?php $__env->slot($1); ?>', $value);
        $value = str_replace('@endslot', '<?php $__env->endSlot(); ?>', $value);

        return $value;
    }

    /**
     * Compile Blade stack statements.
     *
     * @param string $value
     * @return string
     */
    protected function compileStacks(string $value): string
    {
        $value = preg_replace('/@push\s*\(\s*\'(.*?)\'\s*\)/', '<?php $__env->startPush(\'$1\'); ?>', $value);
        $value = str_replace('@endpush', '<?php $__env->stopPush(); ?>', $value);
        $value = preg_replace('/@stack\s*\(\s*\'(.*?)\'\s*\)/', '<?php echo $__env->yieldPushContent(\'$1\'); ?>', $value);
        return $value;
    }

    /**
     * Compile Blade extends statements.
     *
     * @param string $value
     * @return string
     */
    protected function compileExtends(string $value): string
    {
        // Compiles the @extends directive into a PHP call on the ViewFactory instance ($__env).
        return preg_replace('/@extends\s*\((.*)\)/', '<?php $__env->extend($1); ?>', $value);
    }

    /**
     * Compile Blade section statements.
     *
     * @param string $value
     * @return string
     */
    protected function compileSections(string $value): string
    {
        $value = preg_replace_callback('/@section\s*\(\s*\'(.*?)\'\s*(?:,\s*(.*?))?\s*\)/s', function ($matches) {
            $name = $matches[1];
            if (isset($matches[2])) {
                $content = $matches[2];
                return '<?php $__env->startSection(\'' . $name . '\', ' . $content . '); ?>';
            } else {
                $this->sectionStack[] = $name;
                return '<?php $__env->startSection(\'' . $name . '\'); ?>';
            }
        }, $value);

        $value = preg_replace('/@endsection/', '<?php $__env->stopSection(); ?>', $value);
        return $value;
    }

    /**
     * Compile Blade yield statements.
     *
     * @param string $value
     * @return string
     */
    protected function compileYield(string $value): string
    {
        return preg_replace('/@yield\s*\(\s*\'(.*?)\'(?:\s*,\s*(.*?))?\s*\)/s', '<?php echo $__env->yieldContent(\'$1\', $2); ?>', $value);
    }

    /**
     * Compile Blade show statements.
     *
     * @param string $value
     * @return string
     */
    protected function compileShow(string $value): string
    {
        return str_replace('@show', '<?php $__env->stopSection(); echo $__env->yieldSection(); ?>', $value);
    }

    /**
     * Compile Blade parent statements.
     *
     * @param string $value
     * @return string
     */
    protected function compileParent(string $value): string
    {
        return str_replace('@parent', static::PARENT_PLACEHOLDER, $value);
    }

    /**
     * Compile Blade forms and helpers.
     *
     * @param string $value
     * @return string
     */
    protected function compileFormsAndHelpers(string $value): string
    {
        $value = str_replace('@csrf', '<?php echo csrf_field(); ?>', $value);
        $value = preg_replace('/@method\(\s*\'(.*?)\'\s*\)/', '<input type="hidden" name="_method" value="<?php echo htmlspecialchars(\'$1\', ENT_QUOTES); ?>">', $value);

        return $value;
    }

    /**
     * Compile the props statement into valid PHP.
     *
     * @param string $value
     * @return string
     */
    protected function compileProps(string $value): string
    {
        return preg_replace_callback('/@props\s*\((.*?)\)/s', function ($matches) {
            $expression = $matches[1];
            $arrayExpression = '[' . trim($expression, '[]') . ']';

            try {
                $props = eval("return {$arrayExpression};");
            } catch (\Throwable) {
                return $matches[0];
            }

            $output = '';
            foreach ($props as $key => $defaultValue) {
                if (is_numeric($key)) {
                    $propName = $defaultValue;
                    $output .= "<?php \${$propName} = \${$propName} ?? null; ?>";
                } else {
                    $propName = $key;
                    $exportedValue = var_export($defaultValue, true);
                    $output .= "<?php \${$propName} = \${$propName} ?? {$exportedValue}; ?>";
                }
            }

            return $output;
        }, $value);
    }

    /**
     * Compile component attributes.
     *
     * @param string $attributeString
     * @return string
     */
    protected function compileAttributes(string $attributeString): string
    {
        $pattern = '/
            (?<attribute>
                :?
                [\w\-:]+
            )
            (
                =
                (?<value>
                    (
                        "([^"]*)"
                        |
                        \'([^\']*)\'
                        |
                        [^\s>]+
                    )
                )
            )?
        /x';

        preg_match_all($pattern, $attributeString, $matches, PREG_SET_ORDER);

        $attributes = [];
        foreach ($matches as $match) {
            $name = $match['attribute'];
            $value = $match['value'] ?? null;

            $attributes[$name] = $value;
        }

        return '[' . collect($attributes)->map(function ($value, $name) {
            return $this->compileAttribute($name, $value);
        })->implode(',') . ']';
    }

    /**
     * Compile Blade extra directives.
     *
     * @param string $value
     * @return stringd
     */
    protected function compileExtraDirectives(string $value): string
    {
        $value = str_replace('@auth', '<?php if(auth()->check()): ?>', $value);
        $value = str_replace('@endauth', '<?php endif; ?>', $value);

        $value = str_replace('@guest', '<?php if(auth()->guest()): ?>', $value);
        $value = str_replace('@endguest', '<?php endif; ?>', $value);

        $value = preg_replace('/@once/', '<?php if (! $__env->hasRenderedOnce($once = Str::random())): $__env->markAsRenderedOnce($once); ?>', $value);
        $value = str_replace('@endonce', '<?php endif; ?>', $value);

        $value = preg_replace('/@switch\s*\((.*)\)/', '<?php switch($1):', $value);
        $value = preg_replace('/@case\s*\((.*)\)/', 'case $1:', $value);
        $value = str_replace('@break', '<?php break; ?>', $value);
        $value = str_replace('@default', 'default:', $value);
        $value = str_replace('@endswitch', '<?php endswitch; ?>', $value);

        $value = preg_replace('/@dump\s*\((.*)\)/', '<?php dump($1); ?>', $value);
        $value = preg_replace('/@dd\s*\((.*)\)/', '<?php dd($1); ?>', $value);

        foreach ($this->customDirectives as $name => $handler) {
            $pattern = '/@' . preg_quote($name, '/') . '(?:\s*\((.*?)\))?/';
            $value = preg_replace_callback($pattern, function ($matches) use ($handler) {
                return $handler($matches[1] ?? null);
            }, $value);
        }

        return $value;
    }

    /**
     * Compile a single component attribute.
     *
     * @param string $name
     * @param string|null $value
     * @return string
     */
    protected function compileAttribute(string $name, ?string $value): string
    {
        // wire:* attributes are passed through directly.
        if (str_starts_with($name, 'wire:')) {
            return "'{$name}' => " . ($value ?? 'true');
        }

        // Dynamic-value attribute (e.g., :message="$message")
        if (str_starts_with($name, ':')) {
            $name = substr($name, 1);
            $value = trim($value, '"\''); // Remove quotes to pass the expression directly
        }
        // Boolean attribute (e.g., `disabled`) or normal string attribute
        else {
            $value = $value === null ? 'true' : $value;
        }

        // Convert kebab-case to camelCase for prop names
        $propName = \Illuminate\Support\Str::camel($name);

        return "'{$propName}' => {$value}";
    }
}
