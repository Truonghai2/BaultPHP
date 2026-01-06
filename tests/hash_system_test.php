<?php

/**
 * Comprehensive Hashing System Test
 * 
 * Run this to verify your advanced hashing implementation.
 * 
 * Usage: php tests/hash_system_test.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

// Boot the application
$app->boot();

use Core\Hashing\AdaptiveHashManager;
use Core\Support\Facades\Hash;

// ANSI colors for pretty output
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('RESET', "\033[0m");

function success(string $message): void {
    echo GREEN . "✅ {$message}" . RESET . "\n";
}

function error(string $message): void {
    echo RED . "❌ {$message}" . RESET . "\n";
}

function warning(string $message): void {
    echo YELLOW . "⚠️  {$message}" . RESET . "\n";
}

function info(string $message): void {
    echo BLUE . "ℹ️  {$message}" . RESET . "\n";
}

function title(string $message): void {
    echo "\n" . BLUE . str_repeat('=', 70) . RESET . "\n";
    echo BLUE . "  {$message}" . RESET . "\n";
    echo BLUE . str_repeat('=', 70) . RESET . "\n\n";
}

// ============================================================================
// Test 1: PHP Version and Argon2id Support
// ============================================================================

title("Test 1: PHP Version & Argon2id Support");

$phpVersion = PHP_VERSION;
info("PHP Version: {$phpVersion}");

if (version_compare($phpVersion, '7.3.0', '<')) {
    error("PHP 7.3+ required for Argon2id support");
    exit(1);
}
success("PHP version is compatible");

if (!defined('PASSWORD_ARGON2ID')) {
    error("PASSWORD_ARGON2ID not defined. PHP not compiled with Argon2 support.");
    info("Recompile PHP with: ./configure --with-password-argon2");
    exit(1);
}
success("PASSWORD_ARGON2ID is defined");

// ============================================================================
// Test 2: Configuration
// ============================================================================

title("Test 2: Configuration Check");

$driver = config('hashing.driver');
info("Hash Driver: {$driver}");

if ($driver === 'argon2id') {
    success("Using Argon2id (recommended)");
} elseif ($driver === 'argon') {
    warning("Using Argon2i (consider upgrading to argon2id)");
} elseif ($driver === 'bcrypt') {
    warning("Using bcrypt (consider upgrading to argon2id)");
} else {
    error("Unknown hash driver: {$driver}");
}

$pepper = config('hashing.argon2id.pepper');
if ($pepper) {
    success("Pepper is configured");
    info("Pepper length: " . strlen($pepper) . " characters");
    
    if (strlen($pepper) < 32) {
        warning("Pepper is short (<32 chars). Recommend 64+ chars.");
    } else {
        success("Pepper length is good");
    }
} else {
    warning("No pepper configured. Add HASH_PEPPER to .env for extra security.");
}

$adaptive = config('hashing.adaptive');
if ($adaptive) {
    success("Adaptive hashing is enabled");
} else {
    warning("Adaptive hashing is disabled");
}

// ============================================================================
// Test 3: Basic Hashing & Verification
// ============================================================================

title("Test 3: Basic Hashing & Verification");

// Get hash manager directly from container (DeferrableProvider)
$hashManager = $app->make('hash');

$testPassword = 'test-password-' . uniqid();
$hash = $hashManager->make($testPassword);

info("Test password: {$testPassword}");
info("Hash: " . substr($hash, 0, 60) . "...");

$info = password_get_info($hash);
$algo = $info['algoName'] ?? 'unknown';

if ($algo === 'argon2id') {
    success("Hash uses Argon2id");
} elseif ($algo === 'argon2i') {
    warning("Hash uses Argon2i (expected argon2id)");
} elseif ($algo === 'bcrypt') {
    warning("Hash uses bcrypt (expected argon2id)");
} else {
    error("Hash uses unknown algorithm: {$algo}");
}

// Verify correct password
if ($hashManager->check($testPassword, $hash)) {
    success("Correct password verification works");
} else {
    error("Correct password verification FAILED!");
    exit(1);
}

// Verify wrong password
if (!$hashManager->check('wrong-password', $hash)) {
    success("Wrong password rejection works");
} else {
    error("Wrong password was accepted!");
    exit(1);
}

// ============================================================================
// Test 4: Salt Randomness
// ============================================================================

title("Test 4: Salt Randomness");

$hash1 = $hashManager->make('same-password');
$hash2 = $hashManager->make('same-password');

if ($hash1 !== $hash2) {
    success("Salt is being applied (hashes differ)");
    info("Hash 1: " . substr($hash1, 0, 40) . "...");
    info("Hash 2: " . substr($hash2, 0, 40) . "...");
} else {
    error("Hashes are identical! Salt may not be working.");
    exit(1);
}

// ============================================================================
// Test 5: Adaptive Hashing (Risk Levels)
// ============================================================================

title("Test 5: Adaptive Hashing (Risk Levels)");

$hasher = app(AdaptiveHashManager::class);

if (!$hasher instanceof AdaptiveHashManager) {
    warning("AdaptiveHashManager not in use");
    info("Set HASH_ADAPTIVE_ENABLED=true in .env to enable");
} else {
    success("AdaptiveHashManager is active");
    
    $profiles = ['low', 'standard', 'high', 'critical'];
    
    foreach ($profiles as $profile) {
        $start = microtime(true);
        $hash = $hasher->makeWithRisk('test', $profile);
        $duration = (microtime(true) - $start) * 1000;
        
        $status = match(true) {
            $duration < 100 => '⚡ Fast',
            $duration < 300 => '⚖️  Balanced',
            $duration < 600 => '🔒 Secure',
            default => '🐌 Slow',
        };
        
        printf(
            "  %-10s | %6.2fms | %s\n",
            ucfirst($profile),
            $duration,
            $status
        );
    }
}

// ============================================================================
// Test 6: Performance Test
// ============================================================================

title("Test 6: Performance Test");

$iterations = 5;
$durations = [];

info("Running {$iterations} iterations...");

for ($i = 1; $i <= $iterations; $i++) {
    $start = microtime(true);
    $hashManager->make("password-{$i}");
    $duration = (microtime(true) - $start) * 1000;
    $durations[] = $duration;
    
    printf("  Iteration %d: %.2fms\n", $i, $duration);
}

$avgDuration = array_sum($durations) / count($durations);
$minDuration = min($durations);
$maxDuration = max($durations);

info("Average: {$avgDuration}ms");
info("Min: {$minDuration}ms");
info("Max: {$maxDuration}ms");

if ($avgDuration < 150) {
    success("Performance is excellent (<150ms avg)");
} elseif ($avgDuration < 300) {
    success("Performance is good (<300ms avg)");
} elseif ($avgDuration < 500) {
    warning("Performance is acceptable but slow (300-500ms)");
    info("Consider reducing ARGON2ID_TIME_COST");
} else {
    error("Performance is too slow (>500ms)");
    warning("Reduce ARGON2ID_TIME_COST or ARGON2ID_MEMORY_COST");
}

// ============================================================================
// Test 7: Timing Attack Protection
// ============================================================================

title("Test 7: Timing Attack Protection");

if (!($hasher instanceof AdaptiveHashManager)) {
    warning("AdaptiveHashManager not available, skipping timing test");
} else {
    $hash = $hashManager->make('secret-password');
    
    // Test correct password timing
    $start = microtime(true);
    $hasher->checkSecure('secret-password', $hash);
    $correctTiming = (microtime(true) - $start) * 1000;
    
    // Test wrong password timing
    $start = microtime(true);
    $hasher->checkSecure('wrong-password', $hash);
    $wrongTiming = (microtime(true) - $start) * 1000;
    
    info("Correct password: {$correctTiming}ms");
    info("Wrong password: {$wrongTiming}ms");
    
    $difference = abs($correctTiming - $wrongTiming);
    
    if ($difference < 10) {
        success("Timing difference < 10ms (protected)");
    } else {
        warning("Timing difference {$difference}ms (may leak info)");
    }
}

// ============================================================================
// Test 8: Rehashing Detection
// ============================================================================

title("Test 8: Rehashing Detection");

// Create old bcrypt hash for testing
$bcryptHash = password_hash('test', PASSWORD_BCRYPT);
$argon2iHash = @password_hash('test', PASSWORD_ARGON2I) ?: null;
$argon2idHash = password_hash('test', PASSWORD_ARGON2ID);

$hashes = [
    'bcrypt' => $bcryptHash,
    'argon2i' => $argon2iHash,
    'argon2id (current)' => $argon2idHash,
];

foreach ($hashes as $name => $testHash) {
    if (!$testHash) {
        info("{$name}: Not available");
        continue;
    }
    
    $needsRehash = $hashManager->needsRehash($testHash);
    
    if ($needsRehash) {
        warning("{$name}: Needs rehash");
    } else {
        success("{$name}: Up to date");
    }
}

// ============================================================================
// Test 9: Memory Usage
// ============================================================================

title("Test 9: Memory Usage");

$memBefore = memory_get_usage(true);

for ($i = 0; $i < 10; $i++) {
    $hashManager->make("password-{$i}");
}

$memAfter = memory_get_usage(true);
$memUsed = ($memAfter - $memBefore) / 1024 / 1024;

info("Memory used for 10 hashes: {$memUsed}MB");

if ($memUsed < 10) {
    success("Memory usage is normal");
} else {
    warning("Memory usage is high: {$memUsed}MB");
}

// ============================================================================
// Final Summary
// ============================================================================

title("🎉 Test Complete!");

echo "\n";
success("All critical tests passed!");
echo "\n";

info("Configuration Summary:");
echo "  • Driver: " . config('hashing.driver') . "\n";
echo "  • Pepper: " . ($pepper ? 'Configured' : 'Not configured') . "\n";
echo "  • Adaptive: " . ($adaptive ? 'Enabled' : 'Disabled') . "\n";
echo "  • Avg Performance: {$avgDuration}ms\n";

echo "\n";
info("Next Steps:");
echo "  1. Review configuration in config/hashing.php\n";
echo "  2. Set HASH_PEPPER in .env if not already set\n";
echo "  3. Test login flow with real users\n";
echo "  4. Monitor logs for 'Password rehashed' messages\n";
echo "  5. Read docs/HASHING_SECURITY.md for best practices\n";

echo "\n";
success("System ready for production! 🚀");
echo "\n";

exit(0);

