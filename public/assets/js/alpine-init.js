/**
 * Alpine.js Initialization
 *
 * Initialize Alpine.js for reactive UI components
 */

// Import Alpine.js from CDN
document.addEventListener("DOMContentLoaded", () => {
  // Check if Alpine is already loaded
  if (window.Alpine) {
    console.log("Alpine.js already loaded");
    return;
  }

  // Load Alpine.js
  const script = document.createElement("script");
  script.src = "https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js";
  script.defer = true;
  document.head.appendChild(script);

  script.onload = () => {
    console.log("Alpine.js loaded successfully");
  };
});
