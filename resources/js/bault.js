import morphdom from "morphdom";

/**
 * "Trái tim" của hệ thống component tương tác BaultPHP.
 * Quản lý vòng đời của component: gửi request và cập nhật DOM.
 */

// A map to store debounce timers for each component property.
const debounceTimers = new Map();

/**
 * Debounces a function call.
 * @param {string} key A unique key for the debounce timer.
 * @param {function} callback The function to call after the debounce period.
 * @param {number} time The debounce period in milliseconds.
 */
function debounce(key, callback, time = 150) {
  if (debounceTimers.has(key)) {
    clearTimeout(debounceTimers.get(key));
  }
  const timer = setTimeout(() => {
    callback();
    debounceTimers.delete(key);
  }, time);
  debounceTimers.set(key, timer);
}
function findComponent(el) {
  const componentEl = el.closest("[wire\\:id]");
  if (!componentEl) {
    console.warn("Bault component not found for element:", el);
    return null;
  }
  return componentEl;
}

/**
 * The main function to send updates/actions to a backend component.
 * @param {HTMLElement} componentEl The root element of the component.
 * @param {Array} updates An array of property updates.
 * @param {Array} calls An array of method calls.
 */
async function updateComponent(componentEl, updates = [], calls = []) {
  const snapshot = JSON.parse(componentEl.getAttribute("wire:snapshot"));

  // Hiển thị trạng thái loading (tùy chọn)
  componentEl.style.opacity = "0.7";

  try {
    const response = await fetch("/bault/update", {
      // This route is defined in RouteServiceProvider
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        // Thêm CSRF token nếu cần
      },
      body: JSON.stringify({
        snapshot: snapshot,
        updates: updates,
        calls: calls,
      }),
    });

    if (!response.ok) {
      const errorData = await response.json();
      console.error("Bault component update failed:", errorData);
      // Xử lý lỗi validation ở đây nếu cần
      return;
    }

    const data = await response.json();

    // Cập nhật DOM một cách thông minh bằng morphdom
    morphdom(componentEl, data.html, {
      // Cung cấp một callback để kiểm soát việc cập nhật từng element.
      onBeforeElUpdated: (fromEl, toEl) => {
        // Nếu element không thay đổi, bỏ qua để tăng hiệu năng.
        if (fromEl.isEqualNode(toEl)) {
          return false;
        }
        // Nếu element có thuộc tính `wire:ignore`, không cập nhật nó và các con của nó.
        // Điều này hữu ích cho các thư viện bên thứ ba như biểu đồ, trình soạn thảo văn bản...
        if (fromEl.hasAttribute("wire:ignore")) {
          return false;
        }
        return true;
      },
    });

    // Cập nhật snapshot cho lần request tiếp theo
    componentEl.setAttribute("wire:snapshot", data.snapshot);
  } catch (error) {
    console.error("Network error during Bault component update:", error);
  } finally {
    // Bỏ trạng thái loading
    componentEl.style.opacity = "1";
  }
}

async function callMethod(componentEl, methodName, params) {
  // Use the new central update function
  await updateComponent(
    componentEl,
    [],
    [{ method: methodName, params: params }],
  );
}

export function initializeBault() {
  console.log("Bault frontend core initialized.");

  document.addEventListener("click", (e) => {
    const target = e.target.closest("[wire\\:click]");
    if (!target) return;

    e.preventDefault();

    const componentEl = findComponent(target);
    if (!componentEl) return;

    const action = target.getAttribute("wire:click");
    callMethod(componentEl, action, []);
  });

  const handleModelEvent = (e, eventType) => {
    const target = e.target;

    // Find any wire:model attribute (including modifiers)
    const modelAttr = Array.from(target.attributes).find((attr) =>
      attr.name.startsWith("wire:model"),
    );
    if (!modelAttr) return;

    const isLazy = modelAttr.name.includes(".lazy");

    // Determine if this event should trigger an update
    // A "lazy" model only updates on "change" events.
    if (isLazy && eventType !== "change") return;
    // A standard (non-lazy) model only updates on "input" events.
    if (!isLazy && eventType !== "input") return;

    const componentEl = findComponent(target);
    if (!componentEl) return;

    const property = modelAttr.value;
    const value = target.type === "checkbox" ? target.checked : target.value;

    const updatePayload = {
      type: "syncInput",
      payload: { name: property, value: value },
    };

    // If it's lazy, update immediately on change. Otherwise, debounce on input.
    if (isLazy) {
      updateComponent(componentEl, [updatePayload]);
    } else {
      const debounceAttr = modelAttr.name.match(/debounce\.(\d+)/);
      const debounceTime = debounceAttr ? parseInt(debounceAttr[1], 10) : 150;
      const debounceKey = `${componentEl.getAttribute("wire:id")}:${property}`;

      debounce(
        debounceKey,
        () => {
          updateComponent(componentEl, [updatePayload]);
        },
        debounceTime,
      );
    }
  };

  // Listen for both events on the document body
  document.addEventListener("input", (e) => handleModelEvent(e, "input"));
  document.addEventListener("change", (e) => handleModelEvent(e, "change"));
}
