import morphdom from "https://jspm.dev/morphdom";

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

// Helper to get CSRF token from meta tag
function getCsrfToken() {
  const tokenEl = document.querySelector('meta[name="csrf-token"]');
  if (tokenEl) {
    return tokenEl.getAttribute("content");
  }
  console.warn("Bault: CSRF token meta tag not found.");
  return null;
}

function findComponent(el) {
  const componentEl = el.closest("[wire\\:id]");
  if (!componentEl) {
    console.warn("Bault component not found for element:", el);
    return null;
  }
  return componentEl;
}

function getLoadingEls(componentEl) {
  return componentEl.querySelectorAll(
    "[wire\\:loading], [wire\\:loading\\.class], [wire\\:loading\\.class\\.remove]",
  );
}

function setElementLoading(el, targets) {
  const elTarget = el.getAttribute("wire:target");

  // If wire:target is specified, check if it matches any of the current action's targets.
  if (elTarget) {
    const elTargets = elTarget.split(",").map((t) => t.trim());
    // If no match, do nothing.
    if (!elTargets.some((t) => targets.includes(t))) {
      return;
    }
  }

  // wire:loading (show/hide)
  if (el.hasAttribute("wire:loading")) {
    el.style.display = "";
  }

  // wire:loading.class="..."
  const classToAdd = el.getAttribute("wire:loading.class");
  if (classToAdd) {
    el.classList.add(...classToAdd.split(" "));
  }

  // wire:loading.class.remove="..."
  const classToRemove = el.getAttribute("wire:loading.class.remove");
  if (classToRemove) {
    el.classList.remove(...classToRemove.split(" "));
  }
}

function revertElementLoading(el) {
  // wire:loading (show/hide)
  if (el.hasAttribute("wire:loading")) {
    el.style.display = "none";
  }

  // wire:loading.class="..."
  const classToAdd = el.getAttribute("wire:loading.class");
  if (classToAdd) {
    el.classList.remove(...classToAdd.split(" "));
  }

  // wire:loading.class.remove="..."
  const classToRemove = el.getAttribute("wire:loading.class.remove");
  if (classToRemove) {
    el.classList.add(...classToRemove.split(" "));
  }
}

/**
 * The main function to send updates/actions to a backend component.
 * @param {HTMLElement} componentEl The root element of the component.
 * @param {Array} updates An array of property updates.
 * @param {Array} calls An array of method calls.
 */
async function updateComponent(componentEl, updates = [], calls = []) {
  const snapshot = JSON.parse(componentEl.getAttribute("wire:snapshot"));
  const componentId = componentEl.getAttribute("wire:id");

  // --- START: Advanced Loading States ---
  const targets = [
    ...calls.map((call) => call.method),
    ...updates.map((update) => update.payload.name),
  ];
  const loadingEls = getLoadingEls(componentEl);
  loadingEls.forEach((el) => setElementLoading(el, targets));
  // --- END: Advanced Loading States ---

  // 1. Cải tiến Loading State: Thêm class thay vì set style trực tiếp
  componentEl.classList.add("bault-loading");

  document
    .querySelectorAll(`[bault-error-for="${componentId}"]`)
    .forEach((el) => {
      el.textContent = "";
      el.style.display = "none";
    });

  try {
    const response = await fetch("/bault/update", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-CSRF-TOKEN": getCsrfToken(),
      },
      body: JSON.stringify({
        snapshot: snapshot,
        updates: updates,
        calls: calls,
      }),
    });

    if (!response.ok) {
      const errorData = await response.json();
      if (response.status === 422 && errorData.errors) {
        console.warn("Bault Validation Errors:", errorData.errors);
        Object.keys(errorData.errors).forEach((field) => {
          const errorElement = document.querySelector(
            `[bault-error-for="${componentId}"][bault-error="${field}"]`,
          );
          if (errorElement) {
            errorElement.textContent = errorData.errors[field][0];
            errorElement.style.display = "block";
          }
        });
      } else {
        console.error("Bault component update failed:", errorData);
      }
      return;
    }

    const data = await response.json();

    morphdom(componentEl, data.html, {
      onBeforeElUpdated: (fromEl, toEl) => {
        if (fromEl.isEqualNode(toEl)) {
          return false;
        }

        if (fromEl.hasAttribute("wire:ignore")) {
          return false;
        }

        return true;
      },
    });

    componentEl.setAttribute("wire:snapshot", data.snapshot);
  } catch (error) {
    console.error("Network error during Bault component update:", error);
  } finally {
    componentEl.classList.remove("bault-loading");
    loadingEls.forEach((el) => revertElementLoading(el));
  }
}

async function callMethod(componentEl, methodName, params) {
  await updateComponent(
    componentEl,
    [],
    [{ method: methodName, params: params }],
  );
}

let listenersInitialized = false;

function initializeGlobalListeners() {
  if (listenersInitialized) return;

  console.log("Bault: Global event listeners initialized.");

  document.addEventListener("submit", (e) => {
    const target = e.target.closest("form[wire\\:submit]");
    if (!target) return;

    e.preventDefault();

    const componentEl = findComponent(target);
    if (!componentEl) return;

    const action = target.getAttribute("wire:submit");
    callMethod(componentEl, action, []);
  });

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

    const modelAttr = Array.from(target.attributes).find((attr) =>
      attr.name.startsWith("wire:model"),
    );
    if (!modelAttr) return;

    const isLazy = modelAttr.name.includes(".lazy");

    if (isLazy && eventType !== "change") return;
    if (!isLazy && eventType !== "input") return;

    const componentEl = findComponent(target);
    if (!componentEl) return;

    const property = modelAttr.value;
    const value = target.type === "checkbox" ? target.checked : target.value;

    const updatePayload = {
      type: "syncInput",
      payload: { name: property, value: value },
    };

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

  document.addEventListener("input", (e) => handleModelEvent(e, "input"));
  document.addEventListener("change", (e) => handleModelEvent(e, "change"));

  listenersInitialized = true;
}

export function initializeBault() {
  console.log("Bault frontend core initialized.");

  // Khởi tạo các trình lắng nghe toàn cục nếu chưa có.
  initializeGlobalListeners();

  // Logic khởi tạo lại cho các component có thể được thêm vào đây nếu cần.
  // Hiện tại, các listeners toàn cục là đủ.
}
