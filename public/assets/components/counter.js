/**
 * Logic khởi tạo cho Counter component.
 * @param {HTMLElement} element - Phần tử DOM gốc của component (thẻ div có data-component).
 */
export default function initializeCounter(element) {
  // 1. Đọc và phân giải (parse) props từ thuộc tính data-props.
  // Cung cấp một object rỗng làm giá trị mặc định để tránh lỗi.
  const props = JSON.parse(element.dataset.props || "{}");
  const { initialValue = 0, step = 1 } = props;

  // 2. Tìm các phần tử con cần thiết.
  const countEl = element.querySelector("[data-count]");
  const incrementBtn = element.querySelector("[data-increment]");
  const decrementBtn = element.querySelector("[data-decrement]");

  // 3. Khởi tạo trạng thái từ props.
  let count = initialValue;

  // 4. Thiết lập các event listener sử dụng dữ liệu từ props.
  incrementBtn.addEventListener("click", () => {
    count += step;
    countEl.textContent = count;
  });

  decrementBtn.addEventListener("click", () => {
    count -= step;
    countEl.textContent = count;
  });

  console.log("Counter component initialized with props:", props);
}
