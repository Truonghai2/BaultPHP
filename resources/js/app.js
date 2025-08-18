import { initializeBault } from "./bault.js";

// Đây là file JavaScript chính của bạn.
// Bạn có thể import các thư viện khác ở đây.
// Ví dụ: import './bootstrap';

console.log("BaultPHP JS loaded successfully with Vite!");

/**
 * Tự động tìm và khởi tạo các component trên trang.
 * Hàm này sẽ quét các phần tử có thuộc tính `data-component`.
 */
async function initializeComponents() {
  const componentElements = document.querySelectorAll("[data-component]");

  for (const element of componentElements) {
    const componentName = element.dataset.component;
    if (!componentName) continue;

    try {
      // Sử dụng dynamic import của Vite.
      // Vite sẽ tự động tách code (code-splitting) cho mỗi component.
      // Ví dụ: khi gặp `data-component="counter"`, nó sẽ import file `./components/counter.js`.
      const componentModule = await import(`./components/${componentName}.js`);

      // Gọi hàm export default từ module đã import,
      // truyền vào chính phần tử DOM đó để khởi tạo.
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

// Chạy hàm khởi tạo khi DOM đã sẵn sàng.
document.addEventListener("DOMContentLoaded", initializeComponents);
document.addEventListener("DOMContentLoaded", initializeBault);
