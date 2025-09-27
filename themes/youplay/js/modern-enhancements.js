/**
 * YouPlay Modern Enhancements JavaScript
 * Enhanced animations, smooth scrolling, and performance optimizations
 */

(function($) {
    'use strict';

    // Performance optimization - debounce function
    function debounce(func, wait, immediate) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

    // Throttle function for scroll events
    function throttle(func, limit) {
        var inThrottle;
        return function() {
            var args = arguments;
            var context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        }
    }

    // Initialize modern enhancements
    function initModernEnhancements() {
        // Add loading overlay
        addLoadingOverlay();
        
        // Initialize smooth scrolling
        initSmoothScrolling();
        
        // Initialize intersection observer for animations
        initScrollAnimations();
        
        // Initialize lazy loading
        initLazyLoading();
        
        // Initialize enhanced hover effects
        initHoverEffects();
        
        // Initialize enhanced search
        initEnhancedSearch();
        
        // Initialize parallax effects
        initParallaxEffects();
        
        // Initialize performance monitoring
        initPerformanceMonitoring();
    }

    // Add loading overlay
    function addLoadingOverlay() {
        if ($('.loading-overlay').length === 0) {
            $('body').prepend(`
                <div class="loading-overlay" id="loading-overlay">
                    <div class="loading-spinner"></div>
                </div>
            `);
            
            // Hide loading overlay after page load
            $(window).on('load', function() {
                setTimeout(function() {
                    $('#loading-overlay').addClass('fade-out');
                    setTimeout(function() {
                        $('#loading-overlay').remove();
                    }, 500);
                }, 800);
            });
        }
    }

    // Smooth scrolling for anchor links
    function initSmoothScrolling() {
        $('a[href*="#"]:not([href="#"])').on('click', function(e) {
            if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') 
                && location.hostname == this.hostname) {
                var target = $(this.hash);
                target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
                if (target.length) {
                    e.preventDefault();
                    $('html, body').animate({
                        scrollTop: target.offset().top - 80
                    }, 800, 'easeInOutQuart');
                }
            }
        });
    }

    // Intersection Observer for scroll animations
    function initScrollAnimations() {
        if ('IntersectionObserver' in window) {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-in');
                    }
                });
            }, observerOptions);

            // Observe elements for animation
            $('.video-wrapper, .content, .card, .comment-item').each(function() {
                $(this).addClass('animate-ready');
                observer.observe(this);
            });
        }
    }

    // Lazy loading for images
    function initLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        img.classList.add('loaded');
                        imageObserver.unobserve(img);
                    }
                });
            });

            $('img[data-src]').each(function() {
                $(this).addClass('lazy');
                imageObserver.observe(this);
            });
        }
    }

    // Enhanced hover effects
    function initHoverEffects() {
        // Video card hover effects
        $('.video-wrapper').on('mouseenter', function() {
            $(this).addClass('will-change-transform');
        }).on('mouseleave', function() {
            $(this).removeClass('will-change-transform');
        });

        // Button ripple effect
        $('.btn').on('click', function(e) {
            const $btn = $(this);
            const ripple = $('<span class="ripple"></span>');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.css({
                width: size,
                height: size,
                left: x,
                top: y
            });
            
            $btn.append(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });

        // Enhanced tooltip positioning
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body',
            animation: true,
            delay: { show: 500, hide: 100 }
        });
    }

    // Enhanced search with debouncing
    function initEnhancedSearch() {
        const searchInput = $('.search-form input[type="text"]');
        if (searchInput.length) {
            const debouncedSearch = debounce(function() {
                const query = $(this).val();
                if (query.length > 2) {
                    // Add search animation
                    $(this).addClass('searching');
                    // Simulate search (replace with actual search logic)
                    setTimeout(() => {
                        $(this).removeClass('searching');
                    }, 1000);
                }
            }, 300);

            searchInput.on('input', debouncedSearch);
        }
    }

    // Parallax effects for hero sections
    function initParallaxEffects() {
        const parallaxElements = $('.parallax');
        if (parallaxElements.length) {
            const handleParallax = throttle(function() {
                const scrolled = $(window).scrollTop();
                parallaxElements.each(function() {
                    const $this = $(this);
                    const speed = $this.data('speed') || 0.5;
                    const yPos = -(scrolled * speed);
                    $this.css('transform', `translateY(${yPos}px)`);
                });
            }, 16);

            $(window).on('scroll', handleParallax);
        }
    }

    // Performance monitoring
    function initPerformanceMonitoring() {
        // Monitor page load performance
        if ('performance' in window) {
            $(window).on('load', function() {
                setTimeout(function() {
                    const perfData = performance.getEntriesByType('navigation')[0];
                    console.log('Page Load Performance:', {
                        'DOM Content Loaded': perfData.domContentLoadedEventEnd - perfData.domContentLoadedEventStart,
                        'Load Complete': perfData.loadEventEnd - perfData.loadEventStart,
                        'Total Load Time': perfData.loadEventEnd - perfData.fetchStart
                    });
                }, 0);
            });
        }

        // Monitor scroll performance
        let scrollCount = 0;
        const scrollHandler = throttle(function() {
            scrollCount++;
            // Add scroll performance class after heavy scrolling
            if (scrollCount > 100) {
                $('body').addClass('scroll-heavy');
            }
        }, 16);

        $(window).on('scroll', scrollHandler);
    }

    // Enhanced video player interactions
    function initVideoPlayerEnhancements() {
        // Video hover effects
        $('.video-player').on('mouseenter', function() {
            $(this).addClass('player-hover');
        }).on('mouseleave', function() {
            $(this).removeClass('player-hover');
        });

        // Video thumbnail click animation
        $('.video-thumb').on('click', function(e) {
            e.preventDefault();
            const $thumb = $(this);
            const $video = $thumb.closest('.video-wrapper').find('.video-title a');
            
            // Add click animation
            $thumb.addClass('clicked');
            setTimeout(() => {
                $thumb.removeClass('clicked');
                if ($video.length) {
                    window.location.href = $video.attr('href');
                }
            }, 300);
        });
    }

    // Enhanced form interactions
    function initFormEnhancements() {
        // Floating labels
        $('.form-control').each(function() {
            const $input = $(this);
            const $label = $input.siblings('label');
            
            if ($label.length) {
                $input.on('focus blur', function() {
                    if ($(this).val() || $(this).is(':focus')) {
                        $label.addClass('floating');
                    } else {
                        $label.removeClass('floating');
                    }
                });
            }
        });

        // Form validation animations
        $('.form-control').on('invalid', function() {
            $(this).addClass('shake');
            setTimeout(() => {
                $(this).removeClass('shake');
            }, 500);
        });
    }

    // Enhanced modal interactions
    function initModalEnhancements() {
        $('.modal').on('show.bs.modal', function() {
            $(this).find('.modal-content').addClass('modal-entering');
        });

        $('.modal').on('shown.bs.modal', function() {
            $(this).find('.modal-content').removeClass('modal-entering').addClass('modal-enter');
        });

        $('.modal').on('hide.bs.modal', function() {
            $(this).find('.modal-content').addClass('modal-leaving');
        });
    }

    // Initialize all enhancements when document is ready
    $(document).ready(function() {
        initModernEnhancements();
        initVideoPlayerEnhancements();
        initFormEnhancements();
        initModalEnhancements();

        // Add CSS animations styles
        $('<style>')
            .prop('type', 'text/css')
            .html(`
                .animate-ready {
                    opacity: 0;
                    transform: translateY(30px);
                    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
                }
                
                .animate-in {
                    opacity: 1 !important;
                    transform: translateY(0) !important;
                }
                
                .lazy {
                    opacity: 0;
                    transition: opacity 0.3s;
                }
                
                .loaded {
                    opacity: 1;
                }
                
                .ripple {
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.6);
                    transform: scale(0);
                    animation: ripple-animation 0.6s linear;
                    pointer-events: none;
                }
                
                @keyframes ripple-animation {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
                
                .searching {
                    position: relative;
                }
                
                .searching::after {
                    content: '';
                    position: absolute;
                    right: 10px;
                    top: 50%;
                    transform: translateY(-50%);
                    width: 16px;
                    height: 16px;
                    border: 2px solid #04abf2;
                    border-top: 2px solid transparent;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                }
                
                .shake {
                    animation: shake 0.5s ease-in-out;
                }
                
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    25% { transform: translateX(-5px); }
                    75% { transform: translateX(5px); }
                }
                
                .floating {
                    transform: translateY(-20px) scale(0.85);
                    color: #04abf2;
                }
                
                .modal-entering {
                    transform: scale(0.7);
                    opacity: 0;
                }
                
                .modal-enter {
                    transform: scale(1);
                    opacity: 1;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }
                
                .modal-leaving {
                    transform: scale(0.7);
                    opacity: 0;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }
                
                .clicked {
                    transform: scale(0.95);
                    transition: transform 0.1s;
                }
                
                .player-hover {
                    transform: scale(1.02);
                    transition: transform 0.3s ease;
                }
                
                .scroll-heavy * {
                    will-change: auto !important;
                }
                
                .easeInOutQuart {
                    animation-timing-function: cubic-bezier(0.77, 0, 0.175, 1);
                }
            `)
            .appendTo('head');
    });

})(jQuery);

