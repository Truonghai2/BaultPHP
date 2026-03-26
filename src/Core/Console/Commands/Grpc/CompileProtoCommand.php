<?php

declare(strict_types=1);

namespace Core\Console\Commands\Grpc;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\RPC\ProtobufCompiler;

/**
 * Compile Protocol Buffers files to PHP classes
 */
class CompileProtoCommand extends BaseCommand
{
    public function __construct(
        Application $app,
        private readonly ProtobufCompiler $compiler,
    ) {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'grpc:compile {proto? : Path to .proto file or directory} {--output= : Output directory}';
    }

    public function description(): string
    {
        return 'Compile Protocol Buffers .proto files to PHP classes';
    }

    public function handle(): int
    {
        if (!$this->compiler->isAvailable()) {
            $this->io->error('protoc compiler is not available. Please install Protocol Buffers compiler.');
            $this->io->writeln('Installation: https://grpc.io/docs/protoc-installation/');
            return 1;
        }

        $this->io->title('Protocol Buffers Compilation');

        $version = $this->compiler->getVersion();
        if ($version) {
            $this->io->info("Using: {$version}");
        }

        $protoPath = $this->argument('proto') ?? config('grpc.protobuf.proto_directory');
        $outputDir = $this->option('output') ?? config('grpc.protobuf.generated_directory');

        if (!file_exists($protoPath)) {
            $this->io->error("Path not found: {$protoPath}");
            return 1;
        }

        try {
            if (is_dir($protoPath)) {
                $this->io->info("Compiling directory: {$protoPath}");
                $results = $this->compiler->compileDirectory($protoPath, $outputDir);

                $successCount = 0;
                $failCount = 0;

                foreach ($results as $file => $result) {
                    if ($result['success']) {
                        $this->io->writeln("  <fg=green>✓</> {$file}");
                        $successCount++;
                    } else {
                        $this->io->writeln("  <fg=red>✗</> {$file}: {$result['error']}");
                        $failCount++;
                    }
                }

                $this->io->newLine();
                $this->io->success("Compilation complete! Success: {$successCount}, Failed: {$failCount}");
            } else {
                $this->io->info("Compiling file: {$protoPath}");
                $this->compiler->compile($protoPath, $outputDir);
                $this->io->success("Compilation complete! Output: {$outputDir}");
            }

            return 0;
        } catch (\Throwable $e) {
            $this->io->error("Compilation failed: {$e->getMessage()}");
            return 1;
        }
    }
}
