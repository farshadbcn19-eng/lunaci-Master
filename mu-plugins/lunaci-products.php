<?php
/**
 * Plugin Name: LUNACI Page Shortcodes
 * Description: Renders LUNACI static HTML page fragments (deployed from the
 *              lunaci-Master repo's pages/ folder) via shortcodes, so an
 *              Elementor "Shortcode" widget can embed them without
 *              copy/pasting raw HTML into the editor.
 * Author: LUNACI
 */

defined('ABSPATH') || exit;

/**
 * Reads a page fragment from the theme's pages/ folder.
 *
 * Returns an empty string for regular visitors if the file is missing (so a
 * not-yet-built page never shows a broken shortcode on the live site), and
 * an HTML comment for logged-in admins so it's obvious why nothing rendered.
 */
function lunaci_render_page_fragment($filename) {
    $path = WP_CONTENT_DIR . '/themes/lunaci/pages/' . $filename;

    if (!is_readable($path)) {
        return current_user_can('manage_options')
            ? '<!-- LUNACI shortcode: ' . esc_html($filename) . ' not found in theme pages/ folder -->'
            : '';
    }

    return file_get_contents($path);
}

add_shortcode('lunaci_products', function () {
    return lunaci_render_page_fragment('products.html');
});

add_shortcode('lunaci_contact', function () {
    return lunaci_render_page_fragment('contact.html');
});

add_shortcode('lunaci_home', function () {
    return lunaci_render_page_fragment('home.html');
});
