#!/usr/bin/env node

/**
 * Wrapper script to run prettier, gracefully handling if it's not installed
 */

import { execSync } from "child_process";

const files = process.argv.slice(2);

if (files.length === 0) {
  process.exit(0);
}

try {
  // Check if prettier is available
  execSync("prettier --version", { stdio: "ignore" });

  // Run prettier on all files
  execSync(`prettier --write ${files.map((f) => `"${f}"`).join(" ")}`, {
    stdio: "inherit",
  });
} catch (error) {
  // Prettier not found or error - just skip
  console.log("Prettier not found, skipping formatting...");
  process.exit(0);
}
