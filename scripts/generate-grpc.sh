#!/bin/bash

# Generate PHP code from Proto files
# Usage: ./scripts/generate-grpc.sh

set -e

echo "🚀 Generating PHP code from Proto files..."

# Output directory
OUTPUT_DIR="src/Core/RPC/Grpc/Generated"

# Create output directory
mkdir -p "$OUTPUT_DIR"

# Generate for each proto file
for proto_file in proto/example/*.proto; do
    echo "📝 Processing: $proto_file"
    
    protoc --proto_path=proto/example \
           --php_out="$OUTPUT_DIR" \
           --grpc_out="$OUTPUT_DIR" \
           --plugin=protoc-gen-grpc=/usr/local/bin/grpc_php_plugin \
           "$proto_file"
    
    echo "✅ Generated PHP code for: $proto_file"
done

echo ""
echo "🎉 Generation complete!"
echo "📁 Output directory: $OUTPUT_DIR"
echo ""
echo "Next steps:"
echo "1. composer dump-autoload"
echo "2. Update service implementations to use generated classes"
echo "3. Start gRPC server: GRPC_SERVER=1 php artisan serve:grpc"
