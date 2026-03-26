# WebAssembly (WASM) Integration Guide

## Tổng quan

WebAssembly integration cho phép framework thực thi các WASM modules để tăng performance cho compute-intensive tasks lên 10-100x so với PHP thuần.

## Cài đặt

### 1. Cài đặt WASM Runtime

#### Option 1: Wasmtime (Recommended)

```bash
# macOS
brew install wasmtime

# Linux
curl https://wasmtime.dev/install.sh -sSf | bash

# Windows
# Download from https://github.com/bytecodealliance/wasmtime/releases
```

#### Option 2: PHP WASM Extension

```bash
pecl install wasm
```

### 2. Cấu hình

Thêm vào `.env`:

```env
WASM_RUNTIME=wasmtime
WASM_RUNTIME_PATH=/usr/local/bin/wasmtime
WASM_DIRECTORY=wasm
WASM_CACHE_ENABLED=true
WASM_CACHE_TTL=3600
WASM_FALLBACK_TO_PHP=true
```

### 3. Tạo thư mục WASM

```bash
mkdir -p wasm
```

## Sử dụng

### Basic Usage

```php
use Core\WebAssembly\WasmExecutor;

$executor = app(WasmExecutor::class);

// Execute WASM module
$result = $executor->execute('calculator.wasm', [
    'expression' => '2 + 2 * 3',
]);

// Hoặc dùng helper function
$result = wasm('calculator.wasm', ['expression' => '2 + 2 * 3']);
```

### Image Processing

```php
use Core\WebAssembly\WasmImageProcessor;

$processor = wasm_image();

// Resize image
$resized = $processor->resize('/path/to/image.jpg', 800, 600, [
    'quality' => 90,
    'preserve_aspect' => true,
]);

// Crop image
$cropped = $processor->crop('/path/to/image.jpg', 100, 100, 400, 300);

// Apply filter
$filtered = $processor->filter('/path/to/image.jpg', 'blur', [
    'intensity' => 5,
]);
```

### Complex Calculations

```php
use Core\WebAssembly\WasmCalculator;

$calculator = wasm_calc();

// Calculate expression
$result = $calculator->calculate('sin(pi/2) + cos(0)', []);

// Fast Fourier Transform
$fftResult = $calculator->fft([1, 2, 3, 4, 5, 6, 7, 8]);

// Matrix multiplication
$matrixA = [[1, 2], [3, 4]];
$matrixB = [[5, 6], [7, 8]];
$result = $calculator->matrixMultiply($matrixA, $matrixB);

// Statistics
$mean = $calculator->statistics([1, 2, 3, 4, 5], 'mean');
$stddev = $calculator->statistics([1, 2, 3, 4, 5], 'stddev');
```

## Tạo WASM Modules

### 1. Viết code (Rust example)

```rust
// wasm/src/lib.rs
use wasm_bindgen::prelude::*;

#[wasm_bindgen]
pub fn add(a: i32, b: i32) -> i32 {
    a + b
}

#[wasm_bindgen]
pub fn multiply(a: i32, b: i32) -> i32 {
    a * b
}
```

### 2. Compile to WASM

```bash
# Install wasm-pack
curl https://rustwasm.github.io/wasm-pack/installer/init.sh -sSf | sh

# Build
wasm-pack build --target web
```

### 3. Sử dụng trong PHP

```php
$result = wasm('add.wasm', [5, 3], ['function' => 'add']);
// Returns: 8
```

## Performance Comparison

### Image Resize (1000x1000 → 800x600)

- **PHP (GD)**: ~150ms
- **WASM**: ~5ms
- **Speedup**: 30x

### Matrix Multiplication (1000x1000)

- **PHP**: ~2500ms
- **WASM**: ~25ms
- **Speedup**: 100x

### FFT (1024 points)

- **PHP**: ~500ms
- **WASM**: ~2ms
- **Speedup**: 250x

## Best Practices

### 1. Use WASM for Compute-Intensive Tasks

✅ **Good:**
- Image processing
- Complex calculations
- Data transformations
- Encryption/decryption

❌ **Bad:**
- Simple operations (overhead > benefit)
- I/O operations
- Database queries

### 2. Cache Results

WASM execution results are automatically cached. Adjust TTL:

```php
// config/wasm.php
'cache_ttl' => 3600, // 1 hour
```

### 3. Fallback Strategy

Always enable fallback to PHP:

```php
'fallback_to_php' => true,
```

### 4. Error Handling

```php
try {
    $result = wasm('module.wasm', $inputs);
} catch (\RuntimeException $e) {
    // Handle WASM execution error
    // Fallback to PHP implementation
    $result = $phpImplementation->execute($inputs);
}
```

## Advanced Usage

### Custom WASM Modules

Register custom modules:

```php
// In ServiceProvider
$executor = app(WasmExecutor::class);
$executor->registerModule('my_module', base_path('wasm/my_module.wasm'));

// Use
$result = wasm('my_module', $inputs);
```

