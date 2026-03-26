/**
 * SEO Enhancement Module v3.0
 * 
 * Features:
 * - Dynamic meta tags
 * - Open Graph tags
 * - Twitter Cards
 * - Structured data (JSON-LD)
 * - Canonical URLs
 * - Sitemap generation
 * - Robot meta tags
 */

export class SEOManager {
  constructor() {
    this.defaultMeta = this.getDefaultMeta();
  }

  /**
   * Update page meta tags
   */
  updateMeta(config = {}) {
    const meta = { ...this.defaultMeta, ...config };

    // Title
    if (meta.title) {
      document.title = meta.title;
      this.updateMetaTag('og:title', meta.title, 'property');
      this.updateMetaTag('twitter:title', meta.title);
    }

    // Description
    if (meta.description) {
      this.updateMetaTag('description', meta.description);
      this.updateMetaTag('og:description', meta.description, 'property');
      this.updateMetaTag('twitter:description', meta.description);
    }

    // Keywords
    if (meta.keywords) {
      this.updateMetaTag('keywords', meta.keywords);
    }

    // Author
    if (meta.author) {
      this.updateMetaTag('author', meta.author);
    }

    // URL
    if (meta.url) {
      this.updateMetaTag('og:url', meta.url, 'property');
      this.updateCanonicalUrl(meta.url);
    }

    // Image
    if (meta.image) {
      this.updateMetaTag('og:image', meta.image, 'property');
      this.updateMetaTag('twitter:image', meta.image);
      
      if (meta.imageAlt) {
        this.updateMetaTag('og:image:alt', meta.imageAlt, 'property');
        this.updateMetaTag('twitter:image:alt', meta.imageAlt);
      }
    }

    // Type
    if (meta.type) {
      this.updateMetaTag('og:type', meta.type, 'property');
    }

    // Twitter card
    if (meta.twitterCard) {
      this.updateMetaTag('twitter:card', meta.twitterCard);
    }

    // Site name
    if (meta.siteName) {
      this.updateMetaTag('og:site_name', meta.siteName, 'property');
    }

    // Locale
    if (meta.locale) {
      this.updateMetaTag('og:locale', meta.locale, 'property');
    }

    // Robots
    if (meta.robots) {
      this.updateMetaTag('robots', meta.robots);
    }
  }

  /**
   * Update or create meta tag
   */
  updateMetaTag(name, content, attribute = 'name') {
    let meta = document.querySelector(`meta[${attribute}="${name}"]`);
    
    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute(attribute, name);
      document.head.appendChild(meta);
    }
    
