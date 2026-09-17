<?php
/**
 * READ-ONLY. The "ALL / FACE / LIPS / EYES / NAILS" filter bar that
 * appears on every /product-category/{slug}/ archive page has an "All"
 * button hard-coded to https://lunacibarcelona.com/shop/. A prior
 * session redirected /shop/ -> /products/ to fix a "duplicate catalog"
 * problem, so "All" now dead-ends on the marketing/portfolio page
 * instead of showing a real product grid - the other 4 buttons still
 * correctly link to their own category archive (which IS a real grid).
 *
 * Before building a genuine "all products" grid page, this figures
 * out:
 * 1. Where the "lunaci-filter-btn" bar itself is injected (theme
 *    template override, mu-plugin hook, or WPCode snippet) - so a fix
 *    to it lands once and applies to every category page automatically.
 * 2. What the /shop/ -> /products/ redirect actually is (hook,
 *    condition, exact target) and whether it's slug-based or ID-based.
 * 3. What `woocommerce_shop_page_id` points to, and whether that
 *    underlying page/post still has real WooCommerce archive content
 *    or is itself just an empty shell now.
 * 4. Whether any currently-unused slug/page already exists that could
 *    safely host a real "all products" grid without colliding with the
 *    existing redirect or recreating the old duplicate-catalog problem.
 */

global $wpdb;

echo "--- 1. locate 'lunaci-filter-btn' across mu-plugins, active theme, and WPCode snippets ---\n";

$mu_files = glob( WPMU_PLUGIN_DIR . '/*.php' );
foreach ( (array) $mu_files as $f ) {
	$contents = file_get_contents( $f );
	if ( false !== strpos( $contents, 'lunaci-filter-btn' ) ) {
		echo "FOUND in mu-plugin: {$f}\n";
	}
}

$theme_dir = get_stylesheet_directory();
$theme_files = array();
if ( is_dir( $theme_dir ) ) {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $theme_dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $fileinfo ) {
		if ( 'php' === $fileinfo->getExtension() ) {
			$theme_files[] = $fileinfo->getPathname();
		}
	}
}
foreach ( $theme_files as $f ) {
	$contents = @file_get_contents( $f );
	if ( $contents && false !== strpos( $contents, 'lunaci-filter-btn' ) ) {
		echo "FOUND in active theme file: {$f}\n";
	}
}

// WPCode stores snippets as posts (post_type 'wpcode').
$snips = $wpdb->get_results( "SELECT ID, post_title, LENGTH(post_content) as len FROM {$wpdb->posts} WHERE post_type = 'wpcode' AND post_content LIKE '%lunaci-filter-btn%'", ARRAY_A );
if ( $snips ) {
	foreach ( $snips as $s ) {
		echo "FOUND in wpcode snippet post: ID={$s['ID']} title={$s['post_title']} len={$s['len']}\n";
	}
} else {
	echo "(no wpcode snippet post contains 'lunaci-filter-btn')\n";
}

// Also check plain wp_posts content (page templates that literally output this HTML)
// and postmeta (Elementor archive templates, theme builder conditions).
$post_hits = $wpdb->get_results( "SELECT ID, post_type, post_title, post_status FROM {$wpdb->posts} WHERE post_content LIKE '%lunaci-filter-btn%'", ARRAY_A );
foreach ( (array) $post_hits as $p ) {
	echo "FOUND in wp_posts.post_content: ID={$p['ID']} type={$p['post_type']} title={$p['post_title']} status={$p['post_status']}\n";
}
$meta_hits = $wpdb->get_results( "SELECT post_id, meta_key, meta_id, LENGTH(meta_value) as len FROM {$wpdb->postmeta} WHERE meta_value LIKE '%lunaci-filter-btn%'", ARRAY_A );
foreach ( (array) $meta_hits as $m ) {
	echo "FOUND in postmeta: post_id={$m['post_id']} meta_key={$m['meta_key']} meta_id={$m['meta_id']} len={$m['len']}\n";
	$post = get_post( $m['post_id'] );
	if ( $post ) {
		echo "  -> post_type={$post->post_type} title={$post->post_title} status={$post->post_status}\n";
	}
}

echo "\n--- 2. the /shop/ -> /products/ redirect: where does it live, what does it check? ---\n";
foreach ( (array) $mu_files as $f ) {
	$contents = file_get_contents( $f );
	if ( false !== strpos( $contents, "'/shop/'" ) || false !== strpos( $contents, '"/shop/"' ) || false !== stripos( $contents, 'template_redirect' ) ) {
		echo "candidate mu-plugin file: {$f}\n";
		echo "----- content -----\n{$contents}\n----- end -----\n\n";
	}
}

echo "\n--- 3. woocommerce_shop_page_id and its underlying post ---\n";
$shop_page_id = get_option( 'woocommerce_shop_page_id' );
echo 'woocommerce_shop_page_id: ' . var_export( $shop_page_id, true ) . "\n";
if ( $shop_page_id ) {
	$shop_post = get_post( $shop_page_id );
	if ( $shop_post ) {
		echo "post_title: {$shop_post->post_title}\n";
		echo "post_name (slug): {$shop_post->post_name}\n";
		echo "post_status: {$shop_post->post_status}\n";
		echo 'post_content length: ' . strlen( $shop_post->post_content ) . "\n";
		echo 'raw permalink (get_permalink): ' . get_permalink( $shop_page_id ) . "\n";
		$elementor_data = get_post_meta( $shop_page_id, '_elementor_data', true );
		echo 'has _elementor_data: ' . ( $elementor_data ? 'yes, len=' . strlen( $elementor_data ) : 'no' ) . "\n";
	} else {
		echo "get_post() returned nothing for this ID\n";
	}
}

echo "\n--- 4. is_shop() reachable at all right now, and what does wc_get_page_permalink('shop') say? ---\n";
if ( function_exists( 'wc_get_page_permalink' ) ) {
	echo "wc_get_page_permalink('shop'): " . wc_get_page_permalink( 'shop' ) . "\n";
}

echo "\n--- 5. any existing unused/draft page whose slug could safely host a real grid ---\n";
$candidates = $wpdb->get_results(
	"SELECT ID, post_title, post_name, post_status, post_type FROM {$wpdb->posts}
	 WHERE post_type IN ('page') AND post_status IN ('draft','pending','private')
	 ORDER BY ID DESC LIMIT 20",
	ARRAY_A
);
foreach ( (array) $candidates as $c ) {
	echo "candidate: ID={$c['ID']} slug={$c['post_name']} status={$c['post_status']} title={$c['post_title']}\n";
}

echo "\nOK: read-only diagnostic complete\n";
