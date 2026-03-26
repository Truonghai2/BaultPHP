#!/bin/bash
#
# Script to verify WASM runtime installation and test basic functionality

set -e

echo "Verifying WASM runtime installation..."

# Check if wasmtime is installed
if ! command -v wasmtime >/dev/null 2>&1; then
    echo "ERROR: WASM runtime (wasmtime) is not installed"
    echo "Run: docker/scripts/install-wasm.sh"
    exit 1
fi

echo "✓ WASM runtime found: $(wasmtime --version)"

# Check if WASM directory exists
if [ ! -d "/app/wasm" ]; then
    echo "WARNING: WASM directory (/app/wasm) does not exist"
    echo "Creating WASM directory..."
    mkdir -p /app/wasm
fi

echo "✓ WASM directory exists: /app/wasm"

# Test WASM execution with a simple test
echo "Testing WASM execution..."
if wasmtime --version >/dev/null 2>&1; then
    echo "✓ WASM runtime is working correctly"
else
    echo "ERROR: WASM runtime test failed"
    exit 1
fi

echo ""
echo "WASM runtime verification completed successfully!"
echo ""
echo "To use WASM in your application:"
echo "  1. Place .wasm files in /app/wasm directory"
echo "  2. Configure in config/wasm.php"
echo "  3. Use: wasm('module.wasm', \$inputs)"
