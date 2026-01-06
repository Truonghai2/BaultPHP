/**
 * Block Inline Editor
 *
 * Edit blocks directly on frontend (Moodle-like)
 */

class BlockInlineEditor {
  constructor() {
    this.editMode = false;
    this.apiBase = "/admin/blocks";
    this.init();
  }

  init() {
    this.injectToggleButton();
    this.injectStyles();
    this.loadBlockTypes();
  }

  /**
   * Inject edit mode toggle button
   */
  injectToggleButton() {
    if (!this.isAuthenticated()) return;

    const button = document.createElement("button");
    button.id = "block-edit-toggle";
    button.className = "block-edit-toggle";
    button.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            <span>Edit Blocks</span>
        `;
    button.title = "Toggle block editing mode";
    button.onclick = () => this.toggleEditMode();

    document.body.appendChild(button);
  }

  /**
   * Toggle edit mode
   */
  toggleEditMode() {
    this.editMode = !this.editMode;
    const button = document.getElementById("block-edit-toggle");

    if (this.editMode) {
      document.body.classList.add("block-edit-mode");
      button.classList.add("active");
      button.querySelector("span").textContent = "Exit Edit Mode";
      this.enableEditMode();
    } else {
      document.body.classList.remove("block-edit-mode");
      button.classList.remove("active");
      button.querySelector("span").textContent = "Edit Blocks";
      this.disableEditMode();
    }
  }

  /**
   * Enable edit mode - add controls to regions
   */
  enableEditMode() {
    const regions = document.querySelectorAll(".region");

    regions.forEach((region) => {
      this.addRegionControls(region);
      this.makeBlocksEditable(region);
    });

    // Add empty regions if they don't exist
    this.ensureAllRegions();
  }

  /**
   * Disable edit mode - remove controls
   */
  disableEditMode() {
    document.querySelectorAll(".region-controls").forEach((el) => el.remove());
    document.querySelectorAll(".block-controls").forEach((el) => el.remove());
    document.querySelectorAll(".region-empty").forEach((el) => el.remove());
  }

  /**
   * Add controls to region
   */
  addRegionControls(region) {
    if (region.querySelector(".region-controls")) return;

    const regionName = region.dataset.region || "unknown";

    const controls = document.createElement("div");
    controls.className = "region-controls";
    controls.innerHTML = `
            <div class="region-label">
                <span class="region-icon">📍</span>
                <span class="region-name">${this.formatRegionName(regionName)}</span>
                <span class="region-count">${region.querySelectorAll(".block").length} blocks</span>
            </div>
            <button class="add-block-btn" data-region="${regionName}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add Block
            </button>
        `;

    region.insertBefore(controls, region.firstChild);

    // Add event listener
    controls.querySelector(".add-block-btn").addEventListener("click", () => {
      this.showBlockSelectorModal(regionName);
    });
  }

  /**
   * Make blocks editable
   */
  makeBlocksEditable(region) {
    const blocks = region.querySelectorAll(".block");

    blocks.forEach((block) => {
      if (block.querySelector(".block-controls")) return;

      const blockId = block.dataset.blockId;

      const controls = document.createElement("div");
      controls.className = "block-controls";
      controls.innerHTML = `
                <button class="block-control-btn edit-btn" title="Edit Block" data-action="edit" data-block-id="${blockId}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                </button>
                <button class="block-control-btn visibility-btn" title="Toggle Visibility" data-action="toggle-visibility" data-block-id="${blockId}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
                <button class="block-control-btn delete-btn" title="Delete Block" data-action="delete" data-block-id="${blockId}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </button>
            `;

      block.style.position = "relative";
      block.appendChild(controls);

      // Add event listeners
      controls.querySelectorAll(".block-control-btn").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.stopPropagation();
          this.handleBlockAction(btn.dataset.action, btn.dataset.blockId);
        });
      });
    });
  }

  /**
   * Ensure all standard regions exist
   */
  ensureAllRegions() {
    const standardRegions = [
      "header",
      "sidebar-left",
      "content",
      "sidebar",
      "footer",
    ];
    const existingRegions = Array.from(
      document.querySelectorAll(".region"),
    ).map((r) => r.dataset.region);

    standardRegions.forEach((regionName) => {
      if (!existingRegions.includes(regionName)) {
        // Region doesn't exist, create placeholder
        this.createEmptyRegion(regionName);
      }
    });
  }

  /**
   * Create empty region placeholder
   */
  createEmptyRegion(regionName) {
    // Find appropriate container based on region name
    const container = this.findRegionContainer(regionName);
    if (!container) return;

    const emptyRegion = document.createElement("div");
    emptyRegion.className = "region region-empty";
    emptyRegion.dataset.region = regionName;
    emptyRegion.innerHTML = `
            <div class="empty-region-placeholder">
                <p>Empty ${this.formatRegionName(regionName)} Region</p>
            </div>
        `;

    container.appendChild(emptyRegion);
    this.addRegionControls(emptyRegion);
  }

  /**
   * Find appropriate container for region
   */
  findRegionContainer(regionName) {
    const main = document.getElementById("app-content")?.parentElement;
    return main;
  }

  /**
   * Show block selector modal
   */
  async showBlockSelectorModal(regionName) {
    const modal = document.createElement("div");
    modal.className = "block-selector-modal";
    modal.innerHTML = `
            <div class="block-selector-backdrop"></div>
            <div class="block-selector-content">
                <div class="block-selector-header">
                    <h2>Add Block to ${this.formatRegionName(regionName)}</h2>
                    <button class="modal-close-btn">&times;</button>
                </div>
                <div class="block-selector-search">
                    <input type="search" placeholder="Search blocks..." id="block-search-input">
                </div>
                <div class="block-selector-body">
                    <div class="block-types-loading">Loading blocks...</div>
                </div>
            </div>
        `;

    document.body.appendChild(modal);

    modal
      .querySelector(".modal-close-btn")
      .addEventListener("click", () => modal.remove());
    modal
      .querySelector(".block-selector-backdrop")
      .addEventListener("click", () => modal.remove());

    await this.loadBlockTypesIntoModal(modal, regionName);

    modal
      .querySelector("#block-search-input")
      .addEventListener("input", (e) => {
        this.filterBlockTypes(e.target.value);
      });
  }

  /**
   * Load block types
   */
  async loadBlockTypes() {
    try {
      const response = await fetch(`${this.apiBase}/types`);

      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        console.warn(
          "Block types API returned non-JSON response. User may not be authenticated.",
        );
        this.blockTypes = [];
        return;
      }

      if (response.status === 401) {
        console.warn(
          "Block types API: User not authenticated. Please log in to manage blocks.",
        );
        this.blockTypes = [];
        return;
      }

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        const errorMessage =
          errorData.error || errorData.message || response.statusText;
        console.error(`Failed to load block types: ${errorMessage}`);
        this.blockTypes = [];
        return;
      }

      const data = await response.json();
      this.blockTypes = data.block_types || [];
    } catch (error) {
      console.error("Failed to load block types:", error);
      this.blockTypes = [];
    }
  }

  /**
   * Load block types into modal
   */
  async loadBlockTypesIntoModal(modal, regionName) {
    const body = modal.querySelector(".block-selector-body");

    if (!this.blockTypes || this.blockTypes.length === 0) {
      await this.loadBlockTypes();
    }

    if (this.blockTypes.length === 0) {
      body.innerHTML = '<p class="no-blocks">No block types available</p>';
      return;
    }

    const categories = {};
    this.blockTypes.forEach((block) => {
      if (!categories[block.category]) {
        categories[block.category] = [];
      }
      categories[block.category].push(block);
    });

    let html = "";
    for (const [category, blocks] of Object.entries(categories)) {
      html += `
                <div class="block-category">
                    <h3 class="category-title">${category}</h3>
                    <div class="block-types-grid">
                        ${blocks
                          .map((block) => {
                            const title = block.title || "Untitled Block";
                            const description =
                              block.description || "No description";
                            const searchText = `${title.toLowerCase()} ${description.toLowerCase()}`;

                            return `
                                <div class="block-type-card" data-block-type="${block.name}" data-search="${searchText}">
                                    <div class="block-type-icon">${block.icon || "📦"}</div>
                                    <div class="block-type-info">
                                        <h4>${title}</h4>
                                        <p>${description}</p>
                                    </div>
                                </div>
                            `;
                          })
                          .join("")}
                    </div>
                </div>
            `;
    }

    body.innerHTML = html;

    body.querySelectorAll(".block-type-card").forEach((card) => {
      card.addEventListener("click", () => {
        this.addBlockToRegion(card.dataset.blockType, regionName);
        modal.remove();
      });
    });
  }

  /**
   * Filter block types
   */
  filterBlockTypes(search) {
    const cards = document.querySelectorAll(".block-type-card");
    const searchLower = search.toLowerCase();

    cards.forEach((card) => {
      const searchText = card.dataset.search || "";
      card.style.display = searchText.includes(searchLower) ? "flex" : "none";
    });
  }

  /**
   * Add block to region
   */
  async addBlockToRegion(blockType, regionName) {
    try {
      const response = await fetch(`${this.apiBase}`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": this.getCsrfToken(),
        },
        body: JSON.stringify({
          block_type: blockType,
          region: regionName,
          context_type: "global",
          visible: true,
          title: `New ${blockType} Block`,
        }),
      });

      if (response.ok) {
        this.showNotification("Block added successfully!", "success");
        setTimeout(() => window.location.reload(), 1000);
      } else {
        throw new Error("Failed to add block");
      }
    } catch (error) {
      console.error("Error adding block:", error);
      this.showNotification("Failed to add block", "error");
    }
  }

  /**
   * Handle block actions
   */
  async handleBlockAction(action, blockId) {
    switch (action) {
      case "edit":
        window.location.href = `/admin/cms/blocks/visual`;
        break;
      case "toggle-visibility":
        await this.toggleBlockVisibility(blockId);
        break;
      case "delete":
        if (confirm("Delete this block?")) {
          await this.deleteBlock(blockId);
        }
        break;
    }
  }

  /**
   * Toggle block visibility
   */
  async toggleBlockVisibility(blockId) {
    try {
      const response = await fetch(
        `${this.apiBase}/${blockId}/toggle-visibility`,
        {
          method: "POST",
          headers: { "X-CSRF-TOKEN": this.getCsrfToken() },
        },
      );

      if (response.ok) {
        this.showNotification("Block visibility toggled", "success");
        setTimeout(() => window.location.reload(), 1000);
      }
    } catch (error) {
      this.showNotification("Failed to toggle visibility", "error");
    }
  }

  /**
   * Delete block
   */
  async deleteBlock(blockId) {
    try {
      const response = await fetch(`${this.apiBase}/${blockId}`, {
        method: "DELETE",
        headers: { "X-CSRF-TOKEN": this.getCsrfToken() },
      });

      if (response.ok) {
        this.showNotification("Block deleted", "success");
        setTimeout(() => window.location.reload(), 1000);
      }
    } catch (error) {
      this.showNotification("Failed to delete block", "error");
    }
  }

  /**
   * Format region name
   */
  formatRegionName(name) {
    return name
      .split("-")
      .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
      .join(" ");
  }

  /**
   * Check if user is authenticated
   */
  isAuthenticated() {
    return (
      document.querySelector('meta[name="user-authenticated"]')?.content ===
        "true" || document.cookie.includes("laravel_session")
    );
  }

  /**
   * Get CSRF token
   */
  getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || "";
  }

  /**
   * Show notification
   */
  showNotification(message, type = "info") {
    const notification = document.createElement("div");
    notification.className = `block-notification block-notification-${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => notification.classList.add("show"), 100);
    setTimeout(() => {
      notification.classList.remove("show");
      setTimeout(() => notification.remove(), 300);
    }, 3000);
  }

