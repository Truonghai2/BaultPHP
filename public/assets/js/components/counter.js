/**
 * Logic khởi tạo cho Counter component.
 * @param {HTMLElement} element - Phần tử DOM gốc của component (thẻ div có data-component).
 */
export default function initializeCounter(element) {
  const props = JSON.parse(element.dataset.props || "{}");
  const { initialValue = 0, step = 1 } = props;

  const countEl = element.querySelector("[data-count]");
  const incrementBtn = element.querySelector("[data-increment]");
  const decrementBtn = element.querySelector("[data-decrement]");

  let count = initialValue;

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
