# Generate PHP code from Proto files (Windows PowerShell)
# Usage: .\scripts\generate-grpc.ps1

Write-Host "🚀 Generating PHP code from Proto files..." -ForegroundColor Green

# Output directory
$OutputDir = "src\Core\RPC\Grpc\Generated"

# Create output directory
New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null

# Generate for each proto file
Get-ChildItem -Path "proto\example\*.proto" | ForEach-Object {
    Write-Host "📝 Processing: $($_.Name)" -ForegroundColor Cyan
    
    & protoc --proto_path=proto/example `
           --php_out="$OutputDir" `
           --grpc_out="$OutputDir" `
           --plugin=protoc-gen-grpc=grpc_php_plugin `
           $_.FullName
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Generated PHP code for: $($_.Name)" -ForegroundColor Green
    } else {
        Write-Host "❌ Failed to generate for: $($_.Name)" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "🎉 Generation complete!" -ForegroundColor Green
Write-Host "📁 Output directory: $OutputDir" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. composer dump-autoload"
Write-Host "2. Update service implementations to use generated classes"
Write-Host "3. Start gRPC server: `$env:GRPC_SERVER=1; php artisan serve:grpc"
