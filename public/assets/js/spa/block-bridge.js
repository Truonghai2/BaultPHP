/**
 * SPA Block Bridge v1.0
 * 
 * Kết nối SPA Router với hệ thống Page Block
 * 
 * Features:
 * - Load blocks dynamically qua API
 * - Render blocks trong SPA routes
 * - Tích hợp với Block Inline Editor
 * - Cache blocks để tối ưu performance
 * - Support lazy loading blocks
 */

export class BlockBridge {
  constructor(options = {}) {
    this.options = {
      apiEndpoint: options.apiEndpoint || '/api/pages',
      cacheBlocks: options.cacheBlocks !== false,
      cacheDuration: options.cacheDuration || 300000, // 5 minutes
      enableInlineEdit: options.enableInlineEdit !== false,
      ...options
    };

    this.blockCache = new Map();
    this.blockTypeCache = new Map();
    this.regionCache = new Map();
  }

  /**
   * Load page với tất cả blocks
   */
  async loadPage(slug) {
    // Check cache
    const cacheKey = `page:${slug}`;
    if (this.options.cacheBlocks && this.blockCache.has(cacheKey)) {
      const cached = this.blockCache.get(cacheKey);
      if (Date.now() - cached.timestamp < this.options.cacheDuration) {
        return cached.data;
      }
    }

    try {
      const response = await fetch(`${this.options.apiEndpoint}/${slug}`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (!response.ok) {
        throw new Error(`Failed to load page: ${response.status}`);
      }

      const data = await response.json();

      // Cache result
      if (this.options.cacheBlocks) {
        this.blockCache.set(cacheKey, {
          data,
          timestamp: Date.now()
        });
      }

      return data;

    } catch (error) {
      console.error('BlockBridge: Failed to load page', error);
      throw error;
    }
  }

  /**
   * Load blocks cho một region cụ thể
   */
  async loadRegionBlocks(pageId, region) {
    const cacheKey = `region:${pageId}:${region}`;
    
    if (this.options.cacheBlocks && this.regionCache.has(cacheKey)) {
      const cached = this.regionCache.get(cacheKey);
      if (Date.now() - cached.timestamp < this.options.cacheDuration) {
        return cached.data;
      }
    }

    try {
      const response = await fetch(`${this.options.apiEndpoint}/${pageId}/blocks/${region}`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (!response.ok) {
        throw new Error(`Failed to load region blocks: ${response.status}`);
      }

      const data = await response.json();

      // Cache
      if (this.options.cacheBlocks) {
        this.regionCache.set(cacheKey, {
          data,
          timestamp: Date.now()
        });
      }

      return data;

    } catch (error) {
      console.error('BlockBridge: Failed to load region blocks', error);
      return { blocks: [] };
    }
  }

  /**
   * Render page với tất cả regions và blocks
   */
  async renderPage(slug) {
    const pageData = await this.loadPage(slug);

    if (!pageData || !pageData.page) {
      return this.renderNotFound(slug);
    }

    const { page, regions } = pageData;

    // Build HTML cho tất cả regions
    const regionsHtml = await this.renderRegions(regions);

    return `
      <div class="page" data-page-id="${page.id}" data-page-slug="${page.slug}">
        <div class="page-content">
          ${regionsHtml}
        </div>
      </div>
    `;
  }

  /**
   * Render tất cả regions
   */
  async renderRegions(regions) {
    if (!regions || typeof regions !== 'object') {
      return '';
    }

    const regionNames = Object.keys(regions);
    const htmlParts = [];

    for (const regionName of regionNames) {
      const blocks = regions[regionName] || [];
      const regionHtml = await this.renderRegion(regionName, blocks);
      htmlParts.push(regionHtml);
    }

    return htmlParts.join('\n');
  }

  /**
   * Render một region với blocks
   */
  async renderRegion(regionName, blocks) {
    const blocksHtml = blocks.map(block => this.renderBlock(block)).join('\n');

    return `
      <div class="region region-${regionName}" data-region="${regionName}">
        ${blocksHtml || this.renderEmptyRegion(regionName)}
      </div>
    `;
  }

  /**
   * Render empty region (for edit mode)
   */
  renderEmptyRegion(regionName) {
    if (!this.options.enableInlineEdit) {
      return '';
    }

    return `
      <div class="region-empty">
        <p>No blocks in ${regionName} region</p>
      </div>
    `;
  }

  /**
   * Render một block
   */
  renderBlock(block) {
    const blockClasses = [
      'block',
      `block-${block.block_type}`,
      `block-id-${block.id}`
    ];

    if (block.css_classes) {
      blockClasses.push(...block.css_classes);
    }

    const attributes = {
      'data-block-id': block.id,
      'data-block-type': block.block_type,
      'data-block-type-id': block.block_type_id,
      'data-region': block.region
    };

    const attrsHtml = Object.entries(attributes)
      .map(([key, value]) => `${key}="${value}"`)
      .join(' ');

    return `
      <div class="${blockClasses.join(' ')}" ${attrsHtml}>
        ${block.rendered_content || block.content || ''}
      </div>
    `;
  }

  /**
   * Render 404 page
   */
  renderNotFound(slug) {
    return `
      <div class="page-not-found">
        <h1>Page Not Found</h1>
        <p>The page "${slug}" could not be found.</p>
        <a href="/" class="btn">Go Home</a>
      </div>
    `;
  }

  /**
   * Clear cache
   */
  clearCache() {
    this.blockCache.clear();
    this.regionCache.clear();
    this.blockTypeCache.clear();
  }

  /**
   * Clear cache cho một page cụ thể
   */
  clearPageCache(slug) {
    const cacheKey = `page:${slug}`;
    this.blockCache.delete(cacheKey);
  }

  /**
   * Reload blocks trong một region (for live updates)
   */
  async reloadRegion(pageId, regionName) {
    const cacheKey = `region:${pageId}:${regionName}`;
    this.regionCache.delete(cacheKey);

    const blocks = await this.loadRegionBlocks(pageId, regionName);
    const regionEl = document.querySelector(`.region[data-region="${regionName}"]`);

    if (regionEl) {
      regionEl.innerHTML = blocks.blocks
        .map(block => this.renderBlock(block))
        .join('\n');

      // Dispatch event for any listeners
      window.dispatchEvent(new CustomEvent('region:reloaded', {
        detail: { region: regionName, pageId, blocks: blocks.blocks }
      }));
    }

    return blocks;
  }

  /**
   * Add new block to region (for inline editor)
   */
  async addBlock(pageId, regionName, blockTypeId, content = null) {
    try {
      const response = await fetch(`${this.options.apiEndpoint}/${pageId}/blocks`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': this.getCsrfToken()
        },
        body: JSON.stringify({
          block_type_id: blockTypeId,
          region: regionName,
          content: content
        })
      });

      if (!response.ok) {
        throw new Error('Failed to add block');
      }

      const result = await response.json();

      // Clear cache and reload region
      await this.reloadRegion(pageId, regionName);

      return result;

    } catch (error) {
      console.error('BlockBridge: Failed to add block', error);
      throw error;
    }
  }

