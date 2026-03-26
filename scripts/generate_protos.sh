#!/bin/bash

# Generate PHP code from Protobuf definitions
# Usage: ./scripts/generate_protos.sh

set -e

PROTO_DIR="proto/events"
OUTPUT_DIR="proto/generated"

echo "🔨 Generating Protobuf PHP classes..."

# Create output directory
mkdir -p "$OUTPUT_DIR"

# Generate PHP code
protoc \
  --php_out="$OUTPUT_DIR" \
  --proto_path="$PROTO_DIR" \
  --proto_path="/usr/local/include" \
  "$PROTO_DIR"/*.proto

echo "✅ Protobuf generation complete!"
echo "📁 Output directory: $OUTPUT_DIR"

# List generated files
echo ""
echo "Generated files:"
ls -lh "$OUTPUT_DIR" | tail -n +2
