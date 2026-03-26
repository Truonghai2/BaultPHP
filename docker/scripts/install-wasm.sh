#!/bin/bash
#
# Script to install WebAssembly runtime (wasmtime) in Docker container
# This script can be run manually if needed or used during development

set -e

echo "Installing WebAssembly runtime (wasmtime)..."

# Check if wasmtime is already installed
if command -v wasmtime >/dev/null 2>&1; then
    echo "WASM runtime (wasmtime) is already installed: $(wasmtime --version)"
    exit 0
fi

# Install wasmtime
echo "Downloading and installing wasmtime..."
curl https://wasmtime.dev/install.sh -sSf | bash

# Move to /usr/local/bin for system-wide access
if [ -f "$HOME/.wasmtime/bin/wasmtime" ]; then
    sudo mv "$HOME/.wasmtime/bin/wasmtime" /usr/local/bin/wasmtime
    sudo chmod +x /usr/local/bin/wasmtime
    rm -rf "$HOME/.wasmtime"
    echo "WASM runtime installed successfully: $(wasmtime --version)"
else
    echo "ERROR: Failed to install wasmtime"
    exit 1
fi

echo "WASM runtime installation completed!"
