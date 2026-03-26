# Generate PHP code from Protobuf definitions (Windows)
# Usage: .\scripts\generate_protos.ps1

$PROTO_DIR = "proto/events"
$OUTPUT_DIR = "proto/generated"

Write-Host "🔨 Generating Protobuf PHP classes..." -ForegroundColor Cyan

# Create output directory
New-Item -ItemType Directory -Force -Path $OUTPUT_DIR | Out-Null

# Generate PHP code
# Note: Requires protoc to be installed
# Download from: https://github.com/protocolbuffers/protobuf/releases

$protoFiles = Get-ChildItem -Path $PROTO_DIR -Filter "*.proto"

foreach ($file in $protoFiles) {
    Write-Host "Processing: $($file.Name)"
    
    protoc `
        --php_out="$OUTPUT_DIR" `
        --proto_path="$PROTO_DIR" `
        "$($file.FullName)"
}

Write-Host "✅ Protobuf generation complete!" -ForegroundColor Green
Write-Host "📁 Output directory: $OUTPUT_DIR"

# List generated files
Write-Host ""
Write-Host "Generated files:"
Get-ChildItem -Path $OUTPUT_DIR -Recurse | Select-Object Name, Length
