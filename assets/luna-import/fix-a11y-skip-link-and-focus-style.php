<?php
/**
 * GUARDED FIX. WCAG 2.2 findings from the round-6 A11y audit:
 *  - 2.4.1 Bypass Blocks: no "skip to content" link anywhere on the site
 *  - 2.4.7 Focus Visible: newsletter input (.ln-news__in) removes the
 *    default focus outline with no visible replacement
 *
 * Both are added via a small mu-plugin hooking wp_body_open() (fires
 * on every page, including Elementor Canvas templates, right after
 * <body>) and wp_head() - theme/template independent, zero risk to
 * any page's visual layout since nothing existing is touched or
 * removed. The skip link targets id="content", which the companion
 * <main id="content"> fix provides on the homepage/products pages and
 * which the Contact page's theme template already has.
 *
 * Idempotent: skips if the mu-plugin file already exists.
 */

$mu_plugins_dir = WP_CONTENT_DIR . '/mu-plugins';
$target_file     = $mu_plugins_dir . '/lunaci-a11y-skip-link.php';

if ( file_exists( $target_file ) ) {
	echo "SKIP: $target_file already exists, not overwriting\n";
	echo "OK: fix completed successfully (nothing to do)\n";
	return;
}

if ( ! is_dir( $mu_plugins_dir ) ) {
	if ( ! mkdir( $mu_plugins_dir, 0755, true ) && ! is_dir( $mu_plugins_dir ) ) {
		echo "ABORT: could not create $mu_plugins_dir\n";
		return;
	}
	echo "Created directory: $mu_plugins_dir\n";
}

$plugin_code = <<<'PHP'
<?php
/**
 * Plugin Name: LUNACI - A11y skip link + focus-visible fix
 * Description: WCAG 2.2 fixes: (1) a "skip to content" bypass-blocks
 *              link on every page (2.4.1), landing on id="content";
 *              (2) a visible focus style for the newsletter input,
 *              which otherwise suppresses the default outline (2.4.7).
 *              Added via wp_body_open/wp_head so it applies regardless
 *              of template (including Elementor Canvas pages) without
 *              touching any existing markup.
 */

add_action( 'wp_body_open', function () {
	echo '<a class="lunaci-skip-link" href="#content">Skip to content</a>';
} );

add_action( 'wp_head', function () {
	echo '<style>
.lunaci-skip-link {
	position: absolute;
	left: -9999px;
	top: 0;
	z-index: 100000;
	padding: 10px 18px;
	background: #201e28;
	color: #ffffff;
	font: 14px/1.4 system-ui, sans-serif;
	text-decoration: none;
	border-radius: 0 0 6px 0;
}
.lunaci-skip-link:focus {
	left: 0;
}
.ln-news__in:focus,
.ln-news__in:focus-visible {
	outline: 2px solid #4a4e8c;
	outline-offset: 2px;
}
</style>';
} );
PHP;

$written = file_put_contents( $target_file, $plugin_code );

if ( $written === false ) {
	echo "ABORT: file_put_contents failed writing $target_file\n";
	return;
}

echo "Wrote $written bytes to $target_file\n";

$readback = file_get_contents( $target_file );
if ( $readback !== $plugin_code ) {
	echo "ABORT: readback does not match what was written\n";
	return;
}

echo "OK: fix completed successfully\n";
