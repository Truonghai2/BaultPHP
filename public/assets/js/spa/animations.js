/**
 * Web Animations API Wrapper v3.0
 * 
 * Features:
 * - Pre-defined animations
 * - Animation sequences
 * - Gesture-based animations
 * - Performance optimized
 * - Easing functions
 * - Animation groups
 */

export class Animator {
  constructor() {
    this.activeAnimations = new Map();
    this.animationQueue = [];
  }

  /**
   * Fade in animation
   */
  fadeIn(element, options = {}) {
    return this.animate(element, [
      { opacity: 0 },
      { opacity: 1 }
    ], {
      duration: options.duration || 300,
      easing: options.easing || 'ease-out',
      ...options
    });
  }

  /**
   * Fade out animation
   */
  fadeOut(element, options = {}) {
    return this.animate(element, [
      { opacity: 1 },
      { opacity: 0 }
    ], {
      duration: options.duration || 300,
      easing: options.easing || 'ease-in',
      ...options
    });
  }

  /**
   * Slide in animation
   */
  slideIn(element, direction = 'left', options = {}) {
    const transforms = {
      left: [{ transform: 'translateX(-100%)' }, { transform: 'translateX(0)' }],
      right: [{ transform: 'translateX(100%)' }, { transform: 'translateX(0)' }],
      up: [{ transform: 'translateY(100%)' }, { transform: 'translateY(0)' }],
      down: [{ transform: 'translateY(-100%)' }, { transform: 'translateY(0)' }]
    };

    return this.animate(element, transforms[direction], {
      duration: options.duration || 400,
      easing: options.easing || 'cubic-bezier(0.4, 0, 0.2, 1)',
      ...options
    });
  }

  /**
   * Scale animation
   */
  scale(element, from = 0, to = 1, options = {}) {
    return this.animate(element, [
      { transform: `scale(${from})`, opacity: from === 0 ? 0 : 1 },
      { transform: `scale(${to})`, opacity: 1 }
    ], {
      duration: options.duration || 300,
      easing: options.easing || 'ease-out',
      ...options
    });
  }

  /**
   * Bounce animation
   */
  bounce(element, options = {}) {
    return this.animate(element, [
      { transform: 'scale(1)' },
      { transform: 'scale(1.1)' },
      { transform: 'scale(0.95)' },
      { transform: 'scale(1)' }
    ], {
      duration: options.duration || 600,
      easing: 'ease-in-out',
      ...options
    });
  }

  /**
   * Shake animation
   */
  shake(element, options = {}) {
    return this.animate(element, [
      { transform: 'translateX(0)' },
      { transform: 'translateX(-10px)' },
      { transform: 'translateX(10px)' },
      { transform: 'translateX(-10px)' },
      { transform: 'translateX(10px)' },
      { transform: 'translateX(0)' }
    ], {
      duration: options.duration || 500,
      ...options
    });
  }

  /**
   * Pulse animation
   */
  pulse(element, options = {}) {
    return this.animate(element, [
      { transform: 'scale(1)' },
      { transform: 'scale(1.05)' },
      { transform: 'scale(1)' }
    ], {
      duration: options.duration || 1000,
      iterations: options.iterations || Infinity,
      ...options
    });
  }

  /**
   * Flip animation
   */
  flip(element, axis = 'y', options = {}) {
    const transforms = {
      x: [
        { transform: 'rotateX(0deg)' },
        { transform: 'rotateX(180deg)' }
      ],
      y: [
        { transform: 'rotateY(0deg)' },
        { transform: 'rotateY(180deg)' }
      ]
    };

    return this.animate(element, transforms[axis], {
      duration: options.duration || 600,
      easing: 'ease-in-out',
      ...options
    });
  }

  /**
   * Generic animate method
   */
  animate(element, keyframes, options = {}) {
    if (typeof element === 'string') {
      element = document.querySelector(element);
    }

    if (!element) {
      console.warn('Animation target not found');
      return Promise.resolve();
    }

    const animation = element.animate(keyframes, {
      duration: 300,
      easing: 'ease',
      fill: 'both',
      ...options
    });

    // Store active animation
    const id = this.generateId();
    this.activeAnimations.set(id, animation);

    // Clean up when done
    animation.finished.then(() => {
      this.activeAnimations.delete(id);
    }).catch(() => {
      this.activeAnimations.delete(id);
    });

    return animation.finished;
  }

  /**
   * Animate sequence
   */
  async sequence(animations) {
    for (const anim of animations) {
      await anim.play();
    }
  }

  /**
   * Animate in parallel
   */
  async parallel(animations) {
    return Promise.all(animations.map(anim => anim.play()));
  }

  /**
   * Stagger animations
   */
  stagger(elements, animation, delay = 100) {
    const animations = [];
    
    elements.forEach((element, index) => {
      setTimeout(() => {
        animations.push(animation(element));
      }, delay * index);
    });

    return Promise.all(animations);
  }

  /**
   * Cancel all active animations
   */
  cancelAll() {
    this.activeAnimations.forEach(anim => anim.cancel());
    this.activeAnimations.clear();
  }

  /**
   * Generate unique ID
   */
  generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
  }
}

/**
 * Easing functions
 */
export const easings = {
  easeInQuad: 'cubic-bezier(0.55, 0.085, 0.68, 0.53)',
  easeOutQuad: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
  easeInOutQuad: 'cubic-bezier(0.455, 0.03, 0.515, 0.955)',
  easeInCubic: 'cubic-bezier(0.55, 0.055, 0.675, 0.19)',
  easeOutCubic: 'cubic-bezier(0.215, 0.61, 0.355, 1)',
  easeInOutCubic: 'cubic-bezier(0.645, 0.045, 0.355, 1)',
  easeInQuart: 'cubic-bezier(0.895, 0.03, 0.685, 0.22)',
  easeOutQuart: 'cubic-bezier(0.165, 0.84, 0.44, 1)',
  easeInOutQuart: 'cubic-bezier(0.77, 0, 0.175, 1)',
  easeInQuint: 'cubic-bezier(0.755, 0.05, 0.855, 0.06)',
  easeOutQuint: 'cubic-bezier(0.23, 1, 0.32, 1)',
  easeInOutQuint: 'cubic-bezier(0.86, 0, 0.07, 1)',
  easeInExpo: 'cubic-bezier(0.95, 0.05, 0.795, 0.035)',
  easeOutExpo: 'cubic-bezier(0.19, 1, 0.22, 1)',
  easeInOutExpo: 'cubic-bezier(1, 0, 0, 1)',
  easeInCirc: 'cubic-bezier(0.6, 0.04, 0.98, 0.335)',
  easeOutCirc: 'cubic-bezier(0.075, 0.82, 0.165, 1)',
  easeInOutCirc: 'cubic-bezier(0.785, 0.135, 0.15, 0.86)',
  easeInBack: 'cubic-bezier(0.6, -0.28, 0.735, 0.045)',
  easeOutBack: 'cubic-bezier(0.175, 0.885, 0.32, 1.275)',
  easeInOutBack: 'cubic-bezier(0.68, -0.55, 0.265, 1.55)',
};

// Export singleton instance
export const animator = new Animator();

export default animator;
