/**
 * Simple Progress Bar for SPA Navigation
 * Lightweight alternative to NProgress
 */

export class SpaProgressBar {
  constructor(options = {}) {
    this.options = {
      color: options.color || "#3b82f6",
      height: options.height || "3px",
      speed: options.speed || 300,
      trickleSpeed: options.trickleSpeed || 800,
      minimum: options.minimum || 0.08,
      easing: options.easing || "ease",
      showSpinner: options.showSpinner !== false,
      ...options,
    };

    this.status = null;
    this.bar = null;
    this.spinner = null;
    this.trickleTimeout = null;
  }

  /**
   * Start the progress bar
   */
  start() {
    if (this.status !== null) {
      return this;
    }

    this.status = 0;
    this.render();
    this.set(this.options.minimum);

    // Trickle to make it look like progress is happening
    const work = () => {
      setTimeout(() => {
        if (this.status === null) return;
        this.trickle();
        work();
      }, this.options.trickleSpeed);
    };

    work();

    return this;
  }

  /**
   * Set progress to a specific value
   */
  set(n) {
    const started = this.status !== null;
    n = clamp(n, this.options.minimum, 1);
    this.status = n === 1 ? null : n;

    const progress = this.bar;
    if (!progress) return this;

    progress.style.transition = `all ${this.options.speed}ms ${this.options.easing}`;
    progress.style.transform = `translate3d(${(-1 + n) * 100}%, 0, 0)`;

    if (n === 1) {
      progress.style.transition = "none";
      progress.style.opacity = "1";

      setTimeout(() => {
        progress.style.transition = `all ${this.options.speed}ms linear`;
        progress.style.opacity = "0";
        setTimeout(() => {
          this.remove();
        }, this.options.speed);
      }, this.options.speed);
    }

    return this;
  }

  /**
   * Increment progress by a random amount
   */
  trickle() {
    return this.inc();
  }

  /**
   * Increment progress
   */
  inc(amount) {
    let n = this.status;

    if (n === null) {
      return this.start();
    } else if (n > 1) {
      return this;
    } else {
      if (typeof amount !== "number") {
        if (n >= 0 && n < 0.2) amount = 0.1;
        else if (n >= 0.2 && n < 0.5) amount = 0.04;
        else if (n >= 0.5 && n < 0.8) amount = 0.02;
        else if (n >= 0.8 && n < 0.99) amount = 0.005;
        else amount = 0;
      }

      n = clamp(n + amount, 0, 0.994);
      return this.set(n);
    }
  }

  /**
   * Complete the progress
   */
  done(force = false) {
    if (!force && this.status === null) {
      return this;
    }

    return this.inc(0.3 + 0.5 * Math.random()).set(1);
  }

  /**
   * Render the progress bar
   */
  render() {
    if (this.bar) return this;

    // Create container
    const container = document.createElement("div");
    container.id = "spa-progress";
    container.innerHTML = this.template();

    this.bar = container.querySelector(".spa-progress-bar");

    if (this.options.showSpinner) {
      this.spinner = container.querySelector(".spa-progress-spinner");
    }

    document.body.appendChild(container);

    // Add styles
    this.addStyles();

    return this;
  }

  /**
   * Remove the progress bar
   */
  remove() {
    const container = document.getElementById("spa-progress");
    if (container) {
      container.remove();
    }
    this.bar = null;
    this.spinner = null;
  }

  /**
   * HTML template
   */
  template() {
    return `
      <div class="spa-progress-bar" role="bar">
        <div class="spa-progress-peg"></div>
      </div>
      ${
        this.options.showSpinner
          ? `
      <div class="spa-progress-spinner" role="spinner">
        <div class="spa-progress-spinner-icon"></div>
      </div>
      `
          : ""
      }
    `;
  }

  /**
   * Add styles to page
   */
  addStyles() {
    if (document.getElementById("spa-progress-styles")) {
      return;
    }

    const style = document.createElement("style");
    style.id = "spa-progress-styles";
    style.textContent = `
      #spa-progress {
        pointer-events: none;
      }

      #spa-progress .spa-progress-bar {
        background: ${this.options.color};
        position: fixed;
        z-index: 9999;
        top: 0;
        left: 0;
        width: 100%;
        height: ${this.options.height};
        transform: translate3d(-100%, 0, 0);
        box-shadow: 0 0 10px ${this.options.color}, 0 0 5px ${this.options.color};
      }

      #spa-progress .spa-progress-peg {
        display: block;
        position: absolute;
        right: 0;
        width: 100px;
        height: 100%;
        box-shadow: 0 0 10px ${this.options.color}, 0 0 5px ${this.options.color};
        opacity: 1.0;
        transform: rotate(3deg) translate(0px, -4px);
      }

      #spa-progress .spa-progress-spinner {
        display: block;
        position: fixed;
        z-index: 9999;
        top: 15px;
        right: 15px;
      }

      #spa-progress .spa-progress-spinner-icon {
        width: 18px;
        height: 18px;
        box-sizing: border-box;
        border: solid 2px transparent;
        border-top-color: ${this.options.color};
        border-left-color: ${this.options.color};
        border-radius: 50%;
        animation: spa-progress-spinner 400ms linear infinite;
      }

      @keyframes spa-progress-spinner {
        0%   { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }
    `;

    document.head.appendChild(style);
  }
}

/**
 * Clamp a number between min and max
 */
function clamp(n, min, max) {
  if (n < min) return min;
  if (n > max) return max;
  return n;
}

// Export singleton instance
export const progress = new SpaProgressBar();