  /**
   * Update block
   */
  async updateBlock(blockId, data) {
    try {
      const response = await fetch(`${this.options.apiEndpoint}/blocks/${blockId}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': this.getCsrfToken()
        },
        body: JSON.stringify(data)
      });

      if (!response.ok) {
        throw new Error('Failed to update block');
      }

      return await response.json();

    } catch (error) {
      console.error('BlockBridge: Failed to update block', error);
      throw error;
    }
  }

  /**
   * Delete block
   */
  async deleteBlock(blockId) {
    try {
      const response = await fetch(`${this.options.apiEndpoint}/blocks/${blockId}`, {
        method: 'DELETE',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': this.getCsrfToken()
        }
      });

      if (!response.ok) {
        throw new Error('Failed to delete block');
      }

      return await response.json();

    } catch (error) {
      console.error('BlockBridge: Failed to delete block', error);
      throw error;
    }
  }

  /**
   * Get CSRF token
   */
  getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  }

  /**
   * Enable inline editing mode
   */
  enableInlineEditing() {
    if (window.BlockInlineEditor) {
      // Initialize block inline editor if available
      if (!window.blockEditor) {
        window.blockEditor = new window.BlockInlineEditor();
      }
    }
  }

  /**
   * Integrate with SPA Router
   */
  integrateWithRouter(router) {
    // Add route component factory
    router.blockBridge = this;

    // Add helper method to router
    router.pageComponent = async (slug) => {
      return await this.renderPage(slug);
    };

    // Listen to navigation events to clear cache if needed
    window.addEventListener('router:navigated', (event) => {
      const { route } = event.detail;
      
      // Optionally clear cache on navigation
      if (route.meta && route.meta.clearBlockCache) {
        this.clearCache();
      }

      // Re-enable inline editing after navigation
      if (this.options.enableInlineEdit) {
        setTimeout(() => {
          this.enableInlineEditing();
        }, 100);
      }
    });
  }
}

/**
 * Create SPA route from page slug
 */
export function createPageRoute(slug, blockBridge) {
  return {
    path: slug === 'home' ? '/' : `/${slug}`,
    name: slug,
    component: async () => {
      return await blockBridge.renderPage(slug);
    },
    meta: {
      isBlockPage: true
    }
  };
}

/**
 * Load all pages and create routes
 */
export async function loadPageRoutes(blockBridge) {
  try {
    const response = await fetch(`${blockBridge.options.apiEndpoint}`, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    if (!response.ok) {
      throw new Error('Failed to load pages');
    }

    const pages = await response.json();

    return pages.map(page => createPageRoute(page.slug, blockBridge));

  } catch (error) {
    console.error('BlockBridge: Failed to load page routes', error);
    return [];
  }
}

// Export singleton instance
export const blockBridge = new BlockBridge();

export default BlockBridge;
