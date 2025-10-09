import { initializeBault } from "./bault.js";
import { initializeSPA } from "./spa-navigation.js";

console.log("BaultPHP JS loaded successfully!");

async function initializeComponents() {
  const componentElements = document.querySelectorAll("[data-component]");

  for (const element of componentElements) {
    const componentName = element.dataset.component;
    if (!componentName) continue;

    try {
      const componentModule = await import(`./components/${componentName}.js`);

      if (
        componentModule.default &&
        typeof componentModule.default === "function"
      ) {
        componentModule.default(element);
      }
    } catch (error) {
      console.error(`Failed to load component JS: ${componentName}`, error);
    }
  }
}

function onPageLoad() {
  initializeComponents();
}

function initializeApp() {
  console.log("BaultPHP App Initializing...");
  initializeSPA();
  initializeBault();
  initializeComponents();
}

document.addEventListener("DOMContentLoaded", initializeApp);

document.addEventListener("bault:navigated", onPageLoad);