### Plugin ABI (module WASM plugins)

Module WASM plugins live in `Modules/<Name>/wasm/*.wasm`. Two execution modes:

- **stdio (default for plugins):** PHP sends one JSON object to stdin; the WASM module (WASI) reads stdin and writes one JSON object to stdout. Set `config('wasm.plugin_abi')` to `stdio` or pass `['io_mode' => 'stdio']`.
- **invoke:** PHP calls `wasmtime run module.wasm --invoke run '<json>'`; the module export `run` receives the JSON string.

**Helper:** `wasm_plugin($moduleName, $pluginName, $input, $options)` resolves to `Modules/<module>/wasm/<plugin>.wasm` and runs with stdio ABI.

```php
// Run Cms module's rule_engine.wasm with input payload
$result = wasm_plugin('Cms', 'rule_engine', ['rule' => 'discount', 'context' => $data]);

// Equivalent:
$result = wasm('Cms/rule_engine', ['rule' => 'discount', 'context' => $data], ['io_mode' => 'stdio']);
```

**Building a stdio WASM plugin (Rust + WASI):** compile with `wasm32-wasi` target; in `_start` or main, read from fd 0 (stdin) and write to fd 1 (stdout). Input and output are single JSON lines.

**List plugins of a module:**

```php
$executor = app(\Core\WebAssembly\WasmExecutor::class);
$list = $executor->listModuleWasm('Cms'); // ['rule_engine.wasm' => '/path/...', ...]
```

### Execution Options

```php
$result = wasm('module.wasm', $inputs, [
    'function' => 'custom_function',  // Function name
    'timeout' => 30,                  // Timeout in seconds
    'output_format' => 'json',        // json, int, float, bool
]);
```

### Runtime Information

```php
$executor = app(WasmExecutor::class);
$info = $executor->getRuntimeInfo();

// Returns:
// [
//     'type' => 'wasmtime',
//     'path' => '/usr/local/bin/wasmtime',
//     'available' => true,
// ]
```

## Troubleshooting

### WASM Runtime Not Found

**Error:** `WASM runtime is not available`

**Solution:**
1. Install wasmtime: `brew install wasmtime`
2. Set path in `.env`: `WASM_RUNTIME_PATH=/usr/local/bin/wasmtime`
3. Or use PHP extension: `pecl install wasm`

### Invalid WASM File

**Error:** `Invalid WASM file`

**Solution:**
- Check file exists
- Verify WASM magic number (`\x00\x61\x73\x6D`)
- Recompile WASM module

### Execution Timeout

**Error:** `WASM execution timeout`

**Solution:**
- Increase timeout in options: `['timeout' => 60]`
- Optimize WASM module
- Check for infinite loops

### Fallback Not Working

**Error:** Fallback to PHP not triggered

**Solution:**
- Enable fallback: `WASM_FALLBACK_TO_PHP=true`
- Implement fallback class in `config/wasm.php`

## Examples

### Example 1: Image Thumbnail Generation

```php
// Controller
public function generateThumbnail(Request $request)
{
    $imagePath = $request->file('image')->store('uploads');
    
    $processor = wasm_image();
    $thumbnail = $processor->resize($imagePath, 200, 200, [
        'quality' => 85,
        'preserve_aspect' => true,
    ]);
    
    return response()->json(['thumbnail' => $thumbnail]);
}
```

### Example 2: Data Analysis

```php
// Service
public function analyzeData(array $data)
{
    $calculator = wasm_calc();
    
    $mean = $calculator->statistics($data, 'mean');
    $stddev = $calculator->statistics($data, 'stddev');
    $fft = $calculator->fft($data);
    
    return [
        'mean' => $mean,
        'stddev' => $stddev,
        'frequency_domain' => $fft,
    ];
}
```

### Example 3: Custom WASM Module

```php
// Register module
$executor = app(WasmExecutor::class);
$executor->registerModule('encryption', base_path('wasm/encryption.wasm'));

// Use
$encrypted = wasm('encryption', [
    'data' => 'sensitive data',
    'key' => 'secret key',
], ['function' => 'encrypt']);
```

## Security Considerations

1. **Validate Inputs**: Always validate inputs before passing to WASM
2. **Sandboxing**: WASM modules run in isolated environment
3. **Timeout**: Always set reasonable timeouts
4. **Resource Limits**: Monitor memory and CPU usage

## Performance Tips

1. **Batch Operations**: Process multiple items in one WASM call
2. **Cache Aggressively**: Cache results when possible
3. **Use Appropriate Format**: Choose right output format (json vs binary)
4. **Profile First**: Measure before optimizing

## Resources

- [WebAssembly Official Site](https://webassembly.org/)
- [Wasmtime Documentation](https://docs.wasmtime.dev/)
- [Rust WASM Guide](https://rustwasm.github.io/docs/book/)
- [WASM Performance](https://web.dev/webassembly/)
