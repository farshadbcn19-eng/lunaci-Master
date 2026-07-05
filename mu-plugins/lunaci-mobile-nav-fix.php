<?php
/**
 * Plugin Name: LUNACI Mobile Nav Fix
 * Description: Injects a working mobile-menu toggle and fixes stale nav
 *              links on Home, About, and Contact. Home/About/Contact are
 *              rendered by Elementor from _elementor_data (a frozen
 *              raw-HTML widget), not from post_content, so editing the
 *              page HTML in the lunaci-Master repo never reaches them.
 *              This hooks wp_footer instead, which runs on every request
 *              regardless of which field Elementor rendered the page from.
 *              Contact's nav has no id/class of its own and its links are
 *              leftover relative filenames (index.html, products.html,
 *              shop.html, contact.html) from a static template that was
 *              never adapted to WordPress permalinks; both are fixed here
 *              too.
 * Author: LUNACI
 */

defined('ABSPATH') || exit;

add_action('wp_footer', function () {
    ?>
    <style id="lunaci-mobile-nav-fix-css">
    @media (max-width: 768px) {
        .lunaci-collapsible-links {
            display: none !important;
        }
        .lunaci-collapsible-links.lunaci-nav-open {
            display: flex !important;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            padding: 100px 24px 24px;
            background: #0B0B0B;
            overflow-y: auto;
            z-index: 99998;
        }
        .lunaci-mobile-toggle {
            display: flex !important;
            position: relative;
            z-index: 99999;
        }
        .nav-cta,
        .lunaci-nav-cta {
            display: none !important;
        }
        .ln-nav__cart,
        .lna-nav__cart {
            display: none !important;
        }
    }
    .lunaci-mobile-toggle {
        display: none;
        flex-direction: column;
        justify-content: center;
        gap: 5px;
        width: 32px;
        height: 24px;
        background: none;
        border: none;
        cursor: pointer;
        margin-left: auto;
        padding: 0;
    }
    .lunaci-mobile-toggle span {
        display: block;
        width: 100%;
        height: 2px;
        background: #D4AF37;
    }
    </style>
    <script id="lunaci-mobile-nav-fix-js">
    (function () {
        var navTargets = [
            { nav: '#lnNav', links: '.ln-nav__links' },
            { nav: '#lnaNav', links: '.lna-nav__links' },
            { nav: 'header > nav', links: 'ul' }
        ];

        function initToggles() {
            navTargets.forEach(function (t) {
                var nav = document.querySelector(t.nav);
                var links = nav ? nav.querySelector(t.links) : null;
                if (!nav || !links || nav.querySelector('.lunaci-mobile-toggle')) {
                    return;
                }

                links.classList.add('lunaci-collapsible-links');

                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'lunaci-mobile-toggle';
                btn.setAttribute('aria-label', 'Toggle navigation menu');
                btn.setAttribute('aria-expanded', 'false');
                btn.innerHTML = '<span></span><span></span><span></span>';
                nav.insertBefore(btn, links);

                btn.addEventListener('click', function () {
                    var isOpen = links.classList.toggle('lunaci-nav-open');
                    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });

                links.querySelectorAll('a').forEach(function (link) {
                    link.addEventListener('click', function () {
                        links.classList.remove('lunaci-nav-open');
                        btn.setAttribute('aria-expanded', 'false');
                    });
                });
            });
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
            initToggles();
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
