/**
 * YouPlay Performance Optimizer
 * Advanced performance optimizations and monitoring
 */

(function() {
    'use strict';

    // Performance monitoring
    const performanceMonitor = {
        metrics: {},
        
        start(name) {
            this.metrics[name] = performance.now();
        },
        
        end(name) {
            if (this.metrics[name]) {
                const duration = performance.now() - this.metrics[name];
                console.log(`${name}: ${duration.toFixed(2)}ms`);
                return duration;
            }
        },
        
        mark(name) {
            if ('performance' in window && 'mark' in performance) {
                performance.mark(name);
            }
        },
        
        measure(name, startMark, endMark) {
            if ('performance' in window && 'measure' in performance) {
                performance.measure(name, startMark, endMark);
            }
        }
    };

    // Image lazy loading with intersection observer
    class LazyImageLoader {
        constructor() {
            this.imageObserver = null;
            this.init();
        }
        
        init() {
            if ('IntersectionObserver' in window) {
                this.imageObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            this.loadImage(entry.target);
                            this.imageObserver.unobserve(entry.target);
                        }
                    });
                }, {
                    rootMargin: '50px 0px',
                    threshold: 0.01
                });
                
                this.observeImages();
            }
        }
        
        observeImages() {
            const images = document.querySelectorAll('img[data-src]');
            images.forEach(img => {
                this.imageObserver.observe(img);
            });
        }
        
        loadImage(img) {
            const src = img.dataset.src;
            if (src) {
                img.src = src;
                img.classList.add('loaded');
                img.removeAttribute('data-src');
            }
        }
    }

    // Video lazy loading
    class LazyVideoLoader {
        constructor() {
            this.videoObserver = null;
            this.init();
        }
        
        init() {
            if ('IntersectionObserver' in window) {
                this.videoObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            this.loadVideo(entry.target);
                            this.videoObserver.unobserve(entry.target);
                        }
                    });
                }, {
                    rootMargin: '100px 0px',
                    threshold: 0.1
                });
                
                this.observeVideos();
            }
        }
        
        observeVideos() {
            const videos = document.querySelectorAll('video[data-src]');
            videos.forEach(video => {
                this.videoObserver.observe(video);
            });
        }
        
        loadVideo(video) {
            const src = video.dataset.src;
            if (src) {
                video.src = src;
                video.classList.add('loaded');
                video.removeAttribute('data-src');
            }
        }
    }

    // Memory management
    class MemoryManager {
        constructor() {
            this.cleanupInterval = null;
            this.init();
        }
        
        init() {
            // Clean up unused DOM elements every 30 seconds
            this.cleanupInterval = setInterval(() => {
                this.cleanup();
            }, 30000);
            
            // Clean up on page visibility change
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.cleanup();
                }
            });
        }
        
        cleanup() {
            // Remove unused event listeners
            this.removeUnusedEventListeners();
            
            // Clean up unused DOM elements
            this.removeUnusedElements();
            
            // Force garbage collection if available
            if (window.gc) {
                window.gc();
            }
        }
        
        removeUnusedEventListeners() {
            // This would need to be implemented based on specific use cases
            // For now, we'll just log the cleanup
            console.log('Cleaning up unused event listeners');
        }
        
        removeUnusedElements() {
            // Remove elements with .remove-me class
            const elementsToRemove = document.querySelectorAll('.remove-me');
            elementsToRemove.forEach(el => el.remove());
        }
    }

    // Network optimization
    class NetworkOptimizer {
        constructor() {
            this.connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            this.init();
        }
        
        init() {
            if (this.connection) {
                this.adaptToConnection();
                this.connection.addEventListener('change', () => {
                    this.adaptToConnection();
                });
            }
        }
        
        adaptToConnection() {
            const effectiveType = this.connection.effectiveType;
            
            switch (effectiveType) {
                case 'slow-2g':
                case '2g':
                    this.enableLowBandwidthMode();
                    break;
                case '3g':
                    this.enableMediumBandwidthMode();
                    break;
                case '4g':
                    this.enableHighBandwidthMode();
                    break;
            }
        }
        
        enableLowBandwidthMode() {
            document.body.classList.add('low-bandwidth');
            // Disable animations
            document.body.style.setProperty('--animation-duration', '0ms');
            // Reduce image quality
            this.reduceImageQuality();
        }
        
        enableMediumBandwidthMode() {
            document.body.classList.add('medium-bandwidth');
            // Reduce animation duration
            document.body.style.setProperty('--animation-duration', '200ms');
        }
        
        enableHighBandwidthMode() {
            document.body.classList.remove('low-bandwidth', 'medium-bandwidth');
            // Full quality
            document.body.style.setProperty('--animation-duration', '300ms');
        }
        
        reduceImageQuality() {
            const images = document.querySelectorAll('img');
            images.forEach(img => {
                if (img.src.includes('upload/')) {
                    // Add quality parameter to reduce image size
                    img.src = img.src.replace(/(\.(jpg|jpeg|png|webp))$/i, '_q50$1');
                }
            });
        }
    }

    // Scroll optimization
    class ScrollOptimizer {
        constructor() {
            this.ticking = false;
            this.lastScrollY = 0;
            this.init();
        }
        
        init() {
            // Use passive event listeners for better performance
            window.addEventListener('scroll', this.onScroll.bind(this), { passive: true });
            window.addEventListener('resize', this.onResize.bind(this), { passive: true });
        }
        
        onScroll() {
            if (!this.ticking) {
                requestAnimationFrame(() => {
                    this.updateScroll();
                    this.ticking = false;
                });
                this.ticking = true;
            }
        }
        
        onResize() {
            // Debounce resize events
            clearTimeout(this.resizeTimeout);
            this.resizeTimeout = setTimeout(() => {
                this.handleResize();
            }, 250);
        }
        
        updateScroll() {
            const scrollY = window.scrollY;
            const direction = scrollY > this.lastScrollY ? 'down' : 'up';
            
            // Update navbar based on scroll direction
            if (scrollY > 100) {
                document.body.classList.add('scrolled');
                if (direction === 'down') {
                    document.body.classList.add('scroll-down');
                    document.body.classList.remove('scroll-up');
                } else {
                    document.body.classList.add('scroll-up');
                    document.body.classList.remove('scroll-down');
                }
            } else {
                document.body.classList.remove('scrolled', 'scroll-down', 'scroll-up');
            }
            
            this.lastScrollY = scrollY;
        }
        
        handleResize() {
            // Handle responsive changes
            const width = window.innerWidth;
            
            if (width < 768) {
                document.body.classList.add('mobile');
                document.body.classList.remove('tablet', 'desktop');
            } else if (width < 1024) {
                document.body.classList.add('tablet');
                document.body.classList.remove('mobile', 'desktop');
            } else {
                document.body.classList.add('desktop');
                document.body.classList.remove('mobile', 'tablet');
            }
        }
    }

    // Preload critical resources
    class ResourcePreloader {
        constructor() {
            this.preloadedResources = new Set();
            this.init();
        }
        
        init() {
            this.preloadCriticalImages();
            this.preloadCriticalCSS();
            this.preloadCriticalJS();
        }
        
        preloadCriticalImages() {
            const criticalImages = [
                'logo.png',
                'icon.png'
            ];
            
            criticalImages.forEach(src => {
                this.preloadImage(src);
            });
        }
        
        preloadCriticalCSS() {
            // Preload critical CSS files
            const criticalCSS = [
                'bootstrap.min.css',
                'style.css'
            ];
            
            criticalCSS.forEach(href => {
                this.preloadResource(href, 'style');
            });
        }
        
        preloadCriticalJS() {
            // Preload critical JS files
            const criticalJS = [
                'jquery-3.min.js',
                'bootstrap.min.js'
            ];
            
            criticalJS.forEach(href => {
                this.preloadResource(href, 'script');
            });
        }
        
        preloadImage(src) {
            if (!this.preloadedResources.has(src)) {
                const link = document.createElement('link');
                link.rel = 'preload';
                link.as = 'image';
                link.href = src;
                document.head.appendChild(link);
                this.preloadedResources.add(src);
            }
        }
        
        preloadResource(href, as) {
            if (!this.preloadedResources.has(href)) {
                const link = document.createElement('link');
                link.rel = 'preload';
                link.as = as;
                link.href = href;
                document.head.appendChild(link);
                this.preloadedResources.add(href);
            }
        }
    }

    // Initialize all optimizations
    function initPerformanceOptimizer() {
        performanceMonitor.start('performance-init');
        
        // Initialize components
        new LazyImageLoader();
        new LazyVideoLoader();
        new MemoryManager();
        new NetworkOptimizer();
        new ScrollOptimizer();
        new ResourcePreloader();
        
        // Monitor page load performance
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                performanceMonitor.mark('dom-content-loaded');
                performanceMonitor.measure('dom-load-time', 'navigationStart', 'dom-content-loaded');
            });
        }
        
        window.addEventListener('load', () => {
            performanceMonitor.mark('page-loaded');
            performanceMonitor.measure('page-load-time', 'navigationStart', 'page-loaded');
            performanceMonitor.end('performance-init');
        });
        
        // Monitor Core Web Vitals
        this.observeCoreWebVitals();
    }
    
    // Core Web Vitals monitoring
    function observeCoreWebVitals() {
        // Largest Contentful Paint (LCP)
        if ('PerformanceObserver' in window) {
            const lcpObserver = new PerformanceObserver((list) => {
                const entries = list.getEntries();
                const lastEntry = entries[entries.length - 1];
                console.log('LCP:', lastEntry.startTime);
            });
            lcpObserver.observe({ entryTypes: ['largest-contentful-paint'] });
            
            // First Input Delay (FID)
            const fidObserver = new PerformanceObserver((list) => {
                const entries = list.getEntries();
                entries.forEach(entry => {
                    console.log('FID:', entry.processingStart - entry.startTime);
                });
            });
            fidObserver.observe({ entryTypes: ['first-input'] });
            
            // Cumulative Layout Shift (CLS)
            let clsValue = 0;
            const clsObserver = new PerformanceObserver((list) => {
                const entries = list.getEntries();
                entries.forEach(entry => {
                    if (!entry.hadRecentInput) {
                        clsValue += entry.value;
                    }
                });
                console.log('CLS:', clsValue);
            });
            clsObserver.observe({ entryTypes: ['layout-shift'] });
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPerformanceOptimizer);
    } else {
        initPerformanceOptimizer();
    }

    // Export for debugging
    window.YouPlayPerformance = {
        monitor: performanceMonitor,
        version: '1.0.0'
    };

})();