  /**
   * Inject styles
   */
  injectStyles() {
    if (document.getElementById("block-inline-editor-styles")) return;

    const styles = document.createElement("style");
    styles.id = "block-inline-editor-styles";
    styles.textContent = `
            ${this.getStyles()}
        `;
    document.head.appendChild(styles);
  }

  /**
   * Get CSS styles
   */
  getStyles() {
    return `
            /* Toggle Button */
            .block-edit-toggle {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 50px;
                padding: 12px 24px;
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                transition: all 0.3s;
                z-index: 9999;
            }
            
            .block-edit-toggle:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
            }
            
            .block-edit-toggle.active {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            }
            
            .block-edit-toggle svg {
                width: 18px;
                height: 18px;
            }
            
            /* Edit Mode Styles */
            body.block-edit-mode .region {
                outline: 2px dashed rgba(102, 126, 234, 0.3);
                outline-offset: 4px;
                padding-top: 50px;
                position: relative;
                min-height: 80px;
                transition: all 0.2s;
            }
            
            body.block-edit-mode .region:hover {
                outline-color: rgba(102, 126, 234, 0.6);
                background: rgba(102, 126, 234, 0.02);
            }
            
            /* Region Controls */
            .region-controls {
                position: absolute;
                top: 8px;
                left: 0;
                right: 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0 12px;
                z-index: 10;
            }
            
            .region-label {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 12px;
                font-weight: 600;
                color: rgba(255, 255, 255, 0.7);
                background: rgba(102, 126, 234, 0.9);
                padding: 4px 12px;
                border-radius: 20px;
            }
            
            .region-count {
                background: rgba(255, 255, 255, 0.2);
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 11px;
            }
            
            .add-block-btn {
                background: rgba(59, 130, 246, 0.9);
                color: white;
                border: none;
                padding: 6px 14px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 6px;
                transition: all 0.2s;
            }
            
            .add-block-btn:hover {
                background: rgba(59, 130, 246, 1);
                transform: scale(1.05);
            }
            
            /* Block Controls */
            .block-controls {
                position: absolute;
                top: 8px;
                right: 8px;
                display: flex;
                gap: 4px;
                z-index: 20;
            }
            
            .block-control-btn {
                background: rgba(0, 0, 0, 0.7);
                color: white;
                border: none;
                width: 28px;
                height: 28px;
                border-radius: 6px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .block-control-btn:hover {
                background: rgba(59, 130, 246, 0.9);
                transform: scale(1.1);
            }
            
            .delete-btn:hover {
                background: rgba(239, 68, 68, 0.9);
            }
            
            /* Modal */
            .block-selector-modal {
                position: fixed;
                inset: 0;
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .block-selector-backdrop {
                position: absolute;
                inset: 0;
                background: rgba(0, 0, 0, 0.7);
                backdrop-filter: blur(4px);
            }
            
            .block-selector-content {
                position: relative;
                background: #1f2937;
                border-radius: 16px;
                width: 90%;
                max-width: 800px;
                max-height: 80vh;
                display: flex;
                flex-direction: column;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
                animation: modalSlideUp 0.3s ease-out;
            }
            
            @keyframes modalSlideUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .block-selector-header {
                padding: 24px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .block-selector-header h2 {
                margin: 0;
                color: white;
                font-size: 20px;
            }
            
            .modal-close-btn {
                background: none;
                border: none;
                color: rgba(255, 255, 255, 0.6);
                font-size: 32px;
                line-height: 1;
                cursor: pointer;
                padding: 0;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 6px;
                transition: all 0.2s;
            }
            
            .modal-close-btn:hover {
                background: rgba(255, 255, 255, 0.1);
                color: white;
            }
            
            .block-selector-search {
                padding: 16px 24px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }
            
            .block-selector-search input {
                width: 100%;
                padding: 12px 16px;
                background: rgba(0, 0, 0, 0.3);
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 8px;
                color: white;
                font-size: 14px;
            }
            
            .block-selector-search input::placeholder {
                color: rgba(255, 255, 255, 0.4);
            }
            
            .block-selector-body {
                padding: 24px;
                overflow-y: auto;
            }
            
            .block-category {
                margin-bottom: 32px;
            }
            
            .category-title {
                color: rgba(255, 255, 255, 0.6);
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 1px;
                margin-bottom: 12px;
            }
            
            .block-types-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 12px;
            }
            
            .block-type-card {
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                padding: 16px;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                gap: 12px;
            }
            
            .block-type-card:hover {
                background: rgba(59, 130, 246, 0.1);
                border-color: rgba(59, 130, 246, 0.4);
                transform: translateY(-2px);
            }
            
            .block-type-icon {
                font-size: 24px;
            }
            
            .block-type-info h4 {
                margin: 0 0 4px 0;
                color: white;
                font-size: 14px;
            }
            
            .block-type-info p {
                margin: 0;
                color: rgba(255, 255, 255, 0.6);
                font-size: 12px;
            }
            
            /* Notification */
            .block-notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 8px;
                color: white;
                font-size: 14px;
                font-weight: 600;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                opacity: 0;
                transform: translateX(100px);
                transition: all 0.3s;
                z-index: 10001;
            }
            
            .block-notification.show {
                opacity: 1;
                transform: translateX(0);
            }
            
            .block-notification-success {
                background: #10b981;
            }
            
            .block-notification-error {
                background: #ef4444;
            }
            
            /* Empty Region */
            .region-empty {
                min-height: 120px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .empty-region-placeholder {
                color: rgba(255, 255, 255, 0.3);
                font-style: italic;
                text-align: center;
            }
        `;
  }
}

// Initialize when DOM is ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => new BlockInlineEditor());
} else {
  new BlockInlineEditor();
}
