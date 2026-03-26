<?php

namespace Core\Console\Commands\Grpc;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

/**
 * Generate PHP code from Proto files Command.
 * 
 * Usage: php artisan grpc:generate
 */
class GenerateProtoCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'grpc:generate 
                {--proto-dir=proto/example : Proto files directory}
                {--output-dir=src/Core/RPC/Grpc/Generated : Output directory}';
    }

    public function description(): string
    {
        return 'Generate PHP code from Proto files';
    }

    public function handle(): int
    {
        $protoDir = $this->option('proto-dir');
        $outputDir = $this->option('output-dir');

        $this->info('🚀 Generating PHP code from Proto files...');
        $this->line('');

        // Check if protoc is installed
        exec('protoc --version', $output, $returnCode);
        
        if ($returnCode !== 0) {
            $this->error('protoc is not installed!');
            $this->info('Install: https://grpc.io/docs/protoc-installation/');
            return 1;
        }

        $this->info('✅ protoc version: ' . implode(' ', $output));

        // Create output directory
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
            $this->info("📁 Created output directory: $outputDir");
        }

        // Find all proto files
        $protoFiles = glob($protoDir . '/*.proto');

        if (empty($protoFiles)) {
            $this->warn("No proto files found in: $protoDir");
            return 0;
        }

        $this->info("Found " . count($protoFiles) . " proto file(s)");
        $this->line('');

        // Generate for each proto file
        foreach ($protoFiles as $protoFile) {
            $this->info("📝 Processing: " . basename($protoFile));

            $command = sprintf(
                'protoc --proto_path=%s --php_out=%s --grpc_out=%s --plugin=protoc-gen-grpc=grpc_php_plugin %s',
                escapeshellarg($protoDir),
                escapeshellarg($outputDir),
                escapeshellarg($outputDir),
                escapeshellarg($protoFile)
            );

            exec($command, $commandOutput, $returnCode);

            if ($returnCode === 0) {
                $this->info("✅ Generated: " . basename($protoFile));
            } else {
                $this->error("❌ Failed: " . basename($protoFile));
                if (!empty($commandOutput)) {
                    $this->error(implode("\n", $commandOutput));
                }
            }

            $this->line('');
        }

        $this->info('🎉 Generation complete!');
        $this->info("📁 Output: $outputDir");
        $this->line('');
        $this->info('Next steps:');
        $this->info('1. composer dump-autoload');
        $this->info('2. php artisan serve:grpc');

        return 0;
    }
}
