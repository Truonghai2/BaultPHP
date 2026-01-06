#!/usr/bin/env node

/**
 * Wrapper script to run php-cs-fixer on files one at a time
 * This prevents memory issues when processing many files
 */

import { execSync } from "child_process";

const files = process.argv.slice(2);

if (files.length === 0) {
  process.exit(0);
}

// Check PHP version first
try {
  const phpVersion = execSync('php -r "echo PHP_VERSION;"', {
    encoding: "utf8",
  }).trim();
  const [major, minor] = phpVersion.split(".").map(Number);

  // Check if PHP version is >= 8.2
  if (major < 8 || (major === 8 && minor < 2)) {
    console.log(
      `PHP version ${phpVersion} is too old (requires >= 8.2.0). Skipping php-cs-fixer...`,
    );
    process.exit(0);
  }
} catch (error) {
  console.log("Could not check PHP version. Skipping php-cs-fixer...");
  process.exit(0);
}

let hasErrors = false;

for (const file of files) {
  try {
    // Escape file path for Windows compatibility
    const escapedFile = file.replace(/"/g, '\\"');
    execSync(
      `php vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --allow-risky=yes "${escapedFile}"`,
      { stdio: "inherit", shell: true },
    );
  } catch (error) {
    // If it's a PHP version error, skip silently
    if (error.message && error.message.includes("PHP version")) {
      console.log("PHP version incompatible. Skipping php-cs-fixer...");
      process.exit(0);
    }
    console.error(`Error fixing ${file}:`, error.message);
    hasErrors = true;
  }
}

process.exit(hasErrors ? 1 : 0);
