<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;
use Throwable;

class OptimizeCompileCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'optimize:compile';
    }

    public function description(): string
    {
        return 'Compile the service container for a performance boost.';
    }

    public function handle(): int
    {
        $this->info('Compiling service container...');

        $cachePath = $this->app->bootstrapPath('cache/container.php');
        $cacheDir = dirname($cachePath);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        $bindings = $this->app->getBindingsForCompilation();
        $aliases = $this->app->getAliasesForCompilation();

        $compiledFactories = [];
        $uncompilable = [];

        foreach ($bindings as $abstract => $details) {
            $concrete = $details['concrete'];

            if ($concrete instanceof \Closure) {
                $uncompilable[] = $abstract;
                continue;
            }

            if (!is_string($concrete) || (!class_exists($concrete) && !interface_exists($concrete))) {
                $uncompilable[] = $abstract;
                continue;
            }

            try {
                $factoryCode = $this->generateFactoryCode($concrete);
                $compiledFactories[$abstract] = $factoryCode;
            } catch (Throwable $e) {
                $this->warn("Could not compile [{$abstract}]: {$e->getMessage()}");
                $uncompilable[] = $abstract;
            }
        }

        $this->writeCacheFile($cachePath, $compiledFactories, $aliases);

        $this->info('Service container compiled successfully!');
        if (!empty($uncompilable)) {
            $this->comment('The following services could not be compiled and will be resolved at runtime:');
            $this->listing($uncompilable);
        }

        return self::SUCCESS;
    }

    protected function generateFactoryCode(string $concrete): string
    {
        $reflector = new \ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new \Exception("Class [{$concrete}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return "fn() => new \\{$concrete}()";
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type && !$type->isBuiltin()) {
                $typeName = $type->getName();
                $dependencies[] = '$app->make(\\' . $typeName . '::class)';
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = var_export($parameter->getDefaultValue(), true);
            } else {
                throw new \Exception("Cannot resolve un-typed parameter \${$parameter->getName()} without a default value.");
            }
        }

        $depsString = implode(', ', $dependencies);
        return "fn(\$app) => new \\{$concrete}({$depsString})";
    }

    protected function writeCacheFile(string $path, array $factories, array $aliases): void
    {
        $content = "<?php\n\nreturn [\n    'factories' => [\n";
        foreach ($factories as $abstract => $factoryCode) {
            $content .= '        ' . var_export($abstract, true) . " => {$factoryCode},\n";
        }
        $content .= "    ],\n    'aliases' => " . var_export($aliases, true) . ",\n];\n";

        file_put_contents($path, $content);
    }
}
