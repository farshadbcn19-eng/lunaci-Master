<?php
/**
 * Plugin Name: LUNACI Mobile Nav Fix
 * Description: Site-wide mobile navigation. Instead of adapting to each
 *              page's own inconsistent nav markup (Home/About/Contact are
 *              frozen Elementor widgets, Products is native HTML), this
 *              injects one independent full-screen mobile nav overlay with
 *              its own hamburger button and hardcoded Products/Shop/
 *              Contact/About links, identical on every page. Every page's
 *              own nav-links container and toggle button are hidden on
 *              mobile to avoid duplication; desktop nav is untouched. Also
 *              keeps the site-wide link rewrite for stale /collections-2,
 *              /about, and leftover relative-filename hrefs found in
 *              Elementor's frozen page data.
 * Author: LUNACI
 */

defined('ABSPATH') || exit;

add_action('wp_footer', function () {
    ?>
    <style id="lunaci-mobile-nav-fix-css">
    @media (max-width: 768px) {
        .ln-nav__links,
        .lna-nav__links,
        .lunaci-nav-links,
        header > nav ul,
        #lunaci-nav-toggle,
        .lunaci-nav-cta,
        .nav-cta,
        .ln-nav__cart,
        .lna-nav__cart {
            display: none !important;
        }

        #lunaci-global-toggle {
            display: flex !important;
        }
    }

    #lunaci-global-toggle {
        display: none;
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 100000;
        flex-direction: column;
        justify-content: center;
        gap: 5px;
        width: 32px;
        height: 32px;
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
    }
    #lunaci-global-toggle span {
        display: block;
        width: 100%;
        height: 2px;
        background: #D4AF37;
    }

    #lunaci-global-nav-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: #0B0B0B;
        z-index: 99999;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 32px;
    }
    #lunaci-global-nav-overlay.lunaci-global-nav-open {
        display: flex;
    }
    #lunaci-global-nav-overlay a {
        font-family: 'Montserrat', sans-serif;
        font-size: 14px;
        font-weight: 500;
        letter-spacing: 0.3em;
        text-transform: uppercase;
        color: #F7F4EE;
        text-decoration: none;
    }
    #lunaci-global-nav-overlay a:hover {
        color: #D4AF37;
    }

    #lunaci-global-close {
        position: absolute;
        top: 24px;
        right: 24px;
        background: none;
        border: none;
        color: #D4AF37;
        font-size: 24px;
        cursor: pointer;
        z-index: 100001;
    }

    #lunaci-overlay-logo {
        position: absolute;
        top: 24px;
        left: 24px;
    }
    </style>

    <button type="button" id="lunaci-global-toggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="lunaci-global-nav-overlay">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <div id="lunaci-global-nav-overlay" aria-hidden="true">
        <button type="button" id="lunaci-global-close" aria-label="Close menu">&#10005;</button>
        <a href="https://lunacibarcelona.com/" id="lunaci-overlay-logo">
            <img src="https://lunacibarcelona.com/wp-content/uploads/2026/05/b320427b-bdbd-4220-926d-c2fecce7e9e4.jpeg" alt="LUNACI Barcelona" style="height:60px; mix-blend-mode:lighten;">
        </a>
        <a href="https://lunacibarcelona.com/products/">Products</a>
        <a href="https://lunacibarcelona.com/shop/">Shop</a>
        <a href="https://lunacibarcelona.com/about-us/">About</a>
        <a href="https://lunacibarcelona.com/contact/">Contact</a>
    </div>

    <script id="lunaci-mobile-nav-fix-js">
    (function () {
        function initGlobalNav() {
            var btn = document.getElementById('lunaci-global-toggle');
            var overlay = document.getElementById('lunaci-global-nav-overlay');
            if (!btn || !overlay) {
                return;
            }

            btn.addEventListener('click', function () {
                var isOpen = overlay.classList.toggle('lunaci-global-nav-open');
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                overlay.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            });

            overlay.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    overlay.classList.remove('lunaci-global-nav-open');
                    btn.setAttribute('aria-expanded', 'false');
                    overlay.setAttribute('aria-hidden', 'true');
                });
            });

            var closeBtn = document.getElementById('lunaci-global-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    overlay.classList.remove('lunaci-global-nav-open');
                    btn.setAttribute('aria-expanded', 'false');
                    overlay.setAttribute('aria-hidden', 'true');
                });
            }
        }

        function fixStaleLinks() {
            var filenameMap = {
                'index.html': '/',
                'products.html': '/products/',
                'shop.html': '/shop/',
                'contact.html': '/contact/'
            };

            document.querySelectorAll('a[href]').forEach(function (a) {
                var url;
                try {
                    url = new URL(a.href);
                } catch (e) {
                    return;
                }

                if (url.pathname === '/collections-2' || url.pathname === '/collections-2/') {
                    url.pathname = '/products/';
                    a.href = url.toString();
                    return;
                }
                if (url.pathname === '/about' || url.pathname === '/about/') {
                    url.pathname = '/about-us/';
                    a.href = url.toString();
                    return;
                }

                var segments = url.pathname.split('/');
                var lastSegment = segments[segments.length - 1];
                if (Object.prototype.hasOwnProperty.call(filenameMap, lastSegment)) {
                    url.pathname = filenameMap[lastSegment];
                    a.href = url.toString();
                }
            });
        }

        function init() {
            initGlobalNav();
            fixStaleLinks();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    </script>
    <?php
}, 100);
