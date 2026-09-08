<?php
/**
 * READ-ONLY. Two things needed before touching either open issue:
 *
 * 1. /products/ and /es/productos/ render a garbled / missing meta
 *    description even though /shop/ and /es/tienda/ (the native
 *    WooCommerce archive, post 56 EN / 609 ES) were already fixed.
 *    That means /products/ is NOT the same post/template as /shop/ -
 *    find out what it actually is: post ID, post_type, page template,
 *    whether it has its own wp_aioseo_posts row, and what (if anything)
 *    is currently in that row's description field.
 *
 * 2. The duplicate-catalog redirect (/shop/ -> /products/) needs to
 *    know: is there an .htaccess in the webroot we can add rules to,
 *    or should this be a template_redirect hook in a mu-plugin/theme
 *    functions.php instead? And does AIOSEO's sitemap read live from
 *    published/queryable posts (so a redirect alone drops /shop/ from
 *    it) or does it need an explicit exclusion setting?
 */

global $wpdb;

function lunaci_describe_url( string $path ) {
	global $wpdb;
	$url = home_url( $path );
	$post_id = url_to_postid( $url );
	echo "  url_to_postid('{$path}') = " . var_export( $post_id, true ) . "\n";
	if ( $post_id ) {
		$post = get_post( $post_id );
		echo '    post_type: ' . $post->post_type . "\n";
		echo '    post_status: ' . $post->post_status . "\n";
		echo '    post_title: ' . $post->post_title . "\n";
		$template = get_page_template_slug( $post_id );
		echo '    _wp_page_template: ' . var_export( $template, true ) . "\n";
		$builder = get_post_meta( $post_id, '_elementor_edit_mode', true );
		echo "    _elementor_edit_mode: " . var_export( $builder, true ) . "\n";

		$aioseo = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, post_id, title, description, LENGTH(description) as desc_len FROM {$wpdb->prefix}aioseo_posts WHERE post_id = %d",
			$post_id
		), ARRAY_A );
		if ( $aioseo ) {
			echo "    wp_aioseo_posts row: id={$aioseo['id']} desc_len={$aioseo['desc_len']}\n";
			echo '    aioseo description: ' . var_export( $aioseo['description'], true ) . "\n";
		} else {
			echo "    wp_aioseo_posts row: NONE\n";
		}
	}
}

echo "--- /products/ (EN) ---\n";
lunaci_describe_url( '/products/' );

echo "\n--- /es/productos/ (ES) ---\n";
lunaci_describe_url( '/es/productos/' );

echo "\n--- /shop/ (EN, for comparison - already fixed) ---\n";
lunaci_describe_url( '/shop/' );

echo "\n--- /es/tienda/ (ES, for comparison - already fixed) ---\n";
lunaci_describe_url( '/es/tienda/' );

echo "\n--- woocommerce_shop_page_id option ---\n";
echo var_export( get_option( 'woocommerce_shop_page_id' ), true ) . "\n";

echo "\n--- is a rewrite rule mapping /products/ to the shop archive, or is it a real page? ---\n";
global $wp_rewrite;
echo "permalink structure: " . get_option( 'permalink_structure' ) . "\n";

echo "\n--- webroot / .htaccess check ---\n";
echo 'ABSPATH: ' . ABSPATH . "\n";
$htaccess = ABSPATH . '.htaccess';
echo "{$htaccess} exists: " . ( file_exists( $htaccess ) ? 'yes' : 'no' ) . "\n";
if ( file_exists( $htaccess ) ) {
	echo 'writable: ' . ( is_writable( $htaccess ) ? 'yes' : 'no' ) . "\n";
	echo "size: " . filesize( $htaccess ) . " bytes\n";
}

echo "\n--- active theme / mu-plugins (for where a template_redirect hook would live) ---\n";
echo 'stylesheet: ' . get_stylesheet() . "\n";
echo 'template: ' . get_template() . "\n";
echo 'mu-plugins dir: ' . WPMU_PLUGIN_DIR . ' exists: ' . ( is_dir( WPMU_PLUGIN_DIR ) ? 'yes' : 'no' ) . "\n";
if ( is_dir( WPMU_PLUGIN_DIR ) ) {
	$files = glob( WPMU_PLUGIN_DIR . '/*.php' );
	echo 'mu-plugin files: ' . ( $files ? implode( ', ', array_map( 'basename', $files ) ) : '(none)' ) . "\n";
}

echo "\n--- AIOSEO sitemap: how are excluded posts/pages configured? ---\n";
$aioseo_opts_raw = get_option( 'aioseo_options' );
$aioseo_opts = json_decode( (string) $aioseo_opts_raw, true );
if ( is_array( $aioseo_opts ) && isset( $aioseo_opts['sitemap'] ) ) {
	echo json_encode( $aioseo_opts['sitemap'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
} else {
	echo "no aioseo_options.sitemap key found\n";
}

echo "\nOK: read-only diagnostic complete\n";