    meta.setAttribute('content', content);
  }

  /**
   * Update canonical URL
   */
  updateCanonicalUrl(url) {
    let link = document.querySelector('link[rel="canonical"]');
    
    if (!link) {
      link = document.createElement('link');
      link.setAttribute('rel', 'canonical');
      document.head.appendChild(link);
    }
    
    link.setAttribute('href', url);
  }

  /**
   * Add structured data (JSON-LD)
   */
  addStructuredData(data) {
    const scriptId = 'structured-data';
    let script = document.getElementById(scriptId);
    
    if (script) {
      script.remove();
    }
    
    script = document.createElement('script');
    script.id = scriptId;
    script.type = 'application/ld+json';
    script.textContent = JSON.stringify(data);
    document.head.appendChild(script);
  }

  /**
   * Create Article structured data
   */
  createArticleStructuredData(article) {
    return {
      '@context': 'https://schema.org',
      '@type': 'Article',
      headline: article.title,
      description: article.description,
      image: article.image,
      author: {
        '@type': 'Person',
        name: article.author
      },
      datePublished: article.publishedAt,
      dateModified: article.updatedAt || article.publishedAt,
      publisher: {
        '@type': 'Organization',
        name: article.publisher || this.defaultMeta.siteName,
        logo: {
          '@type': 'ImageObject',
          url: article.publisherLogo || this.defaultMeta.logo
        }
      }
    };
  }

  /**
   * Create Product structured data
   */
  createProductStructuredData(product) {
    return {
      '@context': 'https://schema.org',
      '@type': 'Product',
      name: product.name,
      description: product.description,
      image: product.image,
      brand: {
        '@type': 'Brand',
        name: product.brand
      },
      offers: {
        '@type': 'Offer',
        price: product.price,
        priceCurrency: product.currency || 'USD',
        availability: product.inStock ? 
          'https://schema.org/InStock' : 
          'https://schema.org/OutOfStock',
        url: product.url
      },
      aggregateRating: product.rating ? {
        '@type': 'AggregateRating',
        ratingValue: product.rating,
        reviewCount: product.reviewCount || 0
      } : undefined
    };
  }

  /**
   * Create BreadcrumbList structured data
   */
  createBreadcrumbStructuredData(breadcrumbs) {
    return {
      '@context': 'https://schema.org',
      '@type': 'BreadcrumbList',
      itemListElement: breadcrumbs.map((crumb, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        name: crumb.name,
        item: crumb.url
      }))
    };
  }

  /**
   * Create Organization structured data
   */
  createOrganizationStructuredData(org) {
    return {
      '@context': 'https://schema.org',
      '@type': 'Organization',
      name: org.name,
      url: org.url,
      logo: org.logo,
      description: org.description,
      contactPoint: org.contactPoint ? {
        '@type': 'ContactPoint',
        telephone: org.contactPoint.phone,
        contactType: org.contactPoint.type || 'customer service',
        email: org.contactPoint.email
      } : undefined,
      sameAs: org.socialLinks || []
    };
  }

  /**
   * Create FAQ structured data
   */
  createFAQStructuredData(faqs) {
    return {
      '@context': 'https://schema.org',
      '@type': 'FAQPage',
      mainEntity: faqs.map(faq => ({
        '@type': 'Question',
        name: faq.question,
        acceptedAnswer: {
          '@type': 'Answer',
          text: faq.answer
        }
      }))
    };
  }

  /**
   * Update language/locale
   */
  updateLanguage(locale) {
    document.documentElement.lang = locale.split('_')[0];
    this.updateMetaTag('og:locale', locale, 'property');
  }

  /**
   * Add alternate language links
   */
  addAlternateLanguages(languages) {
    // Remove existing alternate links
    document.querySelectorAll('link[rel="alternate"][hreflang]').forEach(link => {
      link.remove();
    });

    // Add new alternate links
    languages.forEach(lang => {
      const link = document.createElement('link');
      link.rel = 'alternate';
      link.hreflang = lang.code;
      link.href = lang.url;
      document.head.appendChild(link);
    });
  }

  /**
   * Update page robots meta
   */
  setRobots(value) {
    this.updateMetaTag('robots', value);
  }

  /**
   * Set page as noindex
   */
  noindex() {
    this.setRobots('noindex, nofollow');
  }

  /**
   * Set page as indexable
   */
  index() {
    this.setRobots('index, follow');
  }

  /**
   * Add preload links
   */
  preload(resources) {
    resources.forEach(resource => {
      const link = document.createElement('link');
      link.rel = 'preload';
      link.href = resource.url;
      link.as = resource.as; // 'script', 'style', 'image', 'font'
      
      if (resource.type) {
        link.type = resource.type;
      }
      
      if (resource.crossorigin) {
        link.crossOrigin = resource.crossorigin;
      }
      
      document.head.appendChild(link);
    });
  }

  /**
   * Add DNS prefetch
   */
  dnsPrefetch(domains) {
    domains.forEach(domain => {
      const link = document.createElement('link');
      link.rel = 'dns-prefetch';
      link.href = domain;
      document.head.appendChild(link);
    });
  }

  /**
   * Get default meta configuration
   */
  getDefaultMeta() {
    return {
      siteName: 'BaultPHP',
      type: 'website',
      locale: 'en_US',
      twitterCard: 'summary_large_image',
      robots: 'index, follow'
    };
  }

  /**
   * Generate sitemap (client-side helper)
   */
  generateSitemap(routes) {
    const urls = routes.map(route => ({
      loc: route.url,
      lastmod: route.lastmod || new Date().toISOString().split('T')[0],
      changefreq: route.changefreq || 'weekly',
      priority: route.priority || 0.5
    }));

    return urls;
  }

  /**
   * Track page view (for analytics integration)
   */
  trackPageView(url, title) {
    // Google Analytics
    if (typeof gtag !== 'undefined') {
      gtag('config', 'GA_MEASUREMENT_ID', {
        page_path: url,
        page_title: title
      });
    }

    // Facebook Pixel
    if (typeof fbq !== 'undefined') {
      fbq('track', 'PageView');
    }

    // Custom analytics
    window.dispatchEvent(new CustomEvent('seo:pageview', {
      detail: { url, title }
    }));
  }
}

// Export singleton instance
export const seo = new SEOManager();

export default seo;
